<?php
namespace Wormhole\Protocols\LaiChongV2\TenRoad\Controllers\Api;
/**
 * Created by PhpStorm.
 * User: chao
 * Date: 2018-10-08
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
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\Heartbeat;
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\PortInfo;
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\Renew;

//设置参数队列
use Wormhole\Protocols\LaiChongV2\TenRoad\Jobs\CheckSetParameter;
//检测启动续费停止是否收到桩响应
use Wormhole\Protocols\LaiChongV2\TenRoad\Jobs\CheckResponse;
//启动充电队列
use Wormhole\Protocols\LaiChongV2\TenRoad\Jobs\StartChargeSend;
//续费下发
use Wormhole\Protocols\LaiChongV2\TenRoad\Jobs\RenewSend;
//停止充电下发
use Wormhole\Protocols\LaiChongV2\TenRoad\Jobs\StopChargeSend;

use Wormhole\Validators\StartChargeValidator;
use Wormhole\Validators\RenewValidator;
use Wormhole\Validators\StopChargeValidator;

use Wormhole\Protocols\LaiChongV2\TenRoad\Models\Evse;
use Wormhole\Protocols\LaiChongV2\TenRoad\Models\Port;
use Wormhole\Protocols\LaiChongV2\TenRoad\Models\ChargeOrderMapping;
use Wormhole\Protocols\LaiChongV2\TenRoad\Models\Turnover;

use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Frame;
use Wormhole\Protocols\LaiChongV2\TenRoad\EventsApi;
//启动充电
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\StartCharge as ServerStartCharge;

//续费
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\Renew as ServerRenew;

//停止充电
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\StopCharge as ServerStopCharge;

//心跳设置
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\SetHearbeat as ServerSetHearbeat;

//连接阈值设置
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\SetThreshold as ServerSetThreshold;

//设置服务器端口
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\SetPort as ServerSetPort;

//清空营业额
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\EmptyTurnover as ServerEmptyTurnover;

//查询ID
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\GetId as ServerGetId;

//心跳查询
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\GetHearbeat as ServerGetHeartbeat;

//连接阈值
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\GetThreshold as ServerGetThreshold;

//信号强度查询
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\Signal as ServerSignal;

//电表抄表
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\ReadMeter as ServerReadMeter;

//营业额查询
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\GetTurnover as ServerGetTurnover;

//状态查询
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\GetWorkStatus as ServerGetWorkStatus;

//查询参数
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\GetParameter as ServerGetParameter;

//单通道时间查询
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\SingleChannel as ServerSingleChannel;

//所有通道时间查询
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\AllThoroughTime as ServerAllThoroughTime;

//修改参数
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\SetParameter as ServerSetParameter;

//修改时间
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\SetTime as ServerSetTime;

use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\Sign as EvseSign;
use Ramsey\Uuid\Uuid;
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\Sign as ServerSign;
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
        $frame = strval($sign);
        //var_dump(bin2hex($frame));
        //die;

        //解析帧
        $sign2 = new EvseSign();
        $frame_load = $sign2($frame);
        var_dump($frame_load->code->getValue());
die;
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
        $params = $this->request->all();
        $params = $params['params'];
        $validator = $chargeValidator->make($params);
        if ($validator->fails()) {
            return $this->errorBadRequest($validator->messages());
        }
        $monitor_order_id = $params['order_id'];
        $monitor_code = $params['code'];//monitorCode
        $port_numbers = $params['port_numbers'];
        $charge_time = $params['charge_time'];
        $order_no = $this->getOrderId(); //订单号
        //获取枪口数据
        $port = Port::where('monitor_code',$monitor_code)->first();//firstOrFail
        if(empty($port)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 启动充电,未找到数据 monitorCode: $monitorCode");
            $this->error();
        }
        $code = $port->code;//桩编号
        //判断桩是否在线或则是否在空闲中
        $onlineStatus = $port->online_status;
        $workStatus = $port->work_status;
        if($onlineStatus != 1 || $workStatus != 0){
            Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 启动充电,桩不在线或者不在空闲状态,online_status: ".$onlineStatus.' monitorCode:'.$monitorCode );
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "启动充电,桩不在线或者不在空闲状态 online_status:$onlineStatus, workStatus:$workStatus ");
            $this->error();
        }
        //将订单号存到启动的枪口中
        $port->order_no = $order_no;
        $port->save();

        //启动充电
        $job = (new StartChargeSend($monitor_order_id, $monitor_code, $port_numbers, $charge_time, $order_no))
            ->onQueue(env("APP_KEY"));
        dispatch($job);
        //返回下发结果
        $this->success();
    }

    //续费
    public function renew(RenewValidator $renewValidator){
        $params = $this->request->all();
        $params = $params['params'];
        $validator = $renewValidator->make($params);
        if ($validator->fails()) {
            return $this->errorBadRequest($validator->messages());
        }
        $monitor_order_id = $params['order_id']; //monitor订单号
        $monitor_code = $params['code'];//monitorCode
        $port_numbers = $params['port_numbers']; //枪口号
        $charge_time = $params['charge_time']; //添加时间
        $order_no = $this->getOrderId(); //订单号
        //获取枪数据
        $port = Port::where('monitor_code',$monitor_code)->first();//firstOrFail
        if(empty($port)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 续费,未找到数据 monitor_code:$monitor_code ");
            $this->error();
        }
        $code = $port->code;//桩编号
        //判断桩是否在线或则是否在充电中
        $onlineStatus = $port->evse->online_status;
        $workStatus = $port->work_status;
        $orderId = $port->order_id;//协议订单号
        if($onlineStatus != 1 || $workStatus != 2){
            Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 续费,桩不在线或者不在充电状态,online_status:$onlineStatus, workStatus:$workStatus " );
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "续费,桩不在线或者不在充电状态 online_status:$onlineStatus, workStatus:$workStatus");
            $this->error();
        }
        //续费下发
        $job = (new RenewSend($monitor_order_id, $monitor_code, $port_numbers, $charge_time, $order_no))
            ->onQueue(env("APP_KEY"));
        dispatch($job);
        $this->success();
    }

    //停止充电
    public function stopCharge(StopChargeValidator $stopChargeValidator){
        $params = $this->request->all();
        $params = $params['params'];
        $validator = $stopChargeValidator->make($params);
        if ($validator->fails()) {
            return $this->errorBadRequest($validator->messages());
        }
        //monitor订单号
        $monitor_order_id = $params['order_no'];
        //获取枪数据
        $port = Port::where('monitor_order_id',$monitor_order_id)->first();//firstOrFail
        $code = $port->code;//桩编号
        //判断桩是否在线或则是否在充电中
        $onlineStatus = $port->evse->online_status;
        $workStatus = $port->work_status;
        if($onlineStatus != 1 || $workStatus != 2){
            Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 停止充电,桩不在线或者不在充电状态,online_status:$onlineStatus, workStatus:$workStatus " );
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "启动充电,桩不在线或者不在充电状态 online_status:$onlineStatus, workStatus:$workStatus");
            $this->error();
        }
        //下发停止充电帧监控
        $job = (new StopChargeSend($monitor_order_id))
            ->onQueue(env("APP_KEY"));
        dispatch($job);
        $this->success();
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
        $this->success($data);
    }

    /*****************************************设置类****************************************************/

    //心跳设置
    public function setHearbeat(){
        $params = $this->request->all();
        $params = $params['params'];
        $code = $params['code'];
        $hearbeatCycle = $params['hearbeatCycle'];
        if(empty($params) || empty($code) || empty($hearbeatCycle)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 设置心跳周期,收到的参数为空 ");
            $this->error();
        }
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳设置,时间 date: ".Carbon::now()." 心跳设置参数 code: $code, hearbeatCycle:$hearbeatCycle" );
        //获取workeId
        $evse = Evse::where("code",$code)->first(); //firstOrFail
        $workeId = $evse->worker_id;
        if(empty($workeId)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " workeId:$workeId, 设置充电周期,workeId为空 ");
            //返回数据
            $this->error();
        }
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 获取workeId:$workeId ");

        //组装帧
        $hearbeat = new ServerSetHearbeat();
        $hearbeat->code(intval($code));
        $hearbeat->heartbeat_cycle(intval($hearbeatCycle));
        $frame = strval($hearbeat);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "frame：$frame");
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ",
            frame:".bin2hex($frame)."
            设置心跳周期:$sendResult " . Carbon::now());
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳设置帧frame: ".bin2hex($frame) );
        $content = "心跳设置周期设置: ".json_encode(array('hearbeatCycle'=>$hearbeatCycle)).'-'.bin2hex($frame).'-'.Carbon::now();
        $this->record_log($code, $content);

        //使用队列,检查心跳周期是否设置成功
        $job = (new CheckSetParameter('heartbeat_cycle', $hearbeatCycle, $code))
            ->onQueue(env("APP_KEY"))
            ->delay(Carbon::now()->addSeconds(5));
        dispatch($job);
        //返回下发结果
        if($sendResult){
            $this->success();
        }else{
            $this->error();
        }
    }

    //连接阈值设置
    public function setThreshold(){
        $params = $this->request->all();
        $params = $params['params'];
        $code = $params['code'];
        $threshold = $params['threshold'];
        if(empty($params) || empty($code) || empty($threshold)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 连接阈值设置,收到的参数为空 ");
            $this->error();
        }
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 连接阈值设置,时间 date: ".Carbon::now()." 连接阈值参数 code: $code, threshold:$threshold" );
        //获取workeId
        $evse = Evse::where("code",$code)->first(); //firstOrFail
        $workeId = $evse->worker_id;
        if(empty($workeId)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " workeId:$workeId, 连接阈值设置,workeId为空 ");
            //返回数据
            $this->error();
        }
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 获取workeId:$workeId ");

        //组装帧
        $threshold = new ServerSetThreshold();
        $threshold->code(intval($code));
        $threshold->threshold(intval($threshold));
        $frame = strval($threshold);
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ",frame:".bin2hex($frame)."连接阈值设置:$sendResult " . Carbon::now());
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 连接阈值设置frame: ".bin2hex($frame) );
        $content = "连接阈值设置frame: ".json_encode(array('threshold'=>$threshold)).'-'.bin2hex($frame).'-'.Carbon::now();
        $this->record_log($code, $content);

        //使用队列,检查心跳周期是否设置成功
        $job = (new CheckSetParameter('threshold', $threshold, $code))
            ->onQueue(env("APP_KEY"))
            ->delay(Carbon::now()->addSeconds(5));
        dispatch($job);
        //返回下发结果
        if($sendResult){
            $this->success();
        }else{
            $this->error();
        }
    }

    //服务器端口设置
    public function setServerInfo(){
        $params = $this->request->all();
        $params = $params['params'];
        $code = $params['code'];
        $portNumber = $params['portNumber'];
        if(empty($params) || empty($code) || empty($portNumber)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 服务器端口设置,收到数据为空 ");
            $this->error();
        }

        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 服务器端口设置,时间 date: ".Carbon::now() );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 服务器端口设置参数 code: $code, portNumber:$portNumber" );

        //获取workeId
        $evse = Evse::where("code",$code)->first(); //firstOrFail
        $workeId = $evse->worker_id;
        if(empty($workeId)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 服务器端口设置, workeId为空 ");
            $this->error();
        }
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 获取workeId:$workeId ");

        //组装帧
        $info = new ServerSetPort();
        $info->code(intval($code));
        $info->port($portNumber);
        $frame = strval($info);
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", frame:".bin2hex($frame)." 服务器端口设置:$sendResult " . Carbon::now());
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 服务器信息设置帧frame: ".bin2hex($frame) );
        //记录log
        $content = "服务器信息设置: code:$code,portNumber:$portNumber".'-'.bin2hex($frame).'-'.Carbon::now();
        $this->record_log($code, $content);

        //使用队列,检查心跳周期是否设置成功
        $job = (new CheckSetParameter('port_number', $portNumber, $code))
            ->onQueue(env("APP_KEY"))
            ->delay(Carbon::now()->addSeconds(5));
        dispatch($job);

        //返回下发结果
        if($sendResult){
            $this->success();
        }else{
            $this->error();
        }
    }

    //设置参数
    public function setParameter(){
        $params = $this->request->all();
        $params = $params['params'];
        $code = $params['code'];
        $card_rate = $params['card_rate']; //刷卡费率
        $card_time = $params['card_time']; //刷卡时间
        $coin_rate = $params['coin_rate']; //投币费率
        $power_base = $params['power_base']; //标准电流
        $channel_maximum_current = $params['channel_maximum_current']; //通道最大电流
        $disconnect = $params['disconnect']; //插头拔断断电开关
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 设置参数,code: $code 时间 date: ".Carbon::now() );

        //获取workeId
        $evse = Evse::where("code",$code)->first(); //firstOrFail
        $workeId = $evse->worker_id;
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 获取workeId:$workeId ");
        if(empty($workeId)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 设置参数,workeId为空 ");
            $this->error();
        }
        //获取单号
        if(empty($evse->order_no) || $evse->order_no > 200){
            $evse->order_no = 1;
        }else{
            $evse->order_no = $evse->order_no + 1;
        }
        $evse->save();
        $order_no = $evse->order_no;

        //组装帧
        $parameter = new ServerSetParameter();
        $parameter->code(intval($code));
        $parameter->order_no(intval($order_no));
        $parameter->card_rate($card_rate);
        $parameter->card_time($card_time);
        $parameter->coin_rate($coin_rate);
        $parameter->power_base($power_base);
        $parameter->channel_maximum_current($channel_maximum_current);
        $parameter->disconnect($disconnect);
        $frame = strval($parameter);
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ",frame:".bin2hex($frame)."设置参数:$sendResult " .Carbon::now());
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 设置参数参数帧: frame: ".bin2hex($frame) );

        //记录log
        $content = "设置参数参数帧: code:$code,order_no:$order_no,card_rate:$card_rate,card_time:$card_time,coin_rate:$coin_rate,power_base:$power_base,channel_maximum_current:$channel_maximum_current,disconnect:$disconnect".'-'.bin2hex($frame).'-'.Carbon::now();
        $this->record_log($code, $content);

        $data = ['card_rate'=>$card_rate, "card_time"=>$card_time, "coin_rate"=>$coin_rate, "power_base"=>$power_base, "channel_maximum_current"=>$channel_maximum_current, "disconnect"=>$disconnect];
        //使用队列,检查设置参数是否设置成功
        $job = (new CheckSetParameter('parameter', $data, $code))
            ->onQueue(env("APP_KEY"))
            ->delay(Carbon::now()->addSeconds(6));
        dispatch($job);

        //返回下发结果
        if($sendResult){
            $this->success();
        }else{
            $this->error();
        }
    }

    //修改时间
    public function setDateTime(){
        $params = $this->request->all();
        $params = $params['params'];
        $code = $params['code'];
        $year = $params['year'];
        $month = $params['month'];
        $day = $params['day'];
        $hour = $params['hour'];
        $minute = $params['minute'];
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 修改时间,时间 date: ".Carbon::now() );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 修改时间参数 code: $code " );
        //获取workeId
        $evse = Evse::where("code",$code)->first(); //firstOrFail
        $workeId = $evse->worker_id;
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 获取workeId:$workeId ");
        if(empty($workeId)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 修改时间,workeId为空 ");
            return false;
        }

        //获取单号
        if(empty($evse->order_no) || $evse->order_no > 200){
            $evse->order_no = 1;
        }else{
            $evse->order_no = $evse->order_no + 1;
        }
        $evse->save();
        $order_no = $evse->order_no;

        //$dateTime = date('YmdHis', strtotime($dateTime));
        //$dateTime = substr($dateTime, 2);

        $setTime = new ServerSetTime();
        $setTime->code(intval($code));
        $setTime->order_no(intval($order_no));
        $setTime->year(intval($year));
        $setTime->moth(intval($month));
        $setTime->day(intval($day));
        $setTime->hour(intval($hour));
        $setTime->minute(intval($minute));
        $frame = strval($setTime);
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ",frame:".bin2hex($frame)."修改时间:$sendResult " . Carbon::now());
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 修改时间下发帧 frame: ".bin2hex($frame) );
        //返回下发结果
        if($sendResult){
            $this->success();
        }else{
            $this->error();
        }
    }

    //清空营业额
    public function emptyTurnover(){
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 清空营业额start ");
        $params = $this->request->all();
        $params = $params['params'];
        $code = $params['code'];
        $option = $params['option'];
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "  code: $code 清空营业额,时间 date: ".Carbon::now() );

        //获取workeId
        $evse = Evse::where("code",$code)->first(); //firstOrFail
        $workeId = $evse->worker_id;
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 获取workeId:$workeId ");
        if(empty($workeId)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 清空营业额,workeId为空 ");
            $this->error();
        }

        //组装帧
        $turnover = new ServerEmptyTurnover();
        $turnover->code(intval($code));
        $turnover->order_no(intval($orer_no));
        $turnover->option(intval($option));
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
            $this->success();
        }else{
            $this->error();
        }
    }

    /*****************************************查询类****************************************************/

    //心跳查询
    public function getHearbeat(){
        $params = $this->request->all();
        $params = $params['params'];
        $code = $params['code'];
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询心跳周期 code: $code ");
        //参数是否为空
        if(empty($params) || empty($code)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询心跳周期, 收到参数为空 ");
            $this->error();
        }

        //如果表里面有则直接获取返回
        //获取workeId
        $evse = Evse::where("code",$code)->first(); //firstOrFail
        //如果心跳有,则直接返回
        $hearbeatCycle = $evse->heartbeat_cycle;
        if(!empty($hearbeatCycle)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询心跳周期, hearbeatCycle:$hearbeatCycle ");
            $this->success($hearbeatCycle);
        }

        //如果表里面没有心跳周期,则下发查询
        $workeId = $evse->worker_id;
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 获取workeId:$workeId ");
        if(empty($workeId)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询心跳周期, workeId为空 ");
            $this->error();
        }

        //组装帧
        $hearbeat = new ServerGetHeartbeat();
        $hearbeat->code(intval($code));
        $frame = strval($hearbeat);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "frame：$frame");
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ",
            frame:".bin2hex($frame)."
            查询心跳周期:$sendResult " . date('Y-m-d H:i:s', time()));

        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询心跳周期下发帧 frame: ".bin2hex($frame) );

        //记录log
        $content = "心跳周期查询: code:$code".'-'.bin2hex($frame).'-'.Carbon::now();
        $this->record_log($code, $content);

        //休眠2秒后,取出心跳周期
        sleep(2);
        $evse = Evse::where("code", $code)->first();
        if(empty($evse)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询心跳周期,未找到此桩 ");
            return false;
        }
        $heartbeat_cycle = $evse->heartbeat_cycle;
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询心跳周期,返回心跳周期 heartbeat_cycle:$heartbeat_cycle");

        //返回下发结果
        if($heartbeat_cycle){
            $this->success($heartbeat_cycle);
        }else{
            $this->error();
        }
    }

    //连接阈值查询
    public function getThreshold(){
        $params = $this->request->all();
        $params = $params['params'];
        $code = $params['code'];
        //参数是否为空
        if(empty($params) || empty($code)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 连接阈值查询, 收到参数为空 ");
            $this->error();
        }
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 连接阈值查询 code: $code ");

        //如果表里面有则直接获取返回
        //获取workeId
        $evse = Evse::where("code",$code)->first(); //firstOrFail
        //如果心跳有,则直接返回
        $threshold = $evse->threshold;
        if(!empty($threshold)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 连接阈值查询, threshold:$threshold ");
            $this->success($threshold);
        }

        //如果表里面没有连接阈值,则下发查询
        $workeId = $evse->worker_id;
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 获取workeId:$workeId ");
        if(empty($workeId)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 连接阈值查询, workeId为空 ");
            $this->error();
        }

        //组装帧
        $threshold = new ServerGetThreshold();
        $threshold->code(intval($code));
        $frame = strval($threshold);
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ",frame:".bin2hex($frame)."连接阈值查询:$sendResult " . Carbon::now());
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 连接阈值查询下发帧 frame: ".bin2hex($frame) );
        //记录log
        $content = "连接阈值查询: code:$code".'-'.bin2hex($frame).'-'.Carbon::now();
        $this->record_log($code, $content);

        //休眠2秒后,取出心跳周期
        sleep(2);
        $evse = Evse::where("code", $code)->first();
        if(empty($evse)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 连接阈值查询,未找到此桩 ");
            return false;
        }
        $threshold = $evse->threshold;
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 连接阈值查询,返回连接阈值 threshold:$threshold");

        //返回下发结果
        if($threshold){
            $this->success();
        }else{
            $this->error();
        }
    }

    //信号强度查询
    public function getSignal(){
        $params = $this->request->all();
        $params = $params['params'];
        $code = $params['code'];
        //参数是否为空
        if(empty($params) || empty($code)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 信号强度查询, 收到参数为空 ");
            $this->error();
        }
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 信号强度查询 code: $code ");

        //获取workeId
        $evse = Evse::where("code",$code)->first(); //firstOrFail
        $workeId = $evse->worker_id;
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 获取workeId:$workeId ");
        if(empty($workeId)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 信号强度查询, workeId为空 ");
            $this->error();
        }

        //组装帧
        $signal = new ServerSignal();
        $signal->code(intval($code));
        $frame = strval($signal);
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ",frame:".bin2hex($frame)."信号强度查询:$sendResult " . Carbon::now());
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 信号强度查询下发帧 frame: ".bin2hex($frame) );
        //记录log
        $content = "信号强度查询: code:$code".'-'.bin2hex($frame).'-'.Carbon::now();
        $this->record_log($code, $content);

        //休眠2秒后,取出心跳周期
        sleep(2);
        $evse = Evse::where("code", $code)->first();
        if(empty($evse)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 信号强度查询,未找到此桩 ");
            return false;
        }
        $signal_intensity = $evse->signal_intensity;
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 信号强度查询,返回信号强度 signal_intensity:$signal_intensity");

        //返回下发结果
        if($signal_intensity){
            $this->success($signal_intensity);
        }else{
            $this->error();
        }
    }

    //电表抄表查询
    public function getMeter(){
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 抄表查询下发开始时间:".Carbon::now());
        //找到所有在线的桩
        $evse = Evse::where("online_status",1)->all();
        if(empty($evse)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 营业额查询,未找到桩数据 ");
        }
        //循环每一个桩进行下发查找电量
        $readMeter = new ServerReadMeter();
        foreach ($evse as $v){
            if(empty($v->worker_id)){
                continue;
            }
            $readMeter->code(intval($v->code));
            $readMeter->address(intval($v->address));
            $frame = strval($readMeter);
            $sendResult  = EventsApi::sendMsg($v->worker_id,$frame);
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ",frame:".bin2hex($frame)."电表查询:$sendResult " . Carbon::now());
            Logger::log($v->code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 电表抄表查询下发帧 frame: ".bin2hex($frame) );
            usleep(500000); //睡眠0.5秒
        }
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 抄表查询下发结束时间:".Carbon::now());
        //查询营业额
        $this->getTurnover($evse);
    }

    //查询参数
    public function getParameter(){
        $params = $this->request->all();
        $params = $params['params'];
        $code = $params['code'];
        if(empty($code)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询参数,参数为空 ");
            $this->error();
        }
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询参数,时间 date: ".Carbon::now() );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询参数参数 code: $code " );

        //获取workeId
        $evse = Evse::where("code",$code)->first(); //firstOrFail
        if(empty($evse)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询参数,未找到数据 ");
            //返回数据
            $this->error();
        }
        $parameter = $evse->parameter;
        $parameter = json_decode($parameter, 1);
        if(!empty($parameter)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询参数 ");
            $data = array('card_rate'=>$parameter['card_rate'], 'card_time'=>$parameter['card_time'], 'coin_rate'=>$parameter['coin_rate'], 'power_base'=>$parameter['power_base'], 'channel_maximum_current'=>$parameter['channel_maximum_current'], 'disconnect'=>$parameter['disconnect']);
            $this->success($data);
        }
        $workeId = $evse->worker_id;
        if(empty($workeId)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询参数,workeId为空 ");
            $this->error();
        }
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 获取workeId:$workeId ");

        $parameter = new ServerGetParameter();
        $parameter->code(intval($code));
        $frame = strval($parameter);
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ",frame:".bin2hex($frame)."查询参数:$sendResult " . Carbon::now());
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询参数下发帧 frame: ".bin2hex($frame) );

        //记录log
        $content = "查询参数: code:$code".'-'.bin2hex($frame).'-'.Carbon::now();
        $this->record_log($code, $content);

        //返回下发结果
        if($sendResult){
            $this->success();
        }else{
            $this->error();
        }
    }

    //获取营业额
    public function getTurnover($evse){
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 营业额查询下发开始时间:".Carbon::now());
       //找到所有在线的桩
        //$evse = Evse::where("online_status",1)->all();
        if(empty($evse)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 营业额查询,未找到桩数据 ");
        }
        //实例化营业额类
        $turnover = new ServerGetTurnover();
        foreach ($evse as $v){
            if(empty($v->worker_id)){
                continue;
            }
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 营业额查询,code: $v->code, worker_id:$v->worker_id");
            $turnover->code(intval($v->code));
            $frame = strval($turnover);
            $sendResult  = EventsApi::sendMsg($v->worker_id,$frame);
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 营业额查询下发结果,code: $v->code, sendResult:$sendResult");
            usleep(500000); //睡眠0.5秒
        }
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 营业额查询下发结束时间:".Carbon::now());
    }

    //状态查询
    public function getStatusInfo(){
        $params = $this->request->all();
        $params = $params['params'];
        $code = $params['code'];
        //获取workeId
        $evse = Evse::where("code",$code)->first(); //firstOrFail
        if(empty($evse)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 状态查询,未找到数据 ");
            //返回数据
            $this->error();
        }
        $workeId = $evse->worker_id;
        if(empty($workeId)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 状态查询,workeId为空 ");
            $this->success();
        }
        //组装帧
        $workStatus = new ServerGetWorkStatus();
        $workStatus->code(intval($code));
        $frame = strval($workStatus);
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 状态查询,下发结果:sendResult:$sendResult, 下发帧: ".bin2hex($frame).'--'.Carbon::now());
    }

    //单通道时间查询
    public function getChannel(){
        $params = $this->request->all();
        $params = $params['params'];
        $code = $params['code'];
        $channelNum = $params['channelNum']; //通道号
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 单通道时间查询,时间 date: ".Carbon::now() );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 单通道时间查询 code: $code, channelNum:$channelNum " );

        //获取workeId
        $evse = Evse::where("code",$code)->first(); //firstOrFail
        $workeId = $evse->worker_id;
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 获取workeId:$workeId ");
        if(empty($workeId)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 单通道时间查询,workeId为空 ");
            return false;
        }
        //组装帧
        $singleChannel = new ServerSingleChannel();
        $singleChannel->code(intval($code));
        $singleChannel->channel_num($channelNum);
        $frame = strval($singleChannel);
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ",frame:".bin2hex($frame)."单通道时间查询:$sendResult " . Carbon::now());
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 单通道时间查询下发帧 frame: ".bin2hex($frame) );
        //记录log
        $content = "单通道时间查询: code:$code,channelNum:$channelNum".'-'.bin2hex($frame).'-'.Carbon::now();
        $this->record_log($code, $content);
        //返回下发结果
        if($sendResult){
            $this->success();
        }else{
            $this->error();
        }
    }

    //所有通道时间查询
    public function getAllChannel(){
        $params = $this->request->all();
        $params = $params['params'];
        $code = $params['code'];
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 所有通道时间查询,code: $code, 时间 date: ".Carbon::now() );

        //获取workeId
        $evse = Evse::where("code",$code)->first(); //firstOrFail
        $workeId = $evse->worker_id;
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 获取workeId:$workeId ");
        if(empty($workeId)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 所有通道时间查询,workeId为空 ");
            return false;
        }
        //组装帧
        $thoroughTime = new ServerAllThoroughTime();
        $thoroughTime->code(intval($code));
        $frame = strval($thoroughTime);
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ",frame:".bin2hex($frame)."所有通道时间查询:$sendResult " . Carbon::now());
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 所有通道时间查询下发帧 frame: ".bin2hex($frame) );
        //记录log
        $content = "所有通道时间查询: code:$code".'-'.bin2hex($frame).'-'.Carbon::now();
        $this->record_log($code, $content);
        //返回下发结果
        if($sendResult){
            $this->success();
        }else{
            $this->error();
        }
    }

    //后台营业额查询
    public function getBackstageTurnover(){
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
            $this->error();
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
        $this->success($data);
    }

    //查询ID
    public function getId(){
        $params = $this->request->all();
        $params = $params['params'];
        $code = $params['code'];
        if(empty($params) || empty($code)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询ID,参数为空 ");
            $this->error();
        }
        //获取workeId
        $evse = Evse::where("code",$code)->first(); //firstOrFail
        if(empty($evse)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询ID,未找到数据 ");
            //返回数据
            $this->error();
        }

        $workeId = $evse->worker_id;
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询ID:$workeId ");
        if(empty($workeId)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询ID,workeId为空 ");
            return false;
        }

        $getId = new ServerGetId();
        $getId->code(intval($code));
        $frame = strval($getId);
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ",frame:".bin2hex($frame)."查询ID:$sendResult " . Carbon::now());
        //返回下发结果
        if($sendResult){
            $this->success();
        }else{
            $this->error();
        }
    }

    //设置id
    public function setId(){
        $params = $this->request->all();
        $params = $params['params'];
        $code = $params['code'];
        if(empty($params) || empty($code)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 设置id,参数为空 ");
            $this->error();
        }
        //获取workeId
        $evse = Evse::where("code",$code)->first(); //firstOrFail
        if(empty($evse)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 设置id,未找到数据 ");
            //返回数据
            $this->error();
        }

        $workeId = $evse->worker_id;
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 获取workeId:$workeId ");
        if(empty($workeId)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 设置id,workeId为空 ");
            return false;
        }

        $setId = new ServerSetId();
        $setId->code(intval($code));
        $setId->device(intval($deviceId));
        $frame = strval($setId);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "frame：$frame");
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ",
            frame:".bin2hex($frame)."
            设置id:$sendResult " . date('Y-m-d H:i:s', time()));

        //返回下发结果
        if($sendResult){
            $this->success();
        }else{
            $this->error();
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
            $this->error();
        }

        $workeId = $evse->worker_id;
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 获取workeId:$workeId ");
        if(empty($workeId)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 清空营业额,workeId为空 ");
            return false;
        }

        $dateTime = new ServerGetDateTime();
        $dateTime->code(intval($code));
        $frame = strval($dateTime);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "frame：$frame");
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ",
            frame:".bin2hex($frame)."
            查询时间:$sendResult " . date('Y-m-d H:i:s', time()));

        //返回下发结果
        if($sendResult){
            $this->success();
        }else{
            $this->error();
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

    //成功返回
    private function success($data=[]){
        return $this->response->array(
            [
                'status_code' => 200,
                'message' => "command send sucesss",
                "data"=>$data
            ]
        );
    }

    //失败返回
    private function error($data=[]){
        return $this->response->array(
            [
                'status_code' => 500,
                'message' => "command send failed",
                "data"=>$data
            ]
        );
    }



}