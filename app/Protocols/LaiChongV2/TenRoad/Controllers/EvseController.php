<?php
namespace Wormhole\Protocols\LaiChongV2\TenRoad\Controllers;
/**
 * Created by PhpStorm.
 * User: chao
 * Date: 2018-10-9
 * Time: 17:47
 */

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Wormhole\Http\Controllers\Controller;
use Wormhole\Protocols\LaiChongV2\TenRoad\Models\Evse;
use Wormhole\Protocols\LaiChongV2\TenRoad\Models\Port;
use Wormhole\Protocols\LaiChongV2\TenRoad\Models\Turnover;
use Wormhole\Protocols\LaiChongV2\TenRoad\Models\ModifyInfoLog;
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol;
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Frame;
use \Curl\Curl;
use Wormhole\Protocols\LaiChongV2\TenRoad\Controllers\ProtocolController;
use Wormhole\Protocols\LaiChongV2\TenRoad\EventsApi;

//检测启动续费停止是否收到桩响应
use Wormhole\Protocols\LaiChongV2\TenRoad\Jobs\CheckResponse;
use Illuminate\Support\Facades\Queue;

//自动停止上报,如果monitor未成功接收到数据,继续队列发送
use Wormhole\Protocols\LaiChongV2\TenRoad\Jobs\AutoStopCharge;

//日结上报,如果monitor未成功接收到数据,继续队列发送
use Wormhole\Protocols\LaiChongV2\TenRoad\Jobs\Report;

//心跳队列
use Wormhole\Protocols\LaiChongV2\TenRoad\Jobs\CheckHeartbeat;

use Illuminate\Support\Facades\Redis;
use Wormhole\Protocols\Library\Log as Logger;

use Wormhole\Protocols\MonitorServer;
//订单映射表
use Wormhole\Protocols\LaiChongV2\TenRoad\Models\ChargeOrderMapping;
//充电记录表
use Wormhole\Protocols\LaiChongV2\TenRoad\Models\ChargeRecords;


//签到
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\Sign as ServerSign;
//自动停止
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\AutomaticStop as ServerAutomaticStop;
//日结
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\Report as ServerReport;
//心跳
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\Heartbeat as EvseHeartbeat;
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\Heartbeat as ServerHeartbeat;

//启动充电
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\StartCharge as ServerStartCharge;
//续费
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\Renew as ServerRenew;
//停止充电
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\StopCharge as ServerStopCharge;

