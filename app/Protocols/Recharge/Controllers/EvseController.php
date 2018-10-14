<?php
namespace Wormhole\Protocols\QianNiu\Controllers;
/**
 * Created by PhpStorm.
 * User: sc
 * Date: 2017-05-12
 * Time: 17:47
 */

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Wormhole\Http\Controllers\Controller;
use Wormhole\Protocols\QianNiu\Models\Evse;
use Wormhole\Protocols\QianNiu\Models\Port;
use Wormhole\Protocols\QianNiu\Models\Turnover;
use Wormhole\Protocols\QianNiu\Models\ModifyInfoLog;
use Wormhole\Protocols\QianNiu\Protocol;
use Wormhole\Protocols\QianNiu\Protocol\Frame;
use \Curl\Curl;
use Wormhole\Protocols\QianNiu\Controllers\ProtocolController;
use Wormhole\Protocols\QianNiu\EventsApi;

//检测启动续费停止是否收到桩响应
use Wormhole\Protocols\QianNiu\Jobs\CheckResponse;
use Illuminate\Support\Facades\Queue;

//自动停止上报,如果monitor未成功接收到数据,继续队列发送
use Wormhole\Protocols\QianNiu\Jobs\AutoStopCharge;

//日结上报,如果monitor未成功接收到数据,继续队列发送
use Wormhole\Protocols\QianNiu\Jobs\Report;

//心跳队列
use Wormhole\Protocols\QianNiu\Jobs\CheckHeartbeat;

use Illuminate\Support\Facades\Redis;
use Wormhole\Protocols\Library\Log as Logger;

use Wormhole\Protocols\MonitorServer;
//订单映射表
use Wormhole\Protocols\QianNiu\Models\ChargeOrderMapping;
//充电记录表
use Wormhole\Protocols\QianNiu\Models\ChargeRecords;


//签到
use Wormhole\Protocols\QianNiu\Protocol\Server\Sign as ServerSign;
//自动停止
use Wormhole\Protocols\QianNiu\Protocol\Server\AutomaticStop as ServerAutomaticStop;
//日结
use Wormhole\Protocols\QianNiu\Protocol\Server\Report as ServerReport;
//心跳
use Wormhole\Protocols\QianNiu\Protocol\Evse\Heartbeat as EvseHeartbeat;
use Wormhole\Protocols\QianNiu\Protocol\Server\Heartbeat as ServerHeartbeat;


//启动充电
use Wormhole\Protocols\QianNiu\Protocol\Server\StartCharge as ServerStartCharge;
//续费
use Wormhole\Protocols\QianNiu\Protocol\Server\Renew as ServerRenew;
//停止充电
use Wormhole\Protocols\QianNiu\Protocol\Server\StopCharge as ServerStopCharge;





class EvseController extends Controller
{
    public function __construct()
    {

    }

    public function checkHeartbeat($id){

        Log::debug(__NAMESPACE__ .  "/" . __CLASS__."/" . __FUNCTION__ . "@" . __LINE__ . " 心跳监控START id : $id ");

        //查询当前桩的心跳周期
        $hearbeat = Evse::where('id',$id)->first();
        //$heartbeatCycle = $hearbeat->heartbeat_cycle * 60;
        Log::debug(__NAMESPACE__ .  "/" . __CLASS__."/" . __FUNCTION__ . "@" . __LINE__ . " 当前时间: ".time()."上次心跳时间:".strtotime($hearbeat->last_update_status_time)."心跳周期:".$hearbeat->heartbeat_cycle);
        //如果心跳没在心跳周期时间上报,则判断掉线
        if((time() - strtotime($hearbeat->last_update_status_time)) < 180){ //$hearbeat->heartbeat_cycle * 60
            $hearbeat->online_status = 1;
            $hearbeat->save();
            Log::debug(__NAMESPACE__ .  "/" . __CLASS__."/" . __FUNCTION__ . "@" . __LINE__ . " 心跳正常, END ");
        }else{
            //告诉monitor掉线
            Log::debug(__NAMESPACE__ .  "/" . __CLASS__."/" . __FUNCTION__ . "@" . __LINE__ . " 断线 code: ".$hearbeat->code);
            $code = [$hearbeat->code];
            $monitorCodes = MonitorServer::offline($code);
            if(empty($monitorCodes)){
                Log::debug(__NAMESPACE__ .  "/" . __CLASS__."/" . __FUNCTION__ . "@" . __LINE__ . " 断线调用monitor失败, END ");
            }
            //更新桩状态字段,充电桩掉线
            $hearbeat->online_status = 0;
            $hearbeat->save();
            Log::debug(__NAMESPACE__ .  "/" . __CLASS__."/" . __FUNCTION__ . "@" . __LINE__ . " END 心跳超时");

        }


        return true;


    }



