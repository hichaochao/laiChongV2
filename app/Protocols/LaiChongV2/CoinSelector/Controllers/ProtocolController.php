<?php
namespace Wormhole\Protocols\QianNiu\Controllers;

use Carbon\Carbon;
use Faker\Provider\Uuid;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Wormhole\Protocols\QianNiu\EventsApi;
use Wormhole\Protocols\QianNiu\Protocol;
use Wormhole\Protocols\MonitorServer;
use Wormhole\Protocols\QianNiu\Models\Evse;
use Wormhole\Protocols\QianNiu\Models\Port;
use Wormhole\Protocols\QianNiu\Models\ChargeOrderMapping;
use Wormhole\Protocols\QianNiu\Models\ChargeRecords;
use Wormhole\Protocols\QianNiu\Models\Turnover;
use Wormhole\Protocols\QianNiu\Models\ModifyInfoLog;
//心跳队列
use Wormhole\Protocols\QianNiu\Jobs\CheckHeartbeat;

use Wormhole\Protocols\CommonTools;

use Illuminate\Support\Facades\Redis;


/**
 * Created by PhpStorm.
 * User: lingf
 * Date: 2016-12-14
 * Time: 14:25
 */
class ProtocolController
{
    use CommonTools;
    protected $workerId;

    public function __construct($workerId='')
    {
        Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " START worker:$workerId");
        $this->workerId = $workerId;

    }

    //签到
    public function sign($code, $num, $device_identification){

        //枪口列表
        $portArr = [];
        Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到,处理数据start ");
        //判断是否有此桩和枪口数量手否一致
        $evse = Evse::where("code",$code)->first(); //firstOrFail
        //找不到添加桩和枪
        if(empty($evse)){

            for ($i=0;$i<$num;$i++){
                $portArr[] = $i;
            }
            //从moniotr获取monitorCode
            $monitorCodes = MonitorServer::deviceOnline($code, $num, $portArr);
            if(!is_array($monitorCodes)){
                Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到,添加桩和枪,调用monitor失败 code:$code ");
                return false;
            }
           //添加桩信息
            //Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 未找到桩 ");
            //初始化request_result
            $data = ['heartbeat_cycle'=>0, 'domain_name'=>0, 'port_number'=>0, 'parameter'=>0, 'device_id'=>0];
            $data = json_encode($data);
            $evse = Evse::create([
                'code'=>$code,
                'worker_id'=>$this->workerId,
                'protocol_name'=> \Wormhole\Protocols\QuChong\Protocol::NAME,
                'channel_num'=>$num,
                'online_status'=>1,
                'last_update_status_time'=>Carbon::now(),
                'request_result'=>$data,
                'device_id'=>$device_identification
                

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
            return true;

        }else{

            //如果设备识别号不一致,则更新设备识别号,将心跳周期和参数初始化为默认值
            $oldIdentification = $evse->identification_number;
            if($oldIdentification != $device_identification){
                $evse->identification_number = $device_identification;
                $evse->heartbeat_cycle = 1;

                //将更改的信息添加到日志表中
                $log = ModifyInfoLog::create([
                    'before_info'=>$oldIdentification,
                    'after_info'=>$device_identification,
                    'remarks'=>'修改内容为设备识别号',
                    'modify_time'=>Carbon::now()
                ]);
                Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 设备识别号改变,记录日志结果 $log ");

            }

            //更新桩信息
            $evse->worker_id = $this->workerId;
            $evse->channel_num = $num;
            $evse->identification_number = $device_identification;
            $evse->online_status = 1; //在线
            $evse->save();

            //如果枪口数量与表中不一致,进行修改
            $count = Port::where('code', $code)->count();
            //如果枪口数量增加，则添加枪口
            if($num > $count){
                //判断有没有软删除的,有的先恢复

                for($i=$count;$i<$num;$i++){
                    $portArr[] = $i;
                }
                $monitorCodes = MonitorServer::deviceOnline($code, $num, $portArr);
                if(!is_array($monitorCodes)){
                    Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到,枪口增加,调用monitor失败 ");
                    return false;
                }
                //添加枪信息
                for($i=$count;$i<$num;$i++){

                    $port = Port::create([
                        'evse_id'=>$evse->id,
                        'code'=>$code,
                        'port_number'=>$i,
                        'monitor_code'=>$monitorCodes[$i],
                    ]);
                    //添加枪失败
                    if(empty($port)){
                        Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 更新桩信息,创建通道 $i 失败 ");
                        return false;
                    }
                }
                Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到,枪口增加,调用monitorc成功 ");
                return true;
            }elseif ($num < $count){ //如果枪口数量减少,软删除多余枪口

                //找到多余的枪口
                $condition = [
                    ['code', '=', $code],
                    ['port_number', '>=', $num]
                ];
                $port = Port::where($condition)->first();
                $count = count($port);
                for ($i=0;$i<$count;$i++){
                    $port[$i]->delete();
                }

                //减少的枪口列表
                for($i=$num;$i<$count;$i++){
                    $portArr[] = $i;
                }
                $monitorCodes = MonitorServer::deviceOnline($code, $num, $portArr);

                if(is_array($monitorCodes)){
                    Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到,枪口减少,调用monitor成功 ");
                    return true;
                }else{
                    Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到,枪口减少,调用monitor失败 ");
                    return false;
                }
            }

        }


        //调用monitor接口
        $monitorCodes = MonitorServer::deviceOnline($code, $num, array());
        if($monitorCodes){
            Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到,枪口数量不变,调用monitor成功 ");
            return true;
        }else{
            Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到,枪口数量不变,调用monitor失败 ");
            return false;
        }


    }

    //心跳
    public function hearbeat($dataArray, $evse_num){
        Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳start ");
        $status = [];
        //如果是一个帧
        if($evse_num == 1){
            $dataArray = array($dataArray);
        }
        foreach ($dataArray as $k=>$data) {

            //查找是否有此桩
            $evse = Evse::where("code", $data['code'])->first();
            if (!empty($evse)) {

                //更新此桩数据
                $evse->worker_id = $this->workerId;
                $evse->channel_num = $data['num'];
                $evse->signal_intensity = $data['signal'] > 31 ? 0 : $data['signal']; //如果信号强度大于31,则信号故障,则设置为0
                $evse->last_update_status_time = Carbon::now();
                $evse->save();

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
                    //告诉monitor
                }

                    //判断是否故障
                    if($data['status']['fuses'][$i] == 1 || $data['status']['overcurrent'][$i] == 1){
                        //如果故障,判断是否已经给过monitor
                        $fault = Redis::get('fault');
                        if($fault != 1){
                            Redis::set('fault',1);
                            //$fault = $data['fault'];
                            //$error_description = "桩故障,通道号为 $i";
                            //心跳工作状态故障
                            $status[$i]['work_status'] = 3;//故障
                            MonitorServer::add_device_error_log($data['code'], $i, 3);//3为故障
                        }

                    }else{
                        //信号强度恢复正常,判断是否已经给过monitor
                        $fault = Redis::get('fault');
                        if($fault != 2){
                            Redis::set('fault',2);
                            //$fault = $data['fault'];
                            //$error_description = "桩恢复,通道号为 $i";
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
                    $port->current = $status[$i]['current'] = $data['current'][$i] / 1000; //电流转换 A
                    $port->save();
                }
            }


            //心跳周期
            $heartbeatCycle = $evse->heartbeat_cycle * 60 + 20;
            //使用队列,检查心跳
            $job = (new CheckHeartbeat($port->evse_id))
                ->onQueue(env("APP_KEY"))
                ->delay(Carbon::now()->addSeconds($heartbeatCycle));
            dispatch($job);


            //调用monitor接口
            $monitorCodes = MonitorServer::hearbeat($status);
            if($monitorCodes){
                Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳调用monitor成功 ");
                return true;
            }else{
                Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳调用monitor失败 ");
                return true;
            }

        }


        
    }

    //启动充电
    public function startCharge($code, $order_number, $result){

        $condition = [
            ['code', '=', $code],
            ['order_id', '=', $order_number]
        ];
        $port = Port::where($condition)->first();
        $monitorOrderId = $port->monitor_order_id;
        //没有找到响应数据,返回false
        if(empty($port)){
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

    
    //续费
    public function renew($code, $order_number, $result){

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
    
    
    

    //停止充电
    public function stopCharge($code, $order_number, $result, $left_time, $stop_time){

        //通过code和order_id获取启动信息
        $condition = [
            ['code', '=', $code],
            ['order_id', '=', $order_number]
        ];
        $port = Port::where($condition)->first();
        $chargeArgs = $port->charge_args;
        if(empty($port)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 
            停止充电收到响应,
            code:$code, order_number:$order_number, result:$result " . Carbon::now());
            return false;
        }

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

            //停止成功,调用monitor接口
            $result = MonitorServer::stop_charge_success_response($monitorOrderId, $left_time, $chargeArgs);
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
    
    //自动停止
    public function autoStop($code, $order_number, $left_time, $stop_reason){

        //通过code和order_id获取启动信息
        $condition = [
            ['code', '=', $code],
            ['order_id', '=', $order_number]
        ];
        $port = Port::where($condition)->first();
        if(empty($port)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 自动停止,为找到对应枪口或者已经接收到一次 " . date('Y-m-d H:i:s', time()));
            return true;
        }
        $monitorOrderId = $port->monitor_order_id;
        $chargeArgs = $port->charge_args;
        $chargeOrderMapping = ChargeOrderMapping::where('monitor_order_id', $monitorOrderId)->first();
        //停止时间
        $stop_time = Carbon::now();
        //生成充电记录
        $res = ChargeRecords::create([
            'code'=>$port->code,
            'port_number'=>$port->port_number,
            'monitor_order_id'=>$port->monitor_order_id,
            'order_id'=>$port->order_id,
            'charge_type'=>$port->charge_type,
            'charge_args'=>$port->charge_args,
            'start_time'=>$port->start_time,
            'end_time'=>$stop_time,
            'stop_reason'=>$stop_reason, //停止原因添加
            //'charged_power'=>$port->charge_args,
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
        $result = MonitorServer::stop_charge_success_response($monitorOrderId, $left_time, $chargeArgs);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 自动停止,调用monitor结果 result:$result " . Carbon::now());
        return true;


    }
    
    
    //日结
    public function report($code, $meter_number, $date, $electricity, $total_electricity, $coins_number, $card_amount, $card_time){

        //处理日期
        //$frontDate = date("Y-m-d",strtotime("-1 day"));
        $date = '20'.$date;
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
                'charged_power_time'=>$electricity,
                'charged_power'=>$total_electricity,
                'coin_number'=>$coins_number - $coinNumber,
                'card_free'=>$card_amount - $cardFree,
                'card_time'=>$card_time - $cardTime

            ]);
            //如果创建失败,返回false
            if(empty($result)){
                Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 日结,创建数据失败 " . Carbon::now());
                return false;
            }
            return true;

        }
        $turnover->electricity_meter_number = $meter_number;
        $turnover->stat_date = $date;
        $turnover->charged_power_time = $electricity;
        $turnover->charged_power = $total_electricity;
        $turnover->coin_number = $coins_number - $coinNumber;
        $turnover->card_free = $card_amount - $cardFree;
        $turnover->card_time = $card_time - $cardTime;

        $result = $turnover->save();

        //如果更新失败,返回false
        if(empty($result)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 日结,更新数据失败 " . Carbon::now());
            return false;
        }

        //调用monitor接口

        return true;

    }
    
    

    //设置心跳周期
    public function setHearbeatCycle($code, $result){

        //如果设置成功,更新心跳周期结果
        if($result){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳设置响应成功 ");
            $evse = Evse::where("code",$code)->first(); //firstOrFail
            if(empty($evse)){
                Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳设置响应成功,未找到桩相应数据 ");
                return false;
            }
            $requestResult = $evse->request_result;
            $requestResult = json_decode($requestResult);
            $requestResult->heartbeat_cycle = 1;
            $evse->request_result = json_encode($requestResult);
            $evse->save();

        }


    }


    //设置服务器参数
    public function setServerInfo($code, $result){

        //如果设置成功,更新域名端口结果
        if($result){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 服务器信息设置响应成功 ");
            $evse = Evse::where("code",$code)->first(); //firstOrFail
            $requestResult = $evse->request_result;
            $requestResult = json_decode($requestResult);
            $requestResult->domain_name = 1;
            $requestResult->port_number = 1;
            $evse->request_result = json_encode($requestResult);
            $evse->save();
        }


    }


    //设置参数
    public function setParament($code, $result){

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 参数设置响应成功 start ");
        //如果设置成功,更新参数
        if($result){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 参数设置响应成功 code:$code ");
            $evse = Evse::where("code",$code)->first(); //firstOrFail
            $requestResult = $evse->request_result;
            $requestResult = json_decode($requestResult);
            $requestResult->parameter = 1;
            $evse->request_result = json_encode($requestResult);
            $evse->save();
        }


    }



    //清空营业额处理
    public function emptyTurnover($code, $coin_num, $card_cost, $card_time, $result){

        //如果设置成功,更新营业额
        if($result){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 清空营业额响应成功 ");
            //前一天日期
            $frontDate = date("Y-m-d",strtotime("-1 day"));
            $condition = [
                ['code', '=', $code],
                ['stat_date', '=', $frontDate]
            ];

            $turnover = Turnover::where($condition)->first(); //firstOrFail
            if(!empty($turnover)){
                $turnover->coin_number = $coin_num;
                $turnover->card_free = $card_cost;
                $turnover->card_time = $card_time;
                $turnover->save();
            }


        }


    }
    

    //查询心跳周期响应
    public function getHearbeat($code, $heartbeat_cycle){

        //如果设置成功,更新域名端口结果
        if($heartbeat_cycle){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询心跳周期响应成功 ");
            $evse = Evse::where("code",$code)->first(); //firstOrFail
            if(empty($evse)){
                Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询心跳周期响应成功,未找到相应桩数据 ");
                return false;
            }
            $evse->heartbeat_cycle = $heartbeat_cycle;
            $evse->save();
            return true;
        }
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询心跳周期响应成功,心跳周期有误 heartbeat_cycle:$heartbeat_cycle ");



    }
    
    

    //电表查表查询
    public function getMeter($code, $number, $meterDegree){
        //收到电表数据,保存到当天
        $frontDate = date("Y-m-d",time());
        $condition = [
            ['code', '=', $code],
            ['stat_date', '=', $frontDate]
        ];
        //如果收到数据则更新
        if(!empty($code) && !empty($number) && is_numeric($meterDegree)){

            $turnover = Turnover::where($condition)->first();
            //如果没有数据则创建
            if(empty($turnover)){
                $result = Turnover::create([
                    'code'=>$code,
                    'electricity_meter_number'=>$number,
                    'stat_date'=>$frontDate,
                    'charged_power_time'=>'',
                    'charged_power'=>$meterDegree / 100,
                    'coin_number'=>0,
                    'card_free'=>0,
                    'card_time'=>0

                ]);
                if(empty($result)){
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 电表查询更新成功,创建失败 result:$result ");
                    return false;
                }
                Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 电表查询更新成功,创建成功 result:$result ");
                return true;
            }
            //有数据则更新
            $turnover->electricity_meter_number = $number;
            $turnover->charged_power = $meterDegree / 100;
            $res = $turnover->save();
            if($res){
                Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 电表查询更新成功 res:$res ");
                return true;
            }

        }
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 电表查询更新失败 ");
        return false;
    }



    //营业额查询
    public function getTurnover($code, $coin_num, $card_cost, $card_time){

        //如果收到数据则更新
        if( !empty($code) &&  is_numeric($coin_num) &&  is_numeric($card_cost) && is_numeric($card_time) ){

            $turnover = Turnover::where("code",$code)->first();
            if(empty($turnover)){
                Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 营业额查询收到响应,未找到响应数据 ");
                return false;
            }
            $turnover->coin_number = $coin_num;
            $turnover->card_free = $card_cost;
            $turnover->card_time = $card_time;
            $res = $turnover->save();
            if($res){
                Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 营业额查询更新成功 res:$res ");
                return true;
            }

        }
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 营业额查询更新失败 ");
        return false;
        
    }


    //查询通道状态
    public function channelStatus($code, $channel_num, $order_number, $data, $equipment_status){

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询通道状态start ");
        $condition = [
            ['code', '=', $code],
            ['port_number', '=', $channel_num]
        ];
        $info = json_encode($data);

        //解析故障状态
        $len = 8;
        $status = [];
        $equipment_status = decbin($equipment_status);  //转换为二进制
        $data = str_pad($equipment_status, $len, "0", STR_PAD_LEFT);
        $data = strrev($data);
        for ($i=0;$i<$len;$i++){
            $status[] = substr($data, $i, 1);
        }
        $port = Port::where($condition)->first();
        if(empty($port)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询通道状态收到响应,为找到响应数据 ");
            return false;
        }
        $port->channel_status = $info;
        $port->work_status = $status[0];
        $port->is_fuse = $status[1];
        $port->is_flow = $status[2];
        $port->is_connect = $status[3];
        $port->is_full = $status[4];
        $port->start_up = $status[5];
        $port->pull_out = $status[6];

        $port->save();

        return true;

    }





    //查询参数
    public function getParameter($code, $channel_maximum_current,$full_judge,$clock,$disconnect,$power_base,$coin_rate,$card_rate){

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询参数响应成功 start ");

        $evse = Evse::where("code",$code)->first(); //firstOrFail
        if(empty($evse)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询参数响应成功,未找到数据 ");
            return false;
        }
        $data = ['channel_maximum_current'=>$channel_maximum_current, 'full_judge'=>$full_judge, 'clock'=>$clock, 'disconnect'=>$disconnect, 'power_base'=>$power_base,
        'coin_rate'=>$coin_rate, 'card_rate'=>$card_rate];
        $info = json_encode($data);
        $evse->parameter = $info;
        $evse->save();

        return true;

    }
    
    


}