class EvseController extends Controller
{
    public function __construct()
    {

    }
    //心跳监控
    public function checkHeartbeat($id)
    {
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳监控START id : $id ");
        //查询当前桩的心跳周期
        $hearbeat = Evse::where('id', $id)->first();
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 当前时间: " . time() . "上次心跳时间:" . strtotime($hearbeat->last_update_status_time) . "心跳周期:" . $hearbeat->heartbeat_cycle);
        //如果心跳没在心跳周期时间上报,则判断掉线
        if ((time() - strtotime($hearbeat->last_update_status_time)) < 180) { //$hearbeat->heartbeat_cycle * 60
            $hearbeat->online_status = 1;
            $hearbeat->save();
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳正常, END ");
        } else {
            //告诉monitor掉线
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 断线 code: " . $hearbeat->code);
            $code = [$hearbeat->code];
            $monitorCodes = MonitorServer::offline($code);
            if (empty($monitorCodes)) {
                Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 断线调用monitor失败, END ");
            }
            //更新桩状态字段,充电桩掉线
            $hearbeat->online_status = 0;
            $hearbeat->save();
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " END 心跳超时");
        }
        return true;
    }

    //设置参数
    public function checkSetParameter($parameterName, $parameter, $code)
    {
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 设置参数检查start ");
        //通过code找到设置参数结果
        $evse = Evse::where('code', $code)->first();
        $requestResult = $evse->request_result;
        $requestResult = json_decode($requestResult);
        //如果是设置域名和端口号
        if (is_array($parameterName) && is_array($parameter)) {
            $p1 = $parameterName[0];
            $p2 = $parameterName[1];
            if ($requestResult->$p1 == 1 && $requestResult->$p2 == 1) {
                Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 设置参数成功 ");
                $evse->$p1 = $parameter[0];
                $evse->$p2 = $parameter[1];
                $requestResult->$p1 = 0;
                $requestResult->$p2 = 0;
                $evse->request_result = json_encode($requestResult);
                $evse->save();

                //记录修改日志
                $log = ModifyInfoLog::create([
                    'before_info' => $evse->$p1 . ' ' . $evse->$p2,
                    'after_info' => $parameter[0] . ' ' . $parameter[1],
                    'remarks' => '修改内容为域名和端口',
                    'modify_time' => date('Y-m-d H:i:s', time())
                ]);
                Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 域名端口改变,记录日志结果 $log ");
                //设置成功调用montor
                return true;
            }
            //设置成功调用montor
            return false;
        }

        //设置参数
        if (is_array($parameter)) {
            $evse = Evse::where('code', $code)->first();
            //如果收到响应
            if ($requestResult->$parameterName == 1) {
                Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 设置参数成功 ");
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
        if ($requestResult->$parameterName == 1) {
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 设置参数成功 ");
            $oldParameter = $evse->$parameterName;
            $evse->$parameterName = $parameter;
            $requestResult->$parameterName = 0;
            $evse->request_result = json_encode($requestResult);
            $evse->save();

            //记录修改日志
            $log = ModifyInfoLog::create([
                'before_info' => $oldParameter,
                'after_info' => $parameter,
                'remarks' => '修改内容为心跳周期',
                'modify_time' => date('Y-m-d H:i:s', time())
            ]);
            Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳改变,记录日志结果 $log ");
            //设置成功调用montor
            return true;
        }
        //设置失败调用montor
        return false;
    }

    //检测启动续费停止是否收到桩响应
    public function response($code, $orderId, $type, $workeId, $frame)
    {
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 监控启动续费或者退费start ");
        $condition = [
            ['code', '=', $code],
            ['order_id', '=', $orderId]
        ];
        $port = Port::where($condition)->first();//firstOrFail
        if (empty($port)) {
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 监控启动续费或者退费没有找到相应数据 code:$code, orderId:$orderId ");
            return false;
        }

        $isResponse = $port->is_response;
        if ($isResponse) {
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 收到桩响应 ");
            return true;
        } else {
            //查询下发次数,如果小于三次则继续下发,否则停止下发,调用monitor接口
            $operationTime = $port->operation_time;
            if ($operationTime < 3) {
                $sendResult = EventsApi::sendMsg($workeId, base64_decode($frame));
                //下发次数加1
                $port->operation_time = ++$operationTime;
                $port->save();
                Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 监控启动续费或者退费，未达到3次,继续下发, 第 $operationTime 次下发, 下发结果sendResult:$sendResult ");

                $job = (new CheckResponse($code, $orderId, $type, $workeId, $frame))
                    ->onQueue(env("APP_KEY"))
                    ->delay(Carbon::now()->addSeconds(3));
                dispatch($job);
            } else {
                //调用monitor接口,启动续费或者退费失败
                if ($type == 1) { //启动
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 启动未收到响应 ");
                } elseif ($type == 2) { //续费
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 续费未收到响应 ");
                } elseif ($type == 3) { //停止
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 停止未收到响应 ");
                }
            }
        }

    }

    //***************************************桩主动上报***********************************//

    //签到
    public function signReport($code, $num, $worker_id, $version)
    {
        //枪口列表
        $portArr = [];
        Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到,处理数据start ");
        //桩是否存在
        $evse = Evse::where("code", $code)->first(); //firstOrFail
        //找不到添加桩和枪
        if (empty($evse)) {
            for ($i = 0; $i < $num; $i++) {
                $portArr[] = $i;
            }
            //从moniotr获取monitorCode
            $monitorCodes = MonitorServer::deviceOnline($code, $num, $portArr, $version);
            if (!is_array($monitorCodes)) {
                Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到,添加桩和枪,调用monitor失败 code:$code ");
                return false;
            }
            //初始化request_result
            $data = ['heartbeat_cycle' => 0, 'port_number' => 0, 'threshold' => 0, 'parameter' => 0, 'device_id' => 0];
            $data = json_encode($data);
            $evse = Evse::create([
                'code' => $code,
                'worker_id' => $worker_id,//$this->workerId,
                'protocol_name' => \Wormhole\Protocols\LaiChongV2\TenRoad\Protocol::NAME,
                'channel_num' => $num,
                'online_status' => 1,
                'last_update_status_time' => Carbon::now(),
                'request_result' => $data
            ]);
            //如果创建桩失败,返回false
            if (empty($evse)) {
                Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 创建桩失败,code:$code ");
                return false;
            }

            //添加枪信息
            for ($i = 0; $i < $num; $i++) {
                $port = Port::create([
                    'evse_id' => $evse->id,
                    'code' => $code,
                    'monitor_code' => $monitorCodes[$i],
                    'port_number' => $i
                ]);
                //如果通道创建失败,返回false
                if (empty($port)) {
                    Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 创建通道 $i 失败 ");
                    return false;
                }
            }
            Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到,添加桩和枪,调用monitor成功 code:$code ");
            //应答充电桩
            $this->signResponse($code, $worker_id);
            return true;
        }

        //更新桩数据
        $evse->worker_id = $worker_id;
        $evse->channel_num = $num;
        $evse->online_status = 1; //在线
        $res = $evse->save();
        if (empty($res)) {
            Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到,更新数据失败 ");
            return false;
        }

        //应答充电桩
        $this->signResponse($code, $worker_id);

        //调用monitor接口
        $monitorCodes = MonitorServer::deviceOnline($code, $num, array(), $version);

        //如果调用monitor成功,应答充电桩
        if ($monitorCodes) {
            Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到,调用monitor成功 ");
            return true;
        } else {
            Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到,调用monitor失败 ");
            return false;
        }
    }

    //应答充电桩
    public function signResponse($code, $worker_id)
    {
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到应答桩start " . Carbon::now());
        $sign = new ServerSign();
        $sign->code($code);
        $sign->result(1);
        $frame = strval($sign);
        $sendResult = EventsApi::sendMsg($worker_id, $frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到响应充电桩结果,sendResult:$sendResult " . Carbon::now());
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到,响应充电桩 结果 $sendResult, 帧frame: " . bin2hex($frame));
    }

    //桩上报心跳处理
    public function heartBeatReport($code, $client_id, $lock_status, $work_status, $fault_status)
    {
        //如果桩不在线,先做上线处理
        Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳业务处理start ");

        //查找是否有此桩
        $evse = Evse::where("code", $code)->first();
        if (empty($evse)) {
            Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳,处理数据,桩编号不存在 code: $code");
            return false;
        }
        //如果有此桩
        if (!empty($evse)) {
            //更新此桩数据
            $evse->last_update_status_time = Carbon::now(); //最后更新充电状态时间
            $evse->online_status = 1; //是否离线 0/离线,1/在线
            $evse_res = $evse->save();
            if (empty($evse_res)) {
                Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳,处理数据,更新桩数据失败 code: $code");
            }
        }

        ////monitor那边1是空闲，2是充电
        $statu = array(1, 2);
        //更新枪数据
        for ($i = 0; $i < 10; $i++) {
            $condition = [
                ['code', '=', $code],
                ['port_number', '=', $i]
            ];
            $status[$i]['device_no'] = $code;
            $status[$i]['gun_num'] = $i;
            $status[$i]['work_status'] = $statu[$work_status[$i]];
            $port = Port::where($condition)->first();
            if (!empty($port)) {
                //如果状态上报的是空闲，表中的状态不是空闲则更改为空闲状态
                if ($work_status[$i] == 0 && $port->work_status != 0) {
                    $port->work_status = $work_status[$i];
                    $status[$i]['work_status'] = $statu[$work_status[$i]];
                    //上报的是空闲状态,表中状态是充电中则表示是自动停止
//                    $job = (new AutoStopReport($code, $order_number, $start_type, $left_time, $stop_reason))
//                        ->onQueue(env("APP_KEY"));
//                    dispatch($job);
                }

                //判断是否故障
                if ($fault_status[$i] == 1) {
                    //心跳工作状态故障
                    $status[$i]['work_status'] = 3;//故障
                    //如果故障,判断是否已经给过monitor
                    $fault = Redis::get($code.$i . ':fault');
                    if ($fault != 1) {
                        Redis::set($code.$i . ':fault', 1);
                        MonitorServer::add_device_error_log($code, $i, 3);//3为故障
                    }
                } else {
                    //枪口故障恢复正常,判断是否已经给过monitor
                    $fault = Redis::get($code.$i . ':fault');
                    if ($fault != 2) {
                        Redis::set($code.$i . ':fault', 2);
                        MonitorServer::add_device_error_log($code, $i, 1); //1为空闲
                    }
                }
                //更新枪表字段
                $port_res = $port->save();
                if (empty($port_res)) {
                    Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳,处理数据,更新枪数据失败 code:$code, port: $i ");
                }
            }
        }

        //心跳应答
        $res = $this->hearbeatAnswer($code, $client_id);

        //调用monitor接口
        $monitorCodes = MonitorServer::hearbeat($status);
        if ($monitorCodes) {
            Log::debug(__CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳调用monitor成功 ");
        } else {
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

    //心跳应答
    public function hearbeatAnswer($code, $client_id){
        //应答充电桩
        $hearbeat = new ServerHeartbeat();
        $hearbeat->code($code);
        $frame = strval($hearbeat);
        $sendResult  = EventsApi::sendMsg($client_id,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " frames: ".bin2hex($frame));
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 多个心跳应答桩sendResult:$sendResult " . Carbon::now());
        return $sendResult;
    }

    //营业额查询
    public function getTurnover($code, $coin_num, $card_cost){
        //前一天
        $frontDate = date("Y-m-d",strtotime("-1 day"));
        $condition = [
            ['code', '=', $code],
            ['stat_date', '=', $frontDate]
        ];
        //如果收到数据则更新
        if( !empty($code) &&  is_numeric($coin_num) &&  is_numeric($card_cost)){
            //获取前一天的数据
            $turnover = Turnover::where($condition)->first();
            //如果有则直接更新,如果没有则创建
            if(!empty($turnover)){
                $turnover->coin_number = $coin_num;
                $turnover->card_free = $card_cost;
                $res = $turnover->save();
                if(empty($res)){
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 营业额查询更新失败 res:$res ");
                }
            }else{
                $res = Turnover::create([
                    'code'=>$code,
                    'coin_number'=>$coin_num,
                    'card_free'=>$card_cost,
                    'stat_date'=>$frontDate
                ]);
                if(empty($res)){
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 营业额查询创建失败 res:$res ");
                }
            }
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 营业额查询成功 res:$res ");

            //计算出当天使用电量,当天投币金额,当天刷卡金额
            //获得上一次上报的数据
            $turnoverData = Turnover::where([['stat_date', '<', $frontDate],['code', $code]])
                ->orderBy('stat_date', 'desc')
                ->first();
            //是否找到数据
            $before_charged_power = 0; //前一天电量
            $before_coin = 0; //前一天投币金额
            $before_card = 0; //前一天刷卡金额
            if(!empty($turnoverData)){
                Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 日结,获取到上一次上报数据 ");
                $before_charged_power = $turnoverData->charged_power;
                $before_coin = $turnoverData->coin_number;
                $before_card = $turnoverData->card_free;
            }

            //当天的数据减去之前的数据
            $charged_power = $turnover->charged_power; //当天总电量
            $current_charged_power = ($charged_power - $before_charged_power);//当天总电量减去前一天总电量
            $current_coin = ($coin_num - $before_coin);//当天投币金额减去前一天投币金额
            $current_card = ($card_cost - $before_card);//当天刷卡金额减去前一天刷卡金额

            //将数据存到数据库
            $turnover->current_charged_power = $current_charged_power;
            $turnover->current_coin_number = $current_coin;
            $turnover->current_card_free = $current_card;
            $res = $turnover->save();

            //调用monitor

        }
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 营业额查询数据异常 ");
        return false;
    }

    //************************************服务器下发**************************************************//

    //下发启动充电
    public function startChargeSend($monitor_order_id, $monitor_code, $port_numbers, $charge_time, $order_no){
        //更新枪数据
        $port = Port::where('monitor_code',$monitor_code)->first();//firstOrFail
        if(empty($port)){
            //启动失败调用monitor接口
            $result = MonitorServer::start_charge_failed_response($monitor_order_id);
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 启动充电,未找到枪,调用monitor接口 monitor_code:$monitor_code, monitor_order_id:$monitor_order_id, port_numbers:$port_numbers, charge_time:$charge_time, order_no:$order_no, result:$result ");
            return false;
        }
        $port_number = $port->port_number; //枪口号
        $code = $port->code;//桩编号
        $workeId = $port->worker_id;  //workId

        $port->monitor_order_id = $monitor_order_id;
        $port->order_id = $order_no;
        $port->work_status = 1; //状态,启动中
        $port->start_time = Carbon::now();
        $port->charge_args = $charge_time;
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
        //判断workid是否为空
        if(empty($workeId)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 启动充电,workeId为空 ");
            return false;
        }

        //组装启动充电帧
        $startCharge = new ServerStartCharge();
        $startCharge->code(intval($code));
        $startCharge->order_number(intval($order_no));
        $startCharge->channel_number(intval($port_numbers));
        $startCharge->charge_time(intval($charge_time));
        //下发启动充电
        $frame = strval($startCharge);
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 启动充电下发帧,frame: ".bin2hex($frame) );
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", frame:".bin2hex($frame)."启动充电结果:$sendResult " . Carbon::now());

        //记录log
        $fiel_data = " 启动充电,时间: ".Carbon::now().PHP_EOL." 启动充电,参数 monitor_order_id:$monitor_order_id, monitor_code:$monitor_code, port_numbers:$port_numbers, charge_time:$charge_time, order_no:$order_no ".PHP_EOL."启动充电帧: $frame, 下发帧结果,sendResult: $sendResult";
        $redis_data = "启动充电下发".json_encode(array('monitor_order_id'=>$monitor_order_id, 'monitor_code'=>$monitor_code, 'port_numbers'=>$port_numbers, 'charge_time'=>$charge_time, 'order_no'=>$order_no)).'-'.bin2hex($frame).'-'.Carbon::now().'+';
        $this->record_log($code, $fiel_data, $redis_data);

        //类型1启动2续费3停止
        $type = 1;
        $frame = base64_encode($frame);

        //下发失败调用monitor
        if(empty($sendResult)){
            //启动失败调用monitor接口
            $result = MonitorServer::start_charge_failed_response($monitor_order_id);
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 启动充电,下发失败,调用monitor结果 result:$result,  monitor_code:$monitor_code, monitor_order_id:$monitor_order_id, order_no:$order_no ");
            return false;
        }

        //如果3秒内没有收到响应,则重发,最多三次
        $job = (new CheckResponse($code, $order_no, $type, $workeId, $frame))
            ->onQueue(env("APP_KEY"))
            ->delay(Carbon::now()->addSeconds(7));
            dispatch($job);
    }

    //续费下发
    public function renewSend($monitor_order_id, $monitor_code, $port_numbers, $charge_time, $order_no){
        //通过订单号找到code和port_number
        $port = Port::where('monitor_code',$monitor_code)->first();//firstOrFail
        if(empty($port)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 续费,未找到枪 monitorCode:$monitor_code ");
            return false;
        }
        //$channelNumber = $port->port_number; //枪口号
        $code = $port->code;                  //桩编号
        $workeId = $port->evse->worker_id;  //workId
        //worke_id是空,调用monitor
        if(empty($workeId)){
            //续费未找到worke_id调用monitor接口
            $result = MonitorServer::continue_charge_failed_response($monitor_order_id);
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 续费,workeId为空,调用monitor结果:result:$result ");
            return false;
        }

        //临时存储续费monitor订单号,收到续费响应后,把此单号给monitor
        Redis::set("$code.$port_numbers",$monitor_order_id);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " order_no:$order_no ");

        //存储续费值
        $port->renew = $charge_time;
        $port->is_response = 0;
        $port->operation_time = 0;
        $port->save();

        //添加orderMap数据
        ChargeOrderMapping::create([
            'evse_id'=>$port->evse_id,
            'port_id'=>$port->id,
            'code'=>$port->code,
            'monitor_order_id'=>$monitor_order_id,
            'order_id'=>$port->order_id,
            'charge_args'=>$charge_time,
        ]);

        //组装续费帧
        $renew = new ServerRenew();
        $renew->code(intval($code));
        $renew->order_number(intval($order_no));
        $renew->channel_number(intval($port_numbers));
        $renew->charge_time(intval($charge_time));

        //续费下发
        $frame = strval($renew);
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 续费下发帧,frame: ".bin2hex($frame) );
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 
            frame:".bin2hex($frame)."
            续费结果:$sendResult " . Carbon::now());

        //记录log
        $fiel_data = " 续费下发参数,monitor_code:$monitor_code, monitor_order_id:$monitor_order_id, port_numbers:$port_numbers, charge_time:$charge_time, order_no:$order_no".PHP_EOL."frame: ".bin2hex($frame).'时间:'.Carbon::now();
        $redis_data = " 续费下发".'-'.json_encode(array('monitor_order_id'=>$monitor_order_id, 'monitor_code'=>$monitor_code, 'port_numbers'=>$port_numbers, 'charge_time'=>$charge_time, 'order_no'=>$order_no)).'-'.bin2hex($frame).'-'.Carbon::now().'+';
        $this->record_log($code, $fiel_data, $redis_data);

        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 续费下发帧结果,sendResult: $sendResult" );

        //续费下发失败调用monitor
        if(empty($sendResult)){
            $result = MonitorServer::continue_charge_failed_response($monitor_order_id);
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 续费,下发失败 ");
        }

        //类型1启动2续费3停止
        $type = 2;
        $frame =  base64_encode($frame);
        //如果3秒内没有收到响应,则重发,最多三次
        $job = (new CheckResponse($code, $order_no, $type, $workeId, $frame))
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

        //组装停止充电帧
        $stop = new ServerStopCharge();
        $stop->code(intval($code));
        $stop->order_number(intval($orderId));
        $stop->channel_number(intval($channelNumber));

        //停止下发
        $frame = strval($stop);
        $sendResult  = EventsApi::sendMsg($workeId,$frame);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 
            frame:".bin2hex($frame)."
            停止充电结果:$sendResult " . Carbon::now());

        //记录log
        $fiel_data = " 停止充电参数,monitorOrderId:$monitorOrderId ".PHP_EOL." frame: ".bin2hex($frame).PHP_EOL."  时间: ".Carbon::now();
        $redis_data = "停止充电:".'-'. json_encode(array('monitorOrderId'=>$monitorOrderId, 'code'=>$code, 'orderId'=>$orderId, 'channelNumber'=>$channelNumber, 'serialNumber'=>$serialNumber)) .'-'.bin2hex($frame).'-'.Carbon::now().'+';
        $this->record_log($code, $fiel_data, $redis_data);

        //下发失败调用monitor
        if(empty($sendResult)){
            //停止失败,调用monitor接口
            $result = MonitorServer::stop_charge_failed_response($monitorOrderId);
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "停止充电下发失败,调用monitor结果result:$result sendResult:$sendResult");
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
    public function stopChargeResponse($code, $order_no, $left_time){
        //通过code和order_id获取启动信息
        $condition = [
            ['code', '=', $code],
            ['order_id', '=', $order_no]
        ];
        $port = Port::where($condition)->first();

        if(empty($port)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 
            停止充电收到响应,未找到数据
            code:$code, order_no:$order_no, result:$result " . Carbon::now());
            return false;
        }
        $chargeArgs = $port->charge_args;
        //判断是否收到了一次响应,如果收到就不再接收
        $isResponse = $port->is_response;
        if($isResponse == 1){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 
            停止充电收到响应,已接收过一次,
            code:$code, order_no:$order_no, left_time:$left_time " . Carbon::now());
            return true;
        }

        $monitorOrderId = $port->monitor_order_id;
        $chargeOrderMapping = ChargeOrderMapping::where('monitor_order_id', $monitorOrderId)->first();
        //如果未找到数据
        if(empty($chargeOrderMapping)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 停止收到响应, 未找到响应数据:monitorOrderId:$monitorOrderId ");
            return false;
        }
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 停止响应结果:left_time:$left_time ");
        //如果停止成功
        if($left_time >= 0){
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
                Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 停止响应成功,未保存充电记录:left_time:$left_time ");
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
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 收到停止响应, 停止失败:left_time:$left_time ");
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

    //记录log,包括参数,帧,时间
    private function record_log($code, $fiel_data, $redis_data){
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . $fiel_data );
        //记录log
        $parameter = $code.uniqid(mt_rand(),1);
        Redis::set($parameter,$redis_data,'EX',86400);
    }


}