    public function checkSetParameter($parameterName, $parameter, $code){

        Log::debug(__NAMESPACE__ .  "/" . __CLASS__."/" . __FUNCTION__ . "@" . __LINE__ . " 设置参数检查start ");
        //通过code找到设置参数结果
        $evse = Evse::where('code',$code)->first();
        $requestResult = $evse->request_result;
        $requestResult = json_decode($requestResult);
        //如果是设置域名和端口号
        if(is_array($parameterName) && is_array($parameter)){
            $p1 = $parameterName[0];
            $p2 = $parameterName[1];
            if($requestResult->$p1 == 1 && $requestResult->$p2 == 1){
                Log::debug(__NAMESPACE__ .  "/" . __CLASS__."/" . __FUNCTION__ . "@" . __LINE__ . " 设置参数成功 ");
                $evse->$p1 = $parameter[0];
                $evse->$p2 = $parameter[1];
                $requestResult->$p1 = 0;
                $requestResult->$p2 = 0;
                $evse->request_result = json_encode($requestResult);
                $evse->save();

                //记录修改日志
                $log = ModifyInfoLog::create([
                    'before_info'=>$evse->$p1.' '.$evse->$p2,
                    'after_info'=>$parameter[0].' '.$parameter[1],
                    'remarks'=>'修改内容为域名和端口',
                    'modify_time'=>date('Y-m-d H:i:s', time())
                ]);
                Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 域名端口改变,记录日志结果 $log ");

                //设置成功调用montor
                return true;
            }
            //设置成功调用montor
            return false;
        }

        //设置参数
        if(is_array($parameter)){
            $evse = Evse::where('code',$code)->first();
            //如果收到响应
            if($requestResult->$parameterName == 1){
                Log::debug(__NAMESPACE__ .  "/" . __CLASS__."/" . __FUNCTION__ . "@" . __LINE__ . " 设置参数成功 ");
                $data = json_encode($parameter);
                $evse->parameter = $data;
                $requestResult->$parameterName = 0;
                $evse->request_result = json_encode($requestResult);
                $evse->save();

                //设置成功调用montor
                return true;
            }
            //设置失败调用montor
            return false;
        }

        //如果设置成功,更新响应参数
        if($requestResult->$parameterName == 1){
            Log::debug(__NAMESPACE__ .  "/" . __CLASS__."/" . __FUNCTION__ . "@" . __LINE__ . " 设置参数成功 ");
            $oldParameter = $evse->$parameterName;
            $evse->$parameterName = $parameter;
            $requestResult->$parameterName = 0;
            $evse->request_result = json_encode($requestResult);
            $evse->save();

            //记录修改日志
            $log = ModifyInfoLog::create([
                'before_info'=>$oldParameter,
                'after_info'=>$parameter,
                'remarks'=>'修改内容为心跳周期',
                'modify_time'=>date('Y-m-d H:i:s', time())
            ]);
            Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳改变,记录日志结果 $log ");

            //设置成功调用montor
            return true;
        }
        //设置失败调用montor
        return false;

    }


    //检测启动续费停止是否收到桩响应
    public function response($code, $orderId, $type, $workeId, $frame){

        Log::debug(__NAMESPACE__ .  "/" . __CLASS__."/" . __FUNCTION__ . "@" . __LINE__ . " 监控启动续费或者退费start ");

        $condition = [
            ['code', '=', $code],
            ['order_id', '=', $orderId]
        ];

        $port = Port::where($condition)->first();//firstOrFail
        if(empty($port)){
            Log::debug(__NAMESPACE__ .  "/" . __CLASS__."/" . __FUNCTION__ . "@" . __LINE__ . " 监控启动续费或者退费没有找到相应数据 code:$code, orderId:$orderId ");
            return false;
        }

        $isResponse = $port->is_response;
        if($isResponse){
            Log::debug(__NAMESPACE__ .  "/" . __CLASS__."/" . __FUNCTION__ . "@" . __LINE__ . " 收到桩响应 ");
            return true;
        }else{

            //查询下发次数,如果小于三次则继续下发,否则停止下发,调用monitor接口
            $operationTime = $port->operation_time;
            if($operationTime < 3){
                $sendResult  = EventsApi::sendMsg($workeId,base64_decode($frame));
                //下发次数加1
                $port->operation_time = ++$operationTime;
                $port->save();
                Log::debug(__NAMESPACE__ .  "/" . __CLASS__."/" . __FUNCTION__ . "@" . __LINE__ . " 监控启动续费或者退费，未达到3次,继续下发, 第 $operationTime 次下发, 下发结果sendResult:$sendResult ");

                $job = (new CheckResponse($code, $orderId, $type, $workeId, $frame))
                    ->onQueue(env("APP_KEY"))
                    ->delay(Carbon::now()->addSeconds(3));
                dispatch($job);
            }else{
                //调用monitor接口,启动续费或者退费失败
                if($type == 1){ //启动
                    Log::debug(__NAMESPACE__ .  "/" . __CLASS__."/" . __FUNCTION__ . "@" . __LINE__ . " 启动未收到响应 ");

                }elseif($type == 2){ //续费
                    Log::debug(__NAMESPACE__ .  "/" . __CLASS__."/" . __FUNCTION__ . "@" . __LINE__ . " 续费未收到响应 ");

                }elseif($type == 3){ //停止
                    Log::debug(__NAMESPACE__ .  "/" . __CLASS__."/" . __FUNCTION__ . "@" . __LINE__ . " 停止未收到响应 ");

                }
            }
        }

    }



    //***************************************桩主动上报***********************************//

