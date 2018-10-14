<?php
namespace Wormhole\Protocols\QianNiu\Controllers\Api;
/**
 * Created by PhpStorm.
 * User: chao
 * Date: 2016-11-29
 * Time: 15:52
 */
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Wormhole\Http\Controllers\Api\BaseController;
use Wormhole\Protocols\Library\Tools;
use Illuminate\Support\Facades\Config;
use Illuminate\Http\Request;
use Wormhole\Protocols\Library;
use Wormhole\Protocols\QianNiu\Protocol\Evse\Heartbeat;
use Wormhole\Protocols\QianNiu\Protocol\PortInfo;
use Wormhole\Protocols\QianNiu\Protocol\Server\Renew;

//设置参数队列
use Wormhole\Protocols\QianNiu\Jobs\CheckSetParameter;
//检测启动续费停止是否收到桩响应
use Wormhole\Protocols\QianNiu\Jobs\CheckResponse;
//启动充电队列
use Wormhole\Protocols\QianNiu\Jobs\StartChargeSend;
//续费下发
use Wormhole\Protocols\QianNiu\Jobs\RenewSend;
//停止充电下发
use Wormhole\Protocols\QianNiu\Jobs\StopChargeSend;






use Wormhole\Validators\StartChargeValidator;
use Wormhole\Validators\RenewValidator;
use Wormhole\Validators\StopChargeValidator;

use Wormhole\Protocols\QianNiu\Models\Evse;
use Wormhole\Protocols\QianNiu\Models\Port;
use Wormhole\Protocols\QianNiu\Models\ChargeOrderMapping;
use Wormhole\Protocols\QianNiu\Models\Turnover;

use Wormhole\Protocols\QianNiu\Protocol\Frame;
use Wormhole\Protocols\QianNiu\EventsApi;
//启动充电
use Wormhole\Protocols\QianNiu\Protocol\Server\StartCharge as ServerStartCharge;

//续费
use Wormhole\Protocols\QianNiu\Protocol\Server\Renew as ServerRenew;

//停止充电
use Wormhole\Protocols\QianNiu\Protocol\Server\StopCharge as ServerStopCharge;

//心跳设置
use Wormhole\Protocols\QianNiu\Protocol\Server\SetHearbeat as ServerSetHearbeat;

//服务器信息设置
use Wormhole\Protocols\QianNiu\Protocol\Server\ServerInfo as ServerInfo;

//清空营业额
use Wormhole\Protocols\QianNiu\Protocol\Server\EmptyTurnover as ServerEmptyTurnover;

//设置参数
use Wormhole\Protocols\QianNiu\Protocol\Server\SetParameter as ServerSetParameter;

//设置ID
use Wormhole\Protocols\QianNiu\Protocol\Server\SetId as ServerSetId;

//查询ID
use Wormhole\Protocols\QianNiu\Protocol\Server\GetId as ServerGetId;

//心跳查询
use Wormhole\Protocols\QianNiu\Protocol\Server\GetHearbeat as ServerGetHeartbeat;

//电表抄表
use Wormhole\Protocols\QianNiu\Protocol\Server\ReadMeter as ServerReadMeter;

//营业额查询
use Wormhole\Protocols\QianNiu\Protocol\Server\GetTurnover as ServerGetTurnover;

//通道查询
use Wormhole\Protocols\QianNiu\Protocol\Server\GetChannelStatus as ServerGetChannelStatus;

//查询参数
use Wormhole\Protocols\QianNiu\Protocol\Server\GetParameter as ServerGetParameter;

//信号强度查询
use Wormhole\Protocols\QianNiu\Protocol\Server\Signal as ServerSignal;

//修改时间
use Wormhole\Protocols\QianNiu\Protocol\Server\SetTime as ServerSetTime;

//获取时间
use Wormhole\Protocols\QianNiu\Protocol\Server\GetDateTime as ServerGetDateTime;

//获取设备识别号
use Wormhole\Protocols\QianNiu\Protocol\Server\GetDeviceIdentification as ServerGetDeviceIdentification;

use Wormhole\Protocols\QianNiu\Protocol\Evse\Sign as EvseSign;
use Ramsey\Uuid\Uuid;
use Wormhole\Protocols\QianNiu\Protocol\Server\Sign as ServerSign;
use Wormhole\Protocols\QianNiu\Protocol\Evse\Report;
use Illuminate\Support\Facades\Redis;
use Wormhole\Protocols\MonitorServer;

use Wormhole\Protocols\Library\Log as Logger;



class EvseController extends BaseController
{