    //签到
    public function signReport($code, $num, $device_identification, $heabeat_cycle, $worker_id, $version){

        //枪口列表
        $portArr = [];
        Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到,处理数据start ");
        //桩是否存在
        $evse = Evse::where("code",$code)->first(); //firstOrFail
        //找不到添加桩和枪
        if(empty($evse)){

            for ($i=0;$i<$num;$i++){
                $portArr[] = $i;
            }
            //从moniotr获取monitorCode
            $monitorCodes = MonitorServer::deviceOnline($code, $num, $portArr, $version);
            if(!is_array($monitorCodes)){
                Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到,添加桩和枪,调用monitor失败 code:$code ");
                return false;
            }
            //添加桩信息
            //Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 未找到桩 ");
            Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到,处理数据 device_identification:$device_identification  ");
            //初始化request_result
            $data = ['heartbeat_cycle'=>0, 'domain_name'=>0, 'port_number'=>0, 'parameter'=>0, 'device_id'=>0];
            $data = json_encode($data);
            $evse = Evse::create([
                'code'=>$code,
                'worker_id'=>$worker_id,//$this->workerId,
                'protocol_name'=> \Wormhole\Protocols\QianNiu\Protocol::NAME,
                'channel_num'=>$num,
                'online_status'=>1,
                'last_update_status_time'=>Carbon::now(),
                'request_result'=>$data,
                'identification_number'=>$device_identification,
                'heartbeat_cycle'=>$heabeat_cycle,
                //'version'=>$version


            ]);
            //如果创建桩失败,返回false
            if(empty($evse)){
                Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 创建桩失败,code:$code ");
                return false;
            }

            //添加枪信息
            for($i=0;$i<$num;$i++){

                $port = Port::create([
                    'evse_id'=>$evse->id,
                    'code'=>$code,
                    'monitor_code'=>$monitorCodes[$i],
                    'port_number'=>$i
                ]);
                //如果通道创建失败,返回false
                if(empty($port)){
                    Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 创建通道 $i 失败 ");
                    return false;
                }
            }
            Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到,添加桩和枪,调用monitor成功 code:$code ");

            //应答充电桩
            $this->signResponse($code, $worker_id);

            return true;

        }else{

            //更新桩信息
            $evse->worker_id = $worker_id;//$this->workerId;
            $evse->channel_num = $num;
            $evse->identification_number = $device_identification;
            $evse->heartbeat_cycle = $heabeat_cycle;
            $evse->online_status = 1; //在线
            //$evse->version = $version; //版本号
            $evse->save();

            //如果枪口数量与表中不一致,进行修改
            $count = Port::where('code', $code)->count();
            //如果枪口数量增加，则添加枪口
            if($num > $count){
                //增加的个数
                $addNum = $num - $count;
                //判断有没有软删除的,有的先恢复
                $res = Port::onlyTrashed()->where('code','1')->orderBy('port_number', 'asc')->get();
                $softNum = count($res);
                $addPort = 0;
                $increase = 0;
                //如果有软删除数据
                if($softNum > 0){

                    //如果软删除个数大于增加的个数或者相同
                    if($addNum <= $softNum){
                        for($i=0;$i<$addNum;$i++){
                            $res[$i]->restore();
                        }
                    }elseif($addNum > $softNum){ //如果软删除的小于增加的
                        //后面还要添加的个数
                        $addPort = $addNum - $softNum;
                        for($i=0;$i<$addNum;$i++){
                            $res[$i]->restore();
                        }
                        //增加到了多少
                        $increase = $res[$i]->port_number+1;
                    }

                }

                for($i=$count;$i<$num;$i++){
                    $portArr[] = $i;
                }
                $monitorCodes = MonitorServer::deviceOnline($code, $num, $portArr, $version);
                if(!is_array($monitorCodes)){
                    Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到,枪口增加,调用monitor失败 ");
                    return false;
                }

                if(!empty($addPort)){

                    //添加枪信息
                    for($i=$increase;$i<$addPort;$i++){

                        $port = Port::create([
                            'evse_id'=>$evse->id,
                            'code'=>$code,
                            'port_number'=>$i,
                            'monitor_code'=>$monitorCodes[$softNum++],
                        ]);
                        //添加枪失败
                        if(empty($port)){
                            Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 更新桩信息,创建通道 $i 失败 ");
                            return false;
                        }
                    }
                    Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到,枪口增加,调用monitorc成功 ");

                }


                //应答充电桩
                $this->signResponse($code, $worker_id);

                return true;
            }elseif ($num < $count){ //如果枪口数量减少,软删除多余枪口

                //找到多余的枪口
                $condition = [
                    ['code', '=', $code],
                    ['port_number', '>=', $num]
                ];
                $port = Port::where($condition)->get();
                $count = count($port);
                for ($i=0;$i<$count;$i++){
                    $port[$i]->delete();
                    $portArr[] = $port[$i]->port_number;
                }

                //减少的枪口列表
//                 for($i=$num;$i<$count;$i++){
//                     $portArr[] = $i;
//                 }
                $monitorCodes = MonitorServer::deviceOnline($code, $num, $portArr, $version);

                if(is_array($monitorCodes)){

                    //应答充电桩
                    //$this->signResponse($code, $worker_id);

                    Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到,枪口减少,调用monitor成功 ");
                    return true;
                }else{
                    Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到,枪口减少,调用monitor失败 ");
                    return false;
                }
            }

        }

        //更新桩数据
        $evse->worker_id = $worker_id;//$this->workerId;
        $evse->online_status = 1; //在线
        //$evse->version = $version; //版本号
        $res = $evse->save();
        if(empty($res)){
            Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到,更新数据失败 ");
            return false;
        }

        //应答充电桩
        $this->signResponse($code, $worker_id);

        //调用monitor接口
        $monitorCodes = MonitorServer::deviceOnline($code, $num, array(), $version);

        //如果调用monitor成功,应答充电桩
        if($monitorCodes){
            Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到,枪口数量不变,调用monitor成功 ");
            //应答充电桩
            //$this->signResponse($code, $worker_id);
            return true;
        }else{
            Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到,枪口数量不变,调用monitor失败 ");
            return false;
        }

    }


    //应答充电桩
    public function signResponse($code, $worker_id){

        //如果接收到数据,应答充电桩
        //从redis中取出流水号,如果没有设置为1
        $serialNumber = $this->serial_num($code);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到,流水号serialNumber：$serialNumber ");

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到应答桩start " . Carbon::now());
        $date = date('YmdHis', time());//当前时间
        $date = substr($date, 2);
        $sign = new ServerSign();
        $sign->code($code);
        $sign->serial_number(intval($serialNumber));
        $sign->result(1);
        $sign->date(intval($date));
        $frame = strval($sign);
        $sendResult  = EventsApi::sendMsg($worker_id,$frame);//self::$client_id
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到响应充电桩结果,sendResult:$sendResult " . Carbon::now());

        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到,响应充电桩 结果 $sendResult, 帧frame: ".bin2hex($frame) );


    }


    public function heartBeatReport($dataArray, $evse_num, $clientID){

        //如果桩不在线,先做上线处理
        Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳start ");
        $status = [];
        foreach ($dataArray as $k=>$data) {

            //查找是否有此桩
            $evse = Evse::where("code", $data['code'])->first();
            if(empty($evse)){
                Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳,处理数据,桩编号不存在 code: ".$data['code']);
                return false;
            }
            if (!empty($evse)) {

                //更新此桩数据
                //$evse->worker_id = $evse->workerId;
                $evse->channel_num = $data['num'];
                $evse->signal_intensity = $data['signal'] > 31 ? 0 : $data['signal']; //如果信号强度大于31,则信号故障,则设置为0
                $evse->last_update_status_time = Carbon::now();
                $evse_res = $evse->save();
                if(empty($evse_res)){
                    Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳,处理数据,更新桩数据失败 code:".$data['code']);
                }

            }

            ////monitor那边1是空闲，2是充电
            $statu = array(1,2);
            //更新枪数据
            for ($i = 0; $i < $data['num']; $i++) {
                $condition = [
                    ['code', '=', $data['code']],
                    ['port_number', '=', $i]
                ];
                $status[$i]['device_no'] = $data['code'];
                $status[$i]['gun_num'] = $i;
                $status[$i]['work_status'] = $statu[$data['status']['worke_state'][$i]];
                $port = Port::where($condition)->first();
                if (!empty($port)) {
                    //如果状态上报的是空闲，表中的状态不是空闲则更改为空闲状态
                    if($data['status']['worke_state'][$i] == 0 && $port->work_status != 0){
                        $port->work_status = $data['status']['worke_state'][$i];
                        $status[$i]['work_status'] = $statu[$data['status']['worke_state'][$i]];
                    }

                    //如果状态上报的是充电中，表中的状态不是充电则更改为充电状态
//                    if($data['status']['worke_state'][$i] == 1 && $port->work_status != 2){
//                        $port->work_status = $data['status']['worke_state'][$i];
//                        $status[$i]['work_status'] = $statu[$data['status']['worke_state'][$i]];
//                    }

                    //判断是否故障
                    if($data['status']['fuses'][$i] == 1 || $data['status']['overcurrent'][$i] == 1){
                        //心跳工作状态故障
                        $status[$i]['work_status'] = 3;//故障

                        //如果故障,判断是否已经给过monitor
                        $fault = Redis::get($data['code'].':fault');
                        if($fault != 1){
                            Redis::set($data['code'].':fault',1);
                            MonitorServer::add_device_error_log($data['code'], $i, 3);//3为故障
                        }

                    }else{
                        //信号强度恢复正常,判断是否已经给过monitor
                        $fault = Redis::get($data['code'].':fault');
                        if($fault != 2){
                            Redis::set($data['code'].':fault',2);
                            MonitorServer::add_device_error_log($data['code'], $i, 1); //1为空闲
                        }

                    }


                    $port->is_fuse = $data['status']['fuses'][$i];
                    $port->is_flow = $data['status']['overcurrent'][$i];
                    $port->is_connect = $data['status']['connect'][$i];
                    $port->is_full = $data['status']['full'][$i];
                    $port->start_up = $data['status']['start_up'][$i];
                    $port->pull_out = $data['status']['pull_out'][$i];
                    $port->left_time = $status[$i]['remaining_time'] = $data['left_time'][$i];
                    $port->current = $status[$i]['current'] = $data['current'][$i];
                    $port_res = $port->save();
                    if(empty($port_res)){
                        Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳,处理数据,更新枪数据失败 code:".$data['code']."port: $i ");
                    }
                }
            }

            //心跳应答
            $this->hearbeatAnswer($dataArray, $evse_num, $clientID);

            //调用monitor接口
            $monitorCodes = MonitorServer::hearbeat($status);
            if($monitorCodes){
                Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳调用monitor成功 ");
            }else{
                Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳调用monitor失败 ");
            }

            //心跳周期
            $heartbeatCycle = $evse->heartbeat_cycle * 60 + 60;
            //使用队列,检查心跳
            $job = (new CheckHeartbeat($port->evse_id))
                ->onQueue(env("APP_KEY"))
                ->delay(Carbon::now()->addSeconds($heartbeatCycle));
            dispatch($job);




        }



    }