    public function test(){





//        $condition = [
//            ['code', '=', 123],
//            ['stat_date', '=', '2017-05-25']
//        ];
//        $turnover = Turnover::where($condition)->first();
//        var_dump($turnover);die;
        //找到多余的枪口
//        $condition = [
//            ['code', '=', '1'],
//            ['port_number', '>=', 5]
//        ];
//        $port = Port::where($condition)->get();//
//
//        var_dump($port[1]->delete());die;

        //5153 00 02 00001500 20 00 0102030405060708 0101 16 00 00 00 00 00 00 00 00 00 00 16
        //5153 00 02 00001500 20 00 0102030405060708 0101 01 16
        //组装帧
        $sign = new EvseSign();
        $sign->code(123456);
        $sign->num(10);
        $sign->device_identification(112233445566);
        $frame = strval($sign);
        var_dump(bin2hex($frame));
        die;

        //解析帧
        $sign2 = new EvseSign();
        $frame_load = $sign2($frame);
        var_dump($frame_load);

        //        $url = "http://int01.unicharge.net/api/pub/api/get_file/hash/iOS/6c32939376742f674609d783da845b79";
        //        $result = MonitorServer::post($url);
        //var_dump($result);
        //$file = app('path.storage').'/logs/13c23716';

//        Logger::log('13c23716', __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " startCharge start");
//        Logger::log('13c23716', __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 启动充电,桩不在线 online_status:1212 ");

        //$frame = 'aa430001580000007856341202110a0000000000000000000000000000000000000022000000000000001500010000000000000000000000000000080000000000fa93';
        //$frame =   'aa430001030000007856341202110a1900000000060000000000000000200000000000000000000000000000220000000020000000000000000000000000e1010966c3';
        //        $frame = 'aa4300012c0000007856341204110a00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000001c4baa4300012c0000007856341204110a00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000001c4b';
        //$frame = 'aa430001210000007856341202110a5600c300290f0000000000000000000000000000000000000000000000000000000000000000000000000000000000000000b60c';
        $frame = 'aa440001440000004e61bc0002111f0a00000000000000000000000000000000000000000000000000000000000000000000000000090001000000000000000000001f3baa440001450000007b00000002111f0a00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000004bc9';
        $frame = pack("H*",$frame);
        $heartbeat2 = new Heartbeat();
        $frame_load = $heartbeat2($frame);
        var_dump($frame_load[0]->info->data[7]['status']->getValue());
        //var_dump($aa = $frame_load->info->data[7]['status']->getValue());
        //var_dump($aa['worke_state'].'---'.$aa['fuses']);


//        $report = new Report();
//        $report->code(305419896);
//        $report->meter_number(123456);
//        $report->date(170516);
//        $report->electricity(12);
//        $report->total_electricity(57);
//        $report->coins_number(35);
//        $report->card_amount(73);
//        $report->card_time(100);
//        $frame = strval($report);
//        $fra = bin2hex($frame);
//        var_dump($fra);die;

        $frame = "aa870001080000007856341205111212121212121705157b007b007b007b007b007b007b007b007b007b007b007b007b007b007b007b007b007b007b007b007b007b007b007b007b007b007b007b007b007b007b007b007b007b007b007b007b007b007b007b007b007b007b007b007b007b007b007b004e61bc007a000f2700007f96980035f4";
        $frame = pack("H*",$frame);
        $report = new Report();
        $frame_load = $report($frame);
        var_dump($frame_load);
        die;

//        Redis::set('aa','12345');
//        echo Redis::get('aa');

//        Redis::set('name', 'Taylor');
//
//        $values = Redis::lrange('names', 5, 10);

        //Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " test start");

//       $aa = Uuid::uuid4();
//        echo $aa;
//        die;
//        $condition = [
//            ['code', '=', 123],
//            ['port_number', '>=', 5]
//        ];
//        $port = Port::withTrashed()->where($condition)->get();
//        //$port[0]->delete();
//        $port[0]->restore();
//        var_dump($port[0]->port_number);
//        var_dump($port[1]->port_number);
//        var_dump($port[2]->port_number);
//        var_dump($port[3]->port_number);
//        var_dump($port[4]->port_number);
//
//        die;


    }


    /*****************************************控制类****************************************************/

    //启动充电
    public function startCharge(StartChargeValidator $chargeValidator){

//        $key = Redis::keys('c*');
//        $aa = Redis::get($key[0]);
//        var_dump($aa);die;

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " startCharge start");
        $params = $this->request->all();
        $params = $params['params'];

        $validator = $chargeValidator->make($params);
        if ($validator->fails()) {
            return $this->errorBadRequest($validator->messages());
        }

        $monitorOrderId = $params['order_id'];
        $monitorCode = $params['code'];//monitorCode
        $chargeType = $params['charge_type'];
        $chargeArgs = $params['charge_args'];
        $order_id = $this->getOrderId();


        $port = Port::where('monitor_code',$monitorCode)->first();//firstOrFail
        if(empty($port)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 启动充电,未找到数据 monitorCode: $monitorCode");
            return $this->response->array(
                [
                    'status' => false,
                    'message' => "command send failed"
                ]
            );
        }
        $code = $port->code;//桩编号
        //判断桩是否在线或则是否在空闲中
        $onlineStatus = $port->evse->online_status;
        $workStatus = $port->work_status;
        if($onlineStatus != 1 || $workStatus != 0){
            Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 启动充电,桩不在线或者不在空闲状态,online_status: ".$onlineStatus.' monitorCode:'.$monitorCode );
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "启动充电,桩不在线或者不在空闲状态 online_status:$onlineStatus, workStatus:$workStatus ");
            return $this->response->array(
                [
                    'status' => false,
                    'message' => "command send failed"
                ]
            );
        }

        //启动充电
        $job = (new StartChargeSend($monitorOrderId, $monitorCode, $chargeType, $chargeArgs, $order_id))
            ->onQueue(env("APP_KEY"));
        dispatch($job);

        //返回下发结果
        return $this->response->array(
            [
                'status' => true,
                'message' => "command send sucesss"
            ]
        );





    }


    //续费
    public function renew(RenewValidator $renewValidator){

        //获取信息
        $params = $this->request->all();
        $params = $params['params'];
        $validator = $renewValidator->make($params);
        if ($validator->fails()) {
            return $this->errorBadRequest($validator->messages());
        }

        $monitorCode = $params['code'];       //桩编号
        $monitorOrderId = $params['order_id'];//monitor订单号
        $chargeType = $params['charge_type'];//续费模式
        $chargeArgs = $params['charge_args'];//续费参数

        $port = Port::where('monitor_code',$monitorCode)->first();//firstOrFail
        if(empty($port)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 续费,未找到数据 monitorCode:$monitorCode ");
            return $this->response->array(
                [
                    'status' => false,
                    'message' => "command send failed"
                ]
            );
        }
        $code = $port->code;//桩编号
        //判断桩是否在线或则是否在充电中
        $onlineStatus = $port->evse->online_status;
        $workStatus = $port->work_status;
        $orderId = $port->order_id;//协议订单号
        if($onlineStatus != 1 || $workStatus != 2){
            Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 续费,桩不在线或者不在充电状态,online_status:$onlineStatus, workStatus:$workStatus " );
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "续费,桩不在线或者不在充电状态 online_status:$onlineStatus, workStatus:$workStatus");
            return $this->response->array(
                [
                    'status' => true,
                    'message' => "command send failed"
                ]
            );
        }


        //续费下发
        $job = (new RenewSend($monitorOrderId, $monitorCode, $chargeType, $chargeArgs, $orderId))
            ->onQueue(env("APP_KEY"));
        dispatch($job);



        return $this->response->array(
            [
                'status' => true,
                'message' => "command send sucesss"
            ]
        );





    }


    //停止充电
    public function stopCharge(StopChargeValidator $stopChargeValidator){

        $params = $this->request->all();
        $params = $params['params'];
        $validator = $stopChargeValidator->make($params);
        if ($validator->fails()) {
            return $this->errorBadRequest($validator->messages());
        }
        //$code = $params['code'];
        //$channelNumber = $params['channel_number'];
        $monitorOrderId = $params['order_id'];


        $port = Port::where('monitor_order_id',$monitorOrderId)->first();//firstOrFail
        $code = $port->code;//桩编号
        //判断桩是否在线或则是否在充电中
        $onlineStatus = $port->evse->online_status;
        $workStatus = $port->work_status;
        if($onlineStatus != 1 || $workStatus != 2){
            Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 停止充电,桩不在线或者不在充电状态,online_status:$onlineStatus, workStatus:$workStatus " );
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "启动充电,桩不在线或者不在充电状态 online_status:$onlineStatus, workStatus:$workStatus");
            return $this->response->array(
                [
                    'status' => false,
                    'message' => "command send failed"
                ]
            );
        }

        //下发停止充电帧
        $job = (new StopChargeSend($monitorOrderId))
            ->onQueue(env("APP_KEY"));
        dispatch($job);


        return $this->response->array(
            [
                'status' => true,
                'message' => "command send sucesss"
            ]
        );







    }


    //实时充电数据
    public function chargeRealtime(){

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 实时数据查询start ");
        $params = $this->request->all();
        $params = $params['params'];
        $monitorOrderId = $params['order_id'];
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 实时数据查询 monitorOrderId:$monitorOrderId ");
        //取出枪口信息
        $port = Port::where('monitor_order_id', $monitorOrderId)->first();//firstOrFail
        //订单不存在返回false
        if(empty($port)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 实时数据查询,未找到相应数据 monitorOrderId:$monitorOrderId ");
            return false;
        }
        $code = $port->code;

        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 实时数据,时间 date: ".Carbon::now()." 实时数据参数: monitorOrderId:$monitorOrderId" );

        $data = [];
        //判断充电模式是否为按时间充
        $chargeType = $port->charge_type; //充电模式
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 实时数据查询,chargeType:$chargeType");
        //1为按时长充电
        if($chargeType == 1){
            //如果当前时间减去启动时间小于心跳周期

            //$data['order_id'] = $monitorOrderId;
            $startTime = $port->start_time; //启动时间
            $data['evse_id'] = $port->code;
            $data['channel_number'] = $port->port_number;
            $data['left_time'] = $port->left_time;   //剩余时间
            $data['already_charge_time'] = $port->charge_args - $data['left_time']; //已充时间
            //$data['sufficient_time'] = $data['duration'] - $data['left_time']; //已充时间 already_charge_time
            //刚启动,如果剩余时间是0,当前时间减去启动时间小于10分钟,剩余时间为充电时长
            if($port->left_time == 0 && time() - strtotime($startTime) < 600){
                $data['left_time'] = $port->charge_args;
                $data['already_charge_time'] = 0;
            }
        }
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 实时数据查询 monitorOrderId:$monitorOrderId 
        already_charge_time:".$data['already_charge_time']." leftTime:".$data['left_time']);//." sufficient_time:".$data['sufficient_time']
        //返回数据
        return $this->response->array(
            [
                'status' =>true,
                'message' => "command send sucesss",
                'data'=>$data
            ]
        );


    }







    /*****************************************设置类****************************************************/

    //心跳设置
    public function setHearbeat(){


        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 设置充电周期start ");
        $params = $this->request->all();
        $params = $params['params'];
        $code = $params['code'];
        $hearbeatCycle = $params['hearbeatCycle'];

        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳设置,时间 date: ".Carbon::now()." 心跳设置参数 code: $code, hearbeatCycle:$hearbeatCycle" );

        //获取workeId
        $evse = Evse::where("code",$code)->first(); //firstOrFail
        $workeId = $evse->worker_id;
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 获取workeId:$workeId ");
        if(empty($workeId)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 设置充电周期,workeId为空 ");
            //返回数据
            return $this->response->array(
                [
                    'status_code' => 500,
                    'message' => "command send failed",
                ]
            );
        }

        //从redis中取出流水号,如果没有设置为1
        $serialNumber = $this->getSerialNumber($code);

        //组装帧+
        $hearbeat = new ServerSetHearbeat();
        $hearbeat->code(intval($code));
        $hearbeat->serial_number($serialNumber);
        $hearbeat->heartbeat_cycle($hearbeatCycle);
        $frame = strval($hearbeat);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "frame：$frame");
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ",
            frame:".bin2hex($frame)."
            设置心跳周期:$sendResult " . Carbon::now());

        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳设置帧frame: ".bin2hex($frame) );

        //记录log
        //$fiel_data = " 续费下发参数,monitorCode:$monitorCode, monitorOrderId:$monitorOrderId, chargeType:$chargeType, chargeArgs:$chargeArgs".PHP_EOL."frame: ".bin2hex($frame).'时间:'.Carbon::now();
        //$redis_data = " 续费下发".'-'.json_encode(array('monitorOrderId'=>$monitorOrderId, 'monitorCode'=>$monitorCode, 'chargeType'=>$chargeType, 'chargeArgs'=>$chargeArgs, 'orderId'=>$orderId)).'-'.bin2hex($frame).'-'.Carbon::now().'+';
        $content = "心跳设置周期设置: ".json_encode(array('hearbeatCycle'=>$hearbeatCycle)).'-'.bin2hex($frame).'-'.Carbon::now();
        $this->record_log($code, $content);


        //使用队列,检查心跳周期是否设置成功
        $job = (new CheckSetParameter('heartbeat_cycle', $hearbeatCycle, $code))
            ->onQueue(env("APP_KEY"))
            ->delay(Carbon::now()->addSeconds(5));
        dispatch($job);

        //返回下发结果
        if($sendResult){
            return $this->response->array(
                [
                    'status_code' => 201,
                    'message' => "command send sucesss"
                ]
            );
        }else{
            return $this->response->array(
                [
                    'status_code' => 500,
                    'message' => "command send failed"
                ]
            );
        }


    }

    //服务器信息设置
    public function setServerInfo(){

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 服务器信息设置start ");
        $params = $this->request->all();
        $params = $params['params'];
        $code = $params['code'];
        $domainName = $params['domainName'];
        $portNumber = $params['portNumber'];

        $date = Carbon::now();
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 服务器信息设置,时间 date: $date" );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 服务器信息设置参数 code: $code, domainName:$domainName, portNumber:$portNumber" );

        //获取workeId
        $evse = Evse::where("code",$code)->first(); //firstOrFail
        $workeId = $evse->worker_id;
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 获取workeId:$workeId ");
        if(empty($workeId)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 设置服务器信息,workeId为空 ");
            return $this->response->array(
                [
                    'status_code' => 500,
                    'message' => "command send failed"
                ]
            );
        }

        //从redis中取出流水号,如果没有设置为1
        $serialNumber = Redis::get($code.':serial_number');
        if(empty($serialNumber)){
            $serialNumber = 1;
            Redis::set($code.':serial_number',$serialNumber);
        }else{
            Redis::set($code.':serial_number',++$serialNumber);
        }
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 服务器信息设置,流水号serialNumber：$serialNumber ");

        //组装帧
        $info = new ServerInfo();
        $info->code(intval($code));
        $info->serial_number(intval($serialNumber));
        $info->domain_name($domainName);
        $info->result($portNumber);
        $frame = strval($info);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "frame：$frame");
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ",
            frame:".bin2hex($frame)."
            服务器信息设置:$sendResult " . date('Y-m-d H:i:s', time()));

        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 服务器信息设置帧frame: ".bin2hex($frame) );

        //记录log
        $content = "服务器信息设置: code:$code,domainName:$domainName,portNumber:$portNumber".'-'.bin2hex($frame).'-'.$date;
        $this->record_log($code, $content);


        //组装数组
        $name = ['domain_name', 'port_number'];
        $data = [$domainName, $portNumber];
        //使用队列,检查心跳周期是否设置成功
        $job = (new CheckSetParameter($name, $data, $code))
            ->onQueue(env("APP_KEY"))
            ->delay(Carbon::now()->addSeconds(5));
        dispatch($job);


        //返回下发结果
        if($sendResult){
            return $this->response->array(
                [
                    'status_code' => 201,
                    'message' => "command send sucesss"
                ]
            );
        }else{
            return $this->response->array(
                [
                    'status_code' => 500,
                    'message' => "command send failed"
                ]
            );
        }

    }

    //清空营业额
    public function emptyTurnover(){

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 清空营业额start ");
        $params = $this->request->all();
        $params = $params['params'];
        $code = $params['code'];

        $date = Carbon::now();
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 清空营业额,时间 date: $date" );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 清空营业额参数 code: $code " );

        //获取workeId
        $evse = Evse::where("code",$code)->first(); //firstOrFail
        $workeId = $evse->worker_id;
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 获取workeId:$workeId ");
        if(empty($workeId)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 清空营业额,workeId为空 ");
            return $this->response->array(
                [
                    'status_code' => 500,
                    'message' => "command send failed"
                ]
            );
        }

        //从redis中取出流水号,如果没有设置为1
        $serialNumber = Redis::get($code.':serial_number');
        if(empty($serialNumber)){
            $serialNumber = 1;
            Redis::set($code.':serial_number',$serialNumber);
        }else{
            Redis::set($code.':serial_number',++$serialNumber);
        }
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 清空营业额,流水号serialNumber：$serialNumber ");

        //组装帧
        $turnover = new ServerEmptyTurnover();
        $turnover->code(intval($code));
        $turnover->serial_number($serialNumber);
        $frame = strval($turnover);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "frame：$frame");
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ",
            frame:".bin2hex($frame)."
            清空营业额:$sendResult " . date('Y-m-d H:i:s', time()));

        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 清空营业额参数下发帧 frame:  ".bin2hex($frame) );

        //记录log
        $content = "清空营业额: code:$code".'-'.bin2hex($frame).'-'.$date;
        $this->record_log($code, $content);

        //返回下发结果
        if($sendResult){
            return $this->response->array(
                [
                    'status_code' => 201,
                    'message' => "command send sucesss"
                ]
            );
        }else{
            return $this->response->array(
                [
                    'status_code' => 500,
                    'message' => "command send failed"
                ]
            );
        }

    }






    //设置参数
    public function setParameter(){

        $params = $this->request->all();
        $params = $params['params'];
        $code = $params['code'];
        $channelMaximumCurrent = $params['channelMaximumCurrent'];
        $powerBase = $params['powerBase'];
        $coinRate = $params['coinRate'];
        $cardRate = $params['cardRate'];
        $fullJudge = $params['fullJudge'];
        $disconnect = $params['disconnect'];

        $date = Carbon::now();
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 设置参数,时间 date: $date" );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 设置参数参数 code: $code " );

        //获取workeId
        $evse = Evse::where("code",$code)->first(); //firstOrFail
        $workeId = $evse->worker_id;
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 获取workeId:$workeId ");
        if(empty($workeId)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 清空营业额,workeId为空 ");
            return $this->response->array(
                [
                    'status_code' => 500,
                    'message' => "command send failed"
                ]
            );
        }

        //从redis中取出流水号,如果没有设置为1
        $serialNumber = Redis::get($code.':serial_number');
        if(empty($serialNumber)){
            $serialNumber = 1;
            Redis::set($code.':serial_number',$serialNumber);
        }else{
            Redis::set($code.':serial_number',++$serialNumber);
        }
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 设置参数,流水号serialNumber：$serialNumber ");

        //组装帧
        $parameter = new ServerSetParameter();
        $parameter->code(intval($code));
        $parameter->serial_number($serialNumber);
        $parameter->channel_maximum_current($channelMaximumCurrent);
        $parameter->full_judge($fullJudge);
        $parameter->disconnect($disconnect);
        $parameter->power_base($powerBase);
        $parameter->coin_rate($coinRate);
        $parameter->card_rate($cardRate);
        $frame = strval($parameter);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "frame：$frame");
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ",
            frame:".bin2hex($frame)."
            设置参数:$sendResult " . date('Y-m-d H:i:s', time()));

        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 设置参数参数帧: frame: ".bin2hex($frame) );

        //记录log
        $content = "清空营业额: code:$code,channelMaximumCurrent:$channelMaximumCurrent,powerBase:$powerBase,coinRate:$coinRate,cardRate:$cardRate,fullJudge:$fullJudge,disconnect:$disconnect".'-'.bin2hex($frame).'-'.$date;
        $this->record_log($code, $content);

        $data = ['channelMaximumCurrent'=>$channelMaximumCurrent, "fullJudge"=>$fullJudge, "disconnect"=>$disconnect, "powerBase"=>$powerBase, "coinRate"=>$coinRate, "cardRate"=>$cardRate];
        //使用队列,检查设置参数是否设置成功
        $job = (new CheckSetParameter('parameter', $data, $code))
            ->onQueue(env("APP_KEY"))
            ->delay(Carbon::now()->addSeconds(6));
        dispatch($job);


        //返回下发结果
        if($sendResult){
            return $this->response->array(
                [
                    'status_code' => 201,
                    'message' => "command send sucesss"
                ]
            );
        }else{
            return $this->response->array(
                [
                    'status_code' => 500,
                    'message' => "command send failed"
                ]
            );
        }

    }



    //修改时间
    public function setDateTime(){

        $params = $this->request->all();
        $params = $params['params'];
        $code = $params['code'];
        $dateTime = $params['dateTime'];

        $date = Carbon::now();
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 修改时间,时间 date: $date" );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 修改时间参数 code: $code " );

        //获取workeId
        $evse = Evse::where("code",$code)->first(); //firstOrFail
        $workeId = $evse->worker_id;
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 获取workeId:$workeId ");
        if(empty($workeId)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 修改时间,workeId为空 ");
            return false;
        }

        $dateTime = date('YmdHis', strtotime($dateTime));
        $dateTime = substr($dateTime, 2);

        $setTime = new ServerSetTime();
        $setTime->code(intval($code));
        $setTime->date(intval($dateTime));
        $frame = strval($setTime);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "frame：$frame");
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ",
            frame:".bin2hex($frame)."
            修改时间:$sendResult " . date('Y-m-d H:i:s', time()));

        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 修改时间下发帧 frame: ".bin2hex($frame) );
        //返回下发结果
        if($sendResult){
            return $this->response->array(
                [
                    'status_code' => 201,
                    'message' => "command send sucesss"
                ]
            );
        }else{
            return $this->response->array(
                [
                    'status_code' => 500,
                    'message' => "command send failed"
                ]
            );
        }

    }



    /*****************************************查询类****************************************************/

    //心跳查询
    public function getHearbeat(){

        $params = $this->request->all();
        $params = $params['params'];
        $code = $params['code'];

        $date = Carbon::now();
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳查询,时间 date: $date" );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳查询参数 code: $code " );

        //获取workeId
        $evse = Evse::where("code",$code)->first(); //firstOrFail
        //如果心跳有,则直接返回
        $hearbeatCycle = $evse->heartbeat_cycle;
        if(!empty($hearbeatCycle)){
            $data = ['heartbeatCycle'=>$hearbeatCycle];
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询心跳周期, hearbeatCycle:$hearbeatCycle ");
            return $this->response->array(
                [
                    'status_code' => 201,
                    'message' => "command send sucesss",
                    'data'=>$data
                ]
            );
        }

        $workeId = $evse->worker_id;
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 获取workeId:$workeId ");
        if(empty($workeId)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 清空营业额,workeId为空 ");
            return $this->response->array(
                [
                    'status_code' => 500,
                    'message' => "command send failed"
                ]
            );
        }

        //从redis中取出流水号,如果没有设置为1
        $serialNumber = Redis::get($code.':serial_number');
        if(empty($serialNumber)){
            $serialNumber = 1;
            Redis::set($code.':serial_number',$serialNumber);
        }else{
            Redis::set($code.':serial_number',++$serialNumber);
        }
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳查询,流水号serialNumber：$serialNumber ");

        //组装帧
        $hearbeat = new ServerGetHeartbeat();
        $hearbeat->code(intval($code));
        $hearbeat->serial_number($serialNumber);
        $frame = strval($hearbeat);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "frame：$frame");
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ",
            frame:".bin2hex($frame)."
            心跳查询:$sendResult " . date('Y-m-d H:i:s', time()));

        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳查询下发帧 frame: ".bin2hex($frame) );

        //记录log
        $content = "心跳周期查询: code:$code".'-'.bin2hex($frame).'-'.$date;
        $this->record_log($code, $content);

        //返回下发结果
        if($sendResult){
            return $this->response->array(
                [
                    'status_code' => 201,
                    'message' => "command send sucesss",
                    'data'=>['heartbeatCycle'=>0]
                ]
            );
        }else{
            return $this->response->array(
                [
                    'status_code' => 500,
                    'message' => "command send failed",
                    'data'=>['heartbeatCycle'=>0]
                ]
            );
        }



    }


    //电表抄表查询
    public function getMeter(){

        $params = $this->request->all();
        $params = $params['params'];
        $code = $params['code'];
        $data = [];

        $date = Carbon::now();
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 电表抄表查询,时间 date: $date" );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 电表抄表查询参数 code: $code " );

        //获取前一天电表总电量
        $frontDate = date("Y-m-d",strtotime("-1 day"));
        $condition = [
            ['code', '=', $code],
            ['stat_date', '=', $frontDate]
        ];
        $turnover = Turnover::where($condition)->first();
        if(!empty($turnover)){
            $data['chargedPower'] = $turnover->charged_power; //电表总电量
            if(!empty($data['chargedPower'])){
                Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询电表读数, chargedPower: ".$data['chargedPower']);
                //返回数据
                return $this->response->array(
                    [
                        'status_code' => 201,
                        'message' => "command send sucesss",
                        'data'=>$data
                    ]
                );
            }
        }

        //获取workeId
        $evse = Evse::where("code",$code)->first(); //firstOrFail
        $workeId = $evse->worker_id;
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 获取workeId:$workeId ");
        if(empty($workeId)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 清空营业额,workeId为空 ");
            return false;
        }

        //从redis中取出流水号,如果没有设置为1
        $serialNumber = Redis::get($code.':serial_number');
        if(empty($serialNumber)){
            $serialNumber = 1;
            Redis::set($code.':serial_number',$serialNumber);
        }else{
            Redis::set($code.':serial_number',++$serialNumber);
        }
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 电表抄表查询,流水号serialNumber：$serialNumber ");

        //组装帧
        $readMeter = new ServerReadMeter();
        $readMeter->code(intval($code));
        $readMeter->serial_number($serialNumber);
        $frame = strval($readMeter);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "frame：$frame");
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ",
            frame:".bin2hex($frame)."
            电表查询:$sendResult " . date('Y-m-d H:i:s', time()));

        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 电表抄表查询下发帧 frame: ".bin2hex($frame) );

        //记录log
        $content = "电表抄表查询: code:$code".'-'.bin2hex($frame).'-'.$date;
        $this->record_log($code, $content);

        //返回结果
        if(empty($sendResult)){
            return $this->response->array(
                [
                    'status_code' => 500,
                    'message' => "command send failed",
                    'data'=>['chargedPower'=>0]
                ]
            );
        }else{
            return $this->response->array(
                [
                    'status_code' => 201,
                    'message' => "command send success",
                    'data'=>['chargedPower'=>0]
                ]
            );
        }


    }


    //营业额查询
    public function getTurnover(){

        $params = $this->request->all();
        $params = $params['params'];
        $code = $params['code'];
        $date = $params['date'];

        $dateTime = Carbon::now();
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 营业额查询,时间 date: $dateTime" );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 营业额查询参数 code: $code, date:$date " );

        $condition = [
            ['code', '=', $code],
            ['stat_date', '=', $date]
        ];

        $turnover = Turnover::where($condition)->first();
        if(empty($turnover)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 营业额查询,未找到数据 ");
            return $this->response->array(
                [
                    'status_code' => 500,
                    'message' => "command send failed",
                    "data"=>[]
                ]
            );
        }

        //计算电量
        $power = 0;
        $chargedPower = $turnover->charged_power_time;
        $chargedPower = json_decode($chargedPower, 1);
        foreach ($chargedPower as $v){
            $power = $v + $power;
        }

        $data['date'] = $turnover->stat_date;
        $data['coinNumber'] = $turnover->coin_number;
        $data['cardFree'] = $turnover->card_free;
        $data['cardTime'] = $turnover->card_time;
        $data['chargedPower'] = $power / 100;//kwh

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 营业额查询结果
         date:".$data['date']." coinNumber:".$data['coinNumber']." cardFree:".$data['cardFree']." cardTime:".$data['cardTime']." chargedPower:".$data['chargedPower']);

        //返回数据
        return $this->response->array(
            [
                'status_code' => 201,
                'message' => "command send sucesss",
                'data'=>$data
            ]
        );



    }



    //通道查询
    public function getChannel(){

        $params = $this->request->all();
        $params = $params['params'];
        $code = $params['code'];
        $channelNum = $params['channelNum'];

        $date = Carbon::now();
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 通道查询,时间 date: $date" );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 通道查询参数 code: $code, channelNum:$channelNum " );

        //获取workeId
        $evse = Evse::where("code",$code)->first(); //firstOrFail
        $workeId = $evse->worker_id;
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 获取workeId:$workeId ");
        if(empty($workeId)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 清空营业额,workeId为空 ");
            return false;
        }


        //从redis中取出流水号,如果没有设置为1
        $serialNumber = Redis::get($code.':serial_number');
        if(empty($serialNumber)){
            $serialNumber = 1;
            Redis::set($code.':serial_number',$serialNumber);
        }else{
            Redis::set($code.':serial_number',++$serialNumber);
        }
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 通道查询,流水号serialNumber：$serialNumber ");

        $status = new ServerGetChannelStatus();
        $status->code(intval($code));
        $status->serial_number($serialNumber);
        $status->channel_num($channelNum);
        $frame = strval($status);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "frame：$frame");
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ",
            frame:".bin2hex($frame)."
            通道查询:$sendResult " . date('Y-m-d H:i:s', time()));

        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 通道查询下发帧 frame: ".bin2hex($frame) );

        //记录log
        $content = "心跳周期查询: code:$code,channelNum:$channelNum".'-'.bin2hex($frame).'-'.$date;
        $this->record_log($code, $content);

        //返回下发结果
        if($sendResult){
            return $this->response->array(
                [
                    'status_code' => 201,
                    'message' => "command send sucesss"
                ]
            );
        }else{
            return $this->response->array(
                [
                    'status_code' => 500,
                    'message' => "command send failed"
                ]
            );
        }

    }


    //查询参数
    public function getParameter(){

        $params = $this->request->all();
        $params = $params['params'];
        $code = $params['code'];

        $date = Carbon::now();
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询参数,时间 date: $date" );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询参数参数 code: $code " );

        //获取workeId
        $evse = Evse::where("code",$code)->first(); //firstOrFail
        if(empty($evse)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询参数,未找到数据 ");
            //返回数据
            return $this->response->array(
                [
                    'status_code' => 500,
                    'message' => "command send failed",
                    'data'=>[]
                ]
            );
        }
        $parameter = $evse->parameter;
        $parameter = json_decode($parameter, 1);
        if(!empty($parameter)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询参数 ");
            $data = array('channelMaximumCurrent'=>$parameter['channel_maximum_current'], 'powerBase'=>$parameter['power_base'], 'coinRate'=>$parameter['coin_rate'], 'cardRate'=>$parameter['card_rate'], 'fullJudge'=>$parameter['full_judge'], 'disconnect'=>$parameter['disconnect']);
            return $this->response->array(
                [
                    'status_code' => 201,
                    'message' => "command send success",
                    'data'=>$data
                ]
            );
        }
        $workeId = $evse->worker_id;
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 获取workeId:$workeId ");
        if(empty($workeId)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 清空营业额,workeId为空 ");
            return false;
        }

        //从redis中取出流水号,如果没有设置为1
        $serialNumber = Redis::get($code.':serial_number');
        if(empty($serialNumber)){
            $serialNumber = 1;
            Redis::set($code.':serial_number',$serialNumber);
        }else{
            Redis::set($code.':serial_number',++$serialNumber);
        }
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 通道查询,流水号serialNumber：$serialNumber ");

        $parameter = new ServerGetParameter();
        $parameter->code(intval($code));
        $parameter->serial_number($serialNumber);
        $frame = strval($parameter);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "frame：$frame");
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ",
            frame:".bin2hex($frame)."
            查询参数:$sendResult " . date('Y-m-d H:i:s', time()));

        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询参数下发帧 frame: ".bin2hex($frame) );

        //记录log
        $content = "查询参数: code:$code".'-'.bin2hex($frame).'-'.$date;
        $this->record_log($code, $content);

        //返回下发结果
        if($sendResult){
            return $this->response->array(
                [
                    'status_code' => 201,
                    'message' => "command send sucesss",
                    'data'=>[]
                ]
            );
        }else{
            return $this->response->array(
                [
                    'status_code' => 500,
                    'message' => "command send failed",
                    'data'=>[]
                ]
            );
        }



    }



    //时间查询
    public function getDateTime(){


        $params = $this->request->all();
        $params = $params['params'];
        $code = $params['code'];

        //获取workeId
        $evse = Evse::where("code",$code)->first(); //firstOrFail
        if(empty($evse)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询时间,未找到数据 ");
            //返回数据
            return $this->response->array(
                [
                    'status_code' => 500,
                    'message' => "command send failed",
                ]
            );
        }


        $workeId = $evse->worker_id;
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 获取workeId:$workeId ");
        if(empty($workeId)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 清空营业额,workeId为空 ");
            return false;
        }

        //从redis中取出流水号,如果没有设置为1
        $serialNumber = Redis::get($code.':serial_number');
        if(empty($serialNumber)){
            $serialNumber = 1;
            Redis::set($code.':serial_number',$serialNumber);
        }else{
            Redis::set($code.':serial_number',++$serialNumber);
        }
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询时间,流水号serialNumber：$serialNumber ");


        $dateTime = new ServerGetDateTime();
        $dateTime->code(intval($code));
        $dateTime->serial_number(intval($serialNumber));
        $frame = strval($dateTime);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "frame：$frame");
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ",
            frame:".bin2hex($frame)."
            查询时间:$sendResult " . date('Y-m-d H:i:s', time()));


        //返回下发结果
        if($sendResult){
            return $this->response->array(
                [
                    'status_code' => 201,
                    'message' => "command send sucesss",
                ]
            );
        }else{
            return $this->response->array(
                [
                    'status_code' => 500,
                    'message' => "command send failed",
                ]
            );
        }



    }


    //设置id
    public function setId(){

        $params = $this->request->all();
        $params = $params['params'];
        $code = $params['code'];
        $deviceId = $params['device_id'];//设备id

        //获取workeId
        $evse = Evse::where("code",$code)->first(); //firstOrFail
        if(empty($evse)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 设置id,未找到数据 ");
            //返回数据
            return $this->response->array(
                [
                    'status_code' => 500,
                    'message' => "command send failed",
                ]
            );
        }


        $workeId = $evse->worker_id;
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 获取workeId:$workeId ");
        if(empty($workeId)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 设置id,workeId为空 ");
            return false;
        }

        //从redis中取出流水号,如果没有设置为1
        $serialNumber = Redis::get($code.':serial_number');
        if(empty($serialNumber)){
            $serialNumber = 1;
            Redis::set($code.':serial_number',$serialNumber);
        }else{
            Redis::set($code.':serial_number',++$serialNumber);
        }
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 设置id,流水号serialNumber：$serialNumber ");


        $setId = new ServerSetId();
        $setId->code(intval($code));
        $setId->serial_number(intval($serialNumber));
        $setId->device(intval($deviceId));
        $frame = strval($setId);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "frame：$frame");
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ",
            frame:".bin2hex($frame)."
            设置id:$sendResult " . date('Y-m-d H:i:s', time()));


        //返回下发结果
        if($sendResult){
            return $this->response->array(
                [
                    'status_code' => 201,
                    'message' => "command send sucesss",
                ]
            );
        }else{
            return $this->response->array(
                [
                    'status_code' => 500,
                    'message' => "command send failed",
                ]
            );
        }




    }


    //查询ID
    public function getId(){

        $params = $this->request->all();
        $params = $params['params'];
        $code = $params['code'];

        //获取workeId
        $evse = Evse::where("code",$code)->first(); //firstOrFail
        if(empty($evse)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询ID,未找到数据 ");
            //返回数据
            return $this->response->array(
                [
                    'status_code' => 500,
                    'message' => "command send failed",
                ]
            );
        }


        $workeId = $evse->worker_id;
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询ID:$workeId ");
        if(empty($workeId)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询ID,workeId为空 ");
            return false;
        }

        //从redis中取出流水号,如果没有设置为1
        $serialNumber = Redis::get($code.':serial_number');
        if(empty($serialNumber)){
            $serialNumber = 1;
            Redis::set($code.':serial_number',$serialNumber);
        }else{
            Redis::set($code.':serial_number',++$serialNumber);
        }
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询ID,流水号serialNumber：$serialNumber ");


        $getId = new ServerGetId();
        $getId->code(intval($code));
        $getId->serial_number(intval($serialNumber));
        $frame = strval($getId);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "frame：$frame");
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ",
            frame:".bin2hex($frame)."
            查询ID:$sendResult " . date('Y-m-d H:i:s', time()));


        //返回下发结果
        if($sendResult){
            return $this->response->array(
                [
                    'status_code' => 201,
                    'message' => "command send sucesss",
                ]
            );
        }else{
            return $this->response->array(
                [
                    'status_code' => 500,
                    'message' => "command send failed",
                ]
            );
        }


    }


    //查询设备识别号
    public function deviceIdentification(){

        $params = $this->request->all();
        $params = $params['params'];
        $code = $params['code'];

        //获取workeId
        $evse = Evse::where("code",$code)->first(); //firstOrFail
        if(empty($evse)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询设备识别号,未找到数据 ");
            //返回数据
            return $this->response->array(
                [
                    'status_code' => 500,
                    'message' => "command send failed",
                ]
            );
        }


        $workeId = $evse->worker_id;
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询设备识别号:$workeId ");
        if(empty($workeId)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询设备识别号,workeId为空 ");
            return false;
        }

        //从redis中取出流水号,如果没有设置为1
        $serialNumber = Redis::get($code.':serial_number');
        if(empty($serialNumber)){
            $serialNumber = 1;
            Redis::set($code.':serial_number',$serialNumber);
        }else{
            Redis::set($code.':serial_number',++$serialNumber);
        }
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询设备识别号,流水号serialNumber：$serialNumber ");


        $deviceIdentification = new ServerGetDeviceIdentification();
        $deviceIdentification->code(intval($code));
        $deviceIdentification->serial_number(intval($serialNumber));
        $frame = strval($deviceIdentification);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "frame：$frame");
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ",
            frame:".bin2hex($frame)."
            查询设备识别号:$sendResult " . date('Y-m-d H:i:s', time()));


        //返回下发结果
        if($sendResult){
            return $this->response->array(
                [
                    'status_code' => 201,
                    'message' => "command send sucesss",
                ]
            );
        }else{
            return $this->response->array(
                [
                    'status_code' => 500,
                    'message' => "command send failed",
                ]
            );
        }


    }


    //获取redis存储log
    public function code_log(){

        $params = $this->request->all();
        $params = $params['params'];
        $code = $params['code'];
        $data = Redis::keys($code.'*');

        return $data;

//        $key = Redis::keys('c*');
//        $aa = Redis::get($key[0]);
//        var_dump($aa);die;

    }





    //获取流水号
    private function getSerialNumber($code){

        //从redis中取出流水号,如果没有设置为1
        $serialNumber = Redis::get($code.':serial_number');
        if(empty($serialNumber)){
            $serialNumber = 1;
            Redis::set($code.':serial_number',$serialNumber);
        }else{
            Redis::set($code.':serial_number',++$serialNumber);
        }
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳设置,流水号serialNumber：$serialNumber ");

        return $serialNumber;

    }




    //获取订单号
    private function getOrderId(){

        //从redis中取出订单号,如果没有设置为0
        $orderId = Redis::get('order_id');
        if(empty($orderId)){
            $orderId = 1;
            Redis::set('order_id',$orderId);
        }else{
            //如果大于等于255则重置为1
            if($orderId >= 126){
                Redis::set('order_id',1);
            }else{
                Redis::set('order_id',++$orderId);
            }

        }

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 订单号 order_id:$orderId ");

        return $orderId;

    }



    public function record_log($code, $content){

        //记录log
        $parameter = $code.uniqid(mt_rand(),1);
        Redis::set($parameter,$content,'EX',86400);

    }





}