    //心跳应答
    public function hearbeatAnswer($dataArray, $evse_num, $clientID){


        $frames = '';
        //应答充电桩
        for($i=0;$i<$evse_num;$i++){
            $hearbeat = new ServerHeartbeat();
            $hearbeat->code($dataArray[$i]['code']);
            $frame = strval($hearbeat);
            $frames = $frames.$frame;
        }
        $sendResult  = EventsApi::sendMsg($clientID,$frames);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " frames: ".bin2hex($frames));
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 多个心跳应答桩sendResult:$sendResult " . Carbon::now());



    }



    //自动停止上报
    public function autoStopReport($code, $order_number, $start_type, $left_time, $stop_reason){


        //获取workeId
        $evse = Evse::where("code",$code)->first(); //firstOrFail
        if(empty($evse)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 自动停止,应答充电桩未找到clientID code:$code, order_number:$order_number, left_time:$left_time, stop_reason:$stop_reason " . Carbon::now());
            return false;
        }
        $workeId = $evse->worker_id;

        //应答充电桩组装数据
        $automaticStop = new ServerAutomaticStop();
        $automaticStop->code($code);
        $automaticStop->channel_number(intval($order_number));
        $automaticStop->result(1);
        $frame = strval($automaticStop);

        //应答充电桩
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 
            自动停止,应答桩结果, sendResult:$sendResult   " . Carbon::now());


        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 桩自动停止,应答桩 frame: ".bin2hex($frame));
        //订单id为空,则直接返回
        if(empty($order_number)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 自动停止, 桩上报的订单id为空 order_number:$order_number " . Carbon::now());
            return false;
        }


        //通过code和order_id获取启动信息
        $condition = [
            ['code', '=', $code],
            ['order_id', '=', $order_number]
        ];
        $port = Port::where($condition)->first();
        if(empty($port)){
            //应答充电桩
            $sendResult  = EventsApi::sendMsg($workeId,$frame);
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 自动停止,未找到对应枪口或者已经接收到一次 code:$code, order_number:$order_number,  sendResult:$sendResult " . Carbon::now());
            return true;
        }

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 
            自动停止,, code:$code, port:$port->port_number " . Carbon::now());

        $monitorOrderId = $port->monitor_order_id;
        $chargeArgs = $port->charge_args;
        $chargeOrderMapping = ChargeOrderMapping::where('monitor_order_id', $monitorOrderId)->first();

        //生成充电记录
        $res = ChargeRecords::create([
            'code'=>$port->code,
            'port_number'=>$port->port_number,
            'monitor_order_id'=>$port->monitor_order_id,
            'order_id'=>$port->order_id,
            'charge_type'=>$port->charge_type,
            'charge_args'=>$port->charge_args,
            'start_time'=>$port->start_time,
            'end_time'=>Carbon::now(),
            'stop_reason'=>$stop_reason, //停止原因添加
            'start_type'=>$start_type,
            'left_time'=>$left_time,
            'charge_records_time'=>Carbon::now(),

        ]);
        //如果自动停止未生成充电记录
        if(empty($res)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 自动停止,未生成充电记录 " . Carbon::now());
            return false;
        }
        //更改状态,清空数据
        $port->monitor_order_id = 0;
        $port->order_id = 0;
        $port->work_status = 0; //状态,空闲中
        $port->is_fuse = 0;
        $port->is_flow = 0;
        $port->is_connect = 0;
        $port->is_full = 0;
        $port->start_up = 0;
        $port->pull_out = 0;
        $port->channel_status = '';
        $port->charged_power = 0;
        $port->charge_type = 0;
        $port->charge_args = 0;
        $port->renew = 0;
        $port->left_time = 0;
        $port->current = 0;
        $row = $port->save();

        //更新charge_order_mapping状态
        $chargeOrderMapping->is_success = 3; //停止成功
        $chargeOrderMapping->stop_reason = $stop_reason;
        $chargeOrderMapping->save();

        //如果未清空数据,返回false
        if(empty($row)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 自动停止,未清空数据 " . Carbon::now());
            return false;
        }




        //停止成功,调用monitor接口
        $job = (new AutoStopCharge($monitorOrderId, $left_time, $chargeArgs))
            ->onQueue(env("APP_KEY"));
        dispatch($job);


        return true;

    }



    //日结
    public function turnoverReport($code, $meter_number, $date_info, $electricity, $total_electricity, $coins_number, $card_amount, $card_time){

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 日结处理数据start ");
        //将数组转换为字符串
        $electricity_json = json_encode($electricity);


        //处理日期
        //$frontDate = date("Y-m-d",strtotime("-1 day"));
        $date = '20'.$date_info;
        $date = date('Y-m-d', strtotime($date));

        //获得上一次上报的数据
        $turnoverData = Turnover::where([['stat_date', '<', $date],['code', $code]])
            ->orderBy('stat_date', 'desc')
            ->first();
        //是否找到数据
        $coinNumber = 0;
        $cardFree = 0;
        $cardTime = 0;
        if(!empty($turnoverData)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 日结,获取到上一次上报数据 ");
            $coinNumber = $turnoverData->coin_number;
            $cardFree = $turnoverData->card_free;
            $cardTime = $turnoverData->card_time;
        }

        //是否有当前上报日期数据
        $condition = [
            ['code', '=', $code],
            ['stat_date', '=', $date]
        ];
        $turnover = Turnover::where($condition)->first();

        //如果是空则创建,否则更新
        if(empty($turnover)){
            $result = Turnover::create([
                'code'=>$code,
                'electricity_meter_number'=>$meter_number,
                'stat_date'=>$date,
                'charged_power_time'=>$electricity_json,
                'charged_power'=>$total_electricity,
                'coin_number'=>$coins_number - $coinNumber,
                'card_free'=>$card_amount - $cardFree,
                'card_time'=>$card_time - $cardTime

            ]);
            //如果创建失败,返回false
            if(empty($result)){
                Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 日结,创建数据失败 " . date('Y-m-d H:i:s', time()));
                return false;
            }


        }else{
            $turnover->electricity_meter_number = $meter_number;
            $turnover->stat_date = $date;
            $turnover->charged_power_time = $electricity_json;
            $turnover->charged_power = $total_electricity;
            $turnover->coin_number = $coins_number - $coinNumber;
            $turnover->card_free = $card_amount - $cardFree;
            $turnover->card_time = $card_time - $cardTime;

            $result = $turnover->save();
        }


        //如果更新失败,返回false
        if(empty($result)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 日结,更新数据失败 " . date('Y-m-d H:i:s', time()));
            return false;
        }

        //计算出一天的电量
        $electricity_num = 0;
        foreach ($electricity as $v){

            $electricity_num = $v + $electricity_num;

        }

        //获取workeId
        $evse = Evse::where("code",$code)->first(); //firstOrFail
        $workeId = $evse->worker_id;

        //如果更新数据成功,应答充电桩
        $time = date('YmdHis', time());
        $time = substr($time, 2);
        $report = new ServerReport();
        $report->code(intval($code));
        $report->date(intval($time));
        $report->receive_date(intval($date_info));
        $report->result(1);
        $frame = strval($report);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "frame：$frame");
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 
            frame:".bin2hex($frame)."
            日结应答充电桩结果:$sendResult " . Carbon::now());

        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 日结,应答桩 frame: ".bin2hex($frame) );




        //调用monitor,如果monitor返回失败则一直上报
        //停止成功,调用monitor接口
        $job = (new Report($code, $coins_number, $card_amount, $electricity_num, $total_electricity, $date))
            ->onQueue(env("APP_KEY"));
        dispatch($job);


    }











    //************************************服务器下发**************************************************//

    //下发启动充电
    public function startChargeSend($monitorOrderId, $monitorCode, $chargeType, $chargeArgs, $orderId){

        //更新枪数据
        $port = Port::where('monitor_code',$monitorCode)->first();//firstOrFail
        if(empty($port)){
            //启动失败调用monitor接口
            $result = MonitorServer::start_charge_failed_response($monitorOrderId);
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 启动充电,未找到枪 monitorCode:$monitorCode, monitorOrderId:$monitorOrderId, orderId:$orderId ");
            return false;
        }
        $channelNumber = $port->port_number; //枪口号
        $code = $port->code;//桩编号
        $workeId = $port->evse->worker_id;  //workId


        $port->monitor_order_id = $monitorOrderId;
        $port->order_id = $orderId;
        $port->work_status = 1; //状态,启动中
        $port->start_time = Carbon::now();
        $port->charge_type = $chargeType;
        $port->charge_args = $chargeArgs;
        $port->is_response = 0;
        $port->operation_time = 0;
        $res = $port->save();
        //如果没有更新成功
        if(empty($res)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 启动充电下发,更新数据库失败 ");
            return false;
        }
        //添加orderMap数据
        $orderRes = ChargeOrderMapping::create([
            'evse_id'=>$port->evse_id,
            'port_id'=>$port->id,
            'code'=>$port->code,
            'monitor_order_id'=>$port->monitor_order_id,
            'order_id'=>$port->order_id,
            'charge_type'=>$port->charge_type,
            'charge_args'=>$port->charge_args

        ]);
        //订单映射表是否创建成功
        if(empty($orderRes)){
            Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 启动充电,订单映射表创建结果 res:$res ");
            return false;
        }


        //获取workeId
        //$evse = Evse::where("id",$evseId)->first(); //firstOrFail
        //$serialNumber = $evse->serial_number; //流水号

        //从redis中取出流水号,如果没有设置为0
        $serialNumber = $this->serial_num($code);

        //$workeId = $evse->worker_id;
        if(empty($workeId)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 启动充电,workeId为空 ");
            return false;
        }

        //组装启动充电帧
        $startCharge = new ServerStartCharge();
        $startCharge->code(intval($code));
        $startCharge->serial_number(intval($serialNumber));
        $startCharge->order_number(intval($orderId));
        $startCharge->channel_number(intval($channelNumber));
        $startCharge->charge_mode(intval($chargeType));
        $startCharge->charge_parameter(intval($chargeArgs));


        //下发启动充电
        $frame = strval($startCharge);
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 启动充电下发帧,frame: ".bin2hex($frame) );
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "frame：$frame");
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 
            frame:".bin2hex($frame)."
            启动充电结果:$sendResult " . date('Y-m-d H:i:s', time()));

        //记录log
        $fiel_data = " 启动充电,时间: ".Carbon::now().PHP_EOL." 启动充电,参数 monitorOrderId:$monitorOrderId, monitorCode:$monitorCode, chargeType:$chargeType, chargeArgs:$chargeArgs, order_id:$orderId ".PHP_EOL."启动充电帧: $frame, 下发帧结果,sendResult: $sendResult";
        $redis_data = "启动充电下发".json_encode(array('monitorOrderId'=>$monitorOrderId, 'monitorCode'=>$monitorCode, 'chargeType'=>$chargeType, 'chargeArgs'=>$chargeArgs, 'orderId'=>$orderId)).'-'.bin2hex($frame).'-'.Carbon::now().'+';
        $this->record_log($code, $fiel_data, $redis_data);

        //类型1启动2续费3停止
        $type = 1;
        $frame = base64_encode($frame);

        //下发失败调用monitor
        if(empty($sendResult)){
            //启动失败调用monitor接口
            $result = MonitorServer::start_charge_failed_response($monitorOrderId);
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 启动充电,下发失败 monitorCode:$monitorCode, monitorOrderId:$monitorOrderId, orderId:$orderId ");
            return false;
        }

        //如果3秒内没有收到响应,则重发,最多三次
        $job = (new CheckResponse($code, $orderId, $type, $workeId, $frame))
            ->onQueue(env("APP_KEY"))
            ->delay(Carbon::now()->addSeconds(7));
            dispatch($job);

    }


    //续费下发
    public function renewSend($monitorOrderId, $monitorCode, $chargeType, $chargeArgs, $orderId){


        //通过订单号找到code和port_number
        $port = Port::where('monitor_code',$monitorCode)->first();//firstOrFail

        if(empty($port)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 续费,未找到枪 monitorCode:$monitorCode ");
            return false;
        }

        $channelNumber = $port->port_number; //枪口号
        $code = $port->code;                  //桩编号
        $workeId = $port->evse->worker_id;  //workId

        //worke_id是空,调用monitor
        if(empty($workeId)){
            //续费未找到worke_id调用monitor接口
            $result = MonitorServer::continue_charge_failed_response($monitorOrderId);
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 续费,workeId为空 ");
            return false;
        }



        //临时存储续费monitor订单号,收到续费响应后,把此单号给monitor
        Redis::set("$code.$channelNumber",$monitorOrderId);

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " oederId:$orderId ");

        //存储续费值
        $port->renew = $chargeArgs;
        $port->is_response = 0;
        $port->operation_time = 0;
        $port->save();

        //添加orderMap数据
        ChargeOrderMapping::create([
            'evse_id'=>$port->evse_id,
            'port_id'=>$port->id,
            'code'=>$port->code,
            'monitor_order_id'=>$monitorOrderId,
            'order_id'=>$port->order_id,
            'charge_type'=>$chargeType,
            'charge_args'=>$chargeArgs,

        ]);

        //从redis中取出流水号,如果没有设置为1
        $serialNumber = $this->serial_num($code);


        //组装续费帧
        $renew = new ServerRenew();
        $renew->code(intval($code));
        $renew->serial_number(intval($serialNumber));
        $renew->order_number(intval($orderId));
        $renew->channel_number(intval($channelNumber));
        $renew->charge_mode(intval($chargeType));
        $renew->charge_parameter(intval($chargeArgs));

        //续费下发
        $frame = strval($renew);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "frame：$frame");
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 续费下发帧,frame: ".bin2hex($frame) );
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 
            frame:".bin2hex($frame)."
            续费结果:$sendResult " . date('Y-m-d H:i:s', time()));

        //记录log
        $fiel_data = " 续费下发参数,monitorCode:$monitorCode, monitorOrderId:$monitorOrderId, chargeType:$chargeType, chargeArgs:$chargeArgs".PHP_EOL."frame: ".bin2hex($frame).'时间:'.Carbon::now();
        $redis_data = " 续费下发".'-'.json_encode(array('monitorOrderId'=>$monitorOrderId, 'monitorCode'=>$monitorCode, 'chargeType'=>$chargeType, 'chargeArgs'=>$chargeArgs, 'orderId'=>$orderId)).'-'.bin2hex($frame).'-'.Carbon::now().'+';
        $this->record_log($code, $fiel_data, $redis_data);

        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 续费下发帧结果,sendResult: $sendResult" );

        //续费下发失败调用monitor
        if(empty($sendResult)){
            $result = MonitorServer::continue_charge_failed_response($monitorOrderId);
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 续费,下发失败 ");
        }

        //类型1启动2续费3停止
        $type = 2;
        $frame =  base64_encode($frame);
        //如果3秒内没有收到响应,则重发,最多三次
        $job = (new CheckResponse($code, $orderId, $type, $workeId, $frame))
            ->onQueue(env("APP_KEY"))
            ->delay(Carbon::now()->addSeconds(7));
        dispatch($job);


    }



    //停止充电下发
    public function stopChargeSend($monitorOrderId){

        //通过code和port_number找到order_id
        $port = Port::where('monitor_order_id', $monitorOrderId)->first();//firstOrFail
        //如果通过订单找不到响应数据
        if(empty($port)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "停止充电下发,未找到响应数据, monitorOrderId:$monitorOrderId");
            return false;
        }
        $orderId = $port->order_id;
        $code = $port->code;
        $channelNumber = $port->port_number;
        //更新状态
        $port->work_status = 4; //状态,停止中
        $port->is_response = 0;
        $port->operation_time = 0;
        $port->save();


        //获取workeId
        $evse = Evse::where("code",$code)->first(); //firstOrFail
        $workeId = $evse->worker_id;
        if(empty($workeId)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 停止充电,workeId为空 ");
            return false;
        }

        //从redis中取出流水号,如果没有设置为1
        $serialNumber = $this->serial_num($code);


        //组装停止充电帧
        $stop = new ServerStopCharge();
        $stop->code(intval($code));
        $stop->serial_number(intval($serialNumber));
        $stop->order_number(intval($orderId));
        $stop->channel_number(intval($channelNumber));

        //停止下发
        $frame = strval($stop);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "frame：$frame");
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 
            frame:".bin2hex($frame)."
            停止充电结果:$sendResult " . date('Y-m-d H:i:s', time()));


        //记录log
        $fiel_data = " 停止充电参数,monitorOrderId:$monitorOrderId ".PHP_EOL." frame: ".bin2hex($frame).PHP_EOL."  时间: ".Carbon::now();
        $redis_data = "停止充电:".'-'. json_encode(array('monitorOrderId'=>$monitorOrderId, 'code'=>$code, 'orderId'=>$orderId, 'channelNumber'=>$channelNumber, 'serialNumber'=>$serialNumber)) .'-'.bin2hex($frame).'-'.Carbon::now().'+';
        $this->record_log($code, $fiel_data, $redis_data);

        //下发失败调用monitor
        if(empty($sendResult)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "停止充电下发失败 sendResult:$sendResult");
            //停止失败,调用monitor接口
            $result = MonitorServer::stop_charge_failed_response($monitorOrderId);
            return false;
        }

        //类型1启动2续费3停止
        $type = 3;
        $frame =  base64_encode($frame);
        //如果3秒内没有收到响应,则重发,最多三次
        $job = (new CheckResponse($code, $orderId, $type, $workeId, $frame))
            ->onQueue(env("APP_KEY"))
            ->delay(Carbon::now()->addSeconds(7));
        dispatch($job);

    }






    //*****************************************桩响应*********************************************************//

    //启动充电响应
    public function startChargeResponse($code, $order_number, $result){

        $condition = [
            ['code', '=', $code],
            ['order_id', '=', $order_number]
        ];
        $port = Port::where($condition)->first();
        $monitorOrderId = $port->monitor_order_id;


        //没有找到响应数据,返回false
        if(empty($port)){
            //启动充电未找到数据调用monitor接口
            $result = MonitorServer::start_charge_failed_response($monitorOrderId);
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 
            启动充电收到响应,未找到响应数据,
            code:$code, order_number:$order_number, result:$result " . Carbon::now());
            return false;
        }
        //判断是否收到了一次响应,如果收到就不再接收
        $isResponse = $port->is_response;
        if($isResponse == 1){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 
            启动充电收到响应,已接收过一次,
            code:$code, order_number:$order_number, result:$result " . Carbon::now());
            return true;
        }
        $orderMap = ChargeOrderMapping::where([
            ['port_id',$port->id,],
            ['order_id',$port->order_id]
        ])->first();
        //如果启动成功，更新相应数据
        if($result){
            //$maxWaitTime = Config::get('failureReason.max_wait_time);
            //$startTime = $port->tart_time;
            //if($startTime+$maxWaitTime+10 <= time()){
            //$reason = Config::get('failureReason.start_overtime);
            //}
            //更改充电状态
            $port->work_status = 2; //状态,充电中
            $port->is_fuse = 0;
            $port->is_flow = 0;
            $port->is_connect = 0;
            $port->is_full = 0;
            $port->charged_power = 0;
            $port->left_time = 0;
            $port->current = 0;
            $port->is_response = 1; //收到响应
            $port->response_time = Carbon::now();//启动充电响应时间
            $port->save();

            //更改订单映射表状态
            if(!is_null($orderMap)){
                $orderMap->is_success = 1; //启动成功
                $orderMap->save();
            }

            //启动成功调用monitor接口
            $result = MonitorServer::start_charge_success_response($monitorOrderId);
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 启动充电成功,调用monitor结果 result:$result");

        }else{

            $port->work_status = 3; //状态,启动失败
            $port->is_response = 1; //收到响应
            $port->response_time = Carbon::now();//启动充电响应时间
            $port->save();

            //更改订单映射表状态
            if(!is_null($orderMap)){
                $orderMap->is_success = 0; //启动失败
                $orderMap->save();
            }

            //启动失败调用monitor接口
            $result = MonitorServer::start_charge_failed_response($monitorOrderId);
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 启动充电失败,调用monitor结果 result:$result");
        }

        return TRUE;



    }


    //续费响应
    public function renewResponse($code, $order_number, $result){

        $condition = [
            ['code', '=', $code],
            ['order_id', '=', $order_number]
        ];
        $port = Port::where($condition)->first();
        $monitorOrderId = $port->monitor_order_id;
        //如果找不到相应数据
        if(empty($port)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 续费响应成功,未找相应数据：
            code:$code, order_number:$order_number, result:$result ");
            return false;
        }
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 续费响应结果：$result ");
        $portNumber = $port->port_number;
        //获取续费时的monitor订单号
        $orderId = Redis::get("$code.$portNumber");
        if(empty($orderId)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 续费获取monitorOrderId是空, orderId:$orderId ");
            return false;
        }
        //判断是否收到了一次响应,如果收到就不再接收
        $isResponse = $port->is_response;
        if($isResponse == 1){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 
            续费收到响应,已接收过一次,
            code:$code, order_number:$order_number, result:$result " . Carbon::now());
            return true;
        }

        //如果续费成功
        if($result){

            //更新charge_args
            $renew = $port->renew;
            $chargeArgs = $port->charge_args;
            $port->charge_args = $renew + $chargeArgs;
            $port->renew = 0;
            $port->is_response = 1; //收到响应
            $port->save();

            //续费成功调用monitor接口
            $result = MonitorServer::continue_charge_success_response($orderId);
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 续费成功,调用monitor结果result:$result ");

        }else{
            //清空续费
            $port->renew = 0;
            $port->is_response = 1; //收到响应
            $port->save();
            //续费失败调用monitor接口
            $result = MonitorServer::continue_charge_failed_response($orderId);
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 续费失败,调用monitor结果result:$result ");

        }

        return true;


    }


    //停止充电响应
    public function stopChargeResponse($code, $order_number, $result, $left_time, $stop_time){

        //通过code和order_id获取启动信息
        $condition = [
            ['code', '=', $code],
            ['order_id', '=', $order_number]
        ];
        $port = Port::where($condition)->first();

        if(empty($port)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 
            停止充电收到响应,未找到数据
            code:$code, order_number:$order_number, result:$result " . Carbon::now());
            return false;
        }
        $chargeArgs = $port->charge_args;
        //判断是否收到了一次响应,如果收到就不再接收
        $isResponse = $port->is_response;
        if($isResponse == 1){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 
            停止充电收到响应,已接收过一次,
            code:$code, order_number:$order_number, result:$result " . Carbon::now());
            return true;
        }

        $monitorOrderId = $port->monitor_order_id;
        $chargeOrderMapping = ChargeOrderMapping::where('monitor_order_id', $monitorOrderId)->first();
        //如果未找到数据
        if(empty($chargeOrderMapping)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 停止收到响应, 未找到响应数据:monitorOrderId:$monitorOrderId ");
            return false;
        }
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 停止响应结果:result:$result ");
        //如果停止成功
        if($result){

            //生成充电记录
            $res = ChargeRecords::create([
                'code'=>$port->code,
                'port_number'=>$port->port_number,
                'monitor_order_id'=>$port->monitor_order_id,
                'order_id'=>$port->order_id,
                'charge_type'=>$port->charge_type,
                'charge_args'=>$port->charge_args,
                'start_time'=>$port->start_time,
                'end_time'=>Carbon::now(),
                //'stop_reason'=>$port->charge_args, 停止原因添加?
                'charged_power'=>$port->charge_args,
                'left_time'=>$left_time,
                'charge_records_time'=>Carbon::now(),

            ]);

            if(empty($res)){
                Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 停止响应成功,未保存充电记录:result:$result ");
                return false;
            }
            //更改状态,清空数据
            $port->work_status = 0; //状态,空闲中
            $port->is_fuse = 0;
            $port->is_flow = 0;
            $port->is_connect = 0;
            $port->is_full = 0;
            $port->charged_power = 0;
            $port->charge_type = 0;
            $port->charge_args = 0;
            $port->left_time = 0;
            $port->current = 0;
            $port->is_response = 1; //收到响应
            $port->monitor_order_id = 0;
            $port->order_id = 0;
            $port->save();

            //更新charge_order_mapping状态
            $chargeOrderMapping->is_success = 3; //停止成功
            $chargeOrderMapping->save();

            $sufficientTime = $chargeArgs - $left_time; //已充时间
            //停止成功,调用monitor接口
            $result = MonitorServer::stop_charge_success_response($monitorOrderId, $left_time, $sufficientTime);
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 停止充电成功,调用monitor结果result:$result ");
        }else{
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 收到停止响应, 停止失败:result:$result ");
            //停止失败,更新状态
            $port->work_status = 5; //状态,停止失败
            $port->is_response = 1; //收到响应
            $port->save();

            $chargeOrderMapping->is_success = 2; //停止失败
            $chargeOrderMapping->save();

            //停止失败,调用monitor接口
            $result = MonitorServer::stop_charge_failed_response($monitorOrderId);
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 停止充电失败,调用monitor结果result:$result ");
        }


    }


    //如果桩自动停止,monitor接受失败继续发送
    public function AutoStopCharge($monitorOrderId, $left_time, $chargeArgs){

        $result = MonitorServer::stop_charge_success_response($monitorOrderId, $left_time, $chargeArgs);

        if(empty($result)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 自动停止,调用monitor 失败,继续调用" . Carbon::now());

            //继续调用monitor
            $job = (new AutoStopCharge($monitorOrderId, $left_time, $chargeArgs))
                ->onQueue(env("APP_KEY"))
                ->delay(Carbon::now()->addSeconds(3));
            dispatch($job);
        }else{
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 自动停止,调用monitor 成功" . Carbon::now());
        }



    }



    //如果日结,monitor接受失败继续发送
    public function Report($code, $coins_number, $card_amount, $electricity_num, $total_electricity, $date){

        $res = MonitorServer::turnover($code, $coins_number, $card_amount, $electricity_num, $total_electricity, $date);
        //如果monitor接受失败,继续发送
        if(empty($res)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 日结失败,继续调用 " . Carbon::now());
            $job = (new Report($code, $coins_number, $card_amount, $electricity_num, $total_electricity, $date))
                ->onQueue(env("APP_KEY"));
            dispatch($job);
        }



    }




    //取出流水号
    private function serial_num($code){

        $serialNumber = Redis::get($code.':serial_number');
        if(empty($serialNumber)){
            $serialNumber = 1;
            Redis::set($code.':serial_number',$serialNumber);
        }else{
            Redis::set($code.':serial_number',++$serialNumber);
        }
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 流水号serialNumber：$serialNumber ");

        return $serialNumber;

    }




    //记录log,包括参数,帧,时间
    private function record_log($code, $fiel_data, $redis_data){

        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . $fiel_data );

        //记录log
        $parameter = $code.uniqid(mt_rand(),1);
        Redis::set($parameter,$redis_data,'EX',86400);

    }




}

