<?php
namespace Wormhole\Protocols\LaiChongV2\TenRoad;
    /**
     * This file is part of workerman.
     *
     * Licensed under The MIT License
     * For full copyright and license information, please see the MIT-LICENSE.txt
     * Redistributions of files must retain the above copyright notice.
     *
     * @author walkor<walkor@workerman.net>
     * @copyright walkor<walkor@workerman.net>
     * @link http://www.workerman.net/
     * @license http://www.opensource.org/licenses/mit-license.php MIT License
     */

    /**
     * 用于检测业务代码死循环或者长时间阻塞等问题
     * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
     * 然后观察一段时间workerman.log看是否有process_timeout异常
     */
//declare(ticks=1);
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Wormhole\Protocols\BaseEvents;
use Wormhole\Protocols\Tools;

use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Frame;

use Wormhole\Protocols\LaiChongV2\TenRoad\Controllers\ProtocolController;
use Wormhole\Protocols\LaiChongV2\TenRoad\Models\Evse;
use Wormhole\Protocols\LaiChongV2\TenRoad\Models\Port;

use Wormhole\Protocols\MonitorServer;

//签到
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\Sign as EvseSign;
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\Sign as ServerSign;

//心跳
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\Heartbeat as EvseHeartbeat;
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\Heartbeat as ServerHeartbeat;

//桩自动停止
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\AutomaticStop as EvseAutomaticStop;
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\AutomaticStop as ServerAutomaticStop;

//状态上报
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\Statusreport as EvseStatusreport;

//日结
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\Report as EvseReport;
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\Report as ServerReport;
//启动充电
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\StartCharge as ServerStartCharge;
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\StartCharge as EvseStartCharge;

//续费
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\Renew as ServerRenew;
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\Renew as EvseRenew;

//停止充电
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\StopCharge as ServerStopCharge;
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\StopCharge as EvseStopCharge;

//心跳设置
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\SetHearbeat as ServerSetHearbeat;
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\SetHearbeat as EvseHearbeat;

//服务器信息设置
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\ServerInfo as ServerInfo;
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\ServerInfo as EvseServerInfo;

//清空营业额
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\EmptyTurnover as ServerEmptyTurnover;
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\EmptyTurnover as EvseEmptyTurnover;

//设置参数
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\SetParameter as ServerSetParameter;
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\SetParameter as EvseSetParameter;

//设置ID
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\SetId as ServerSetId;
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\SetId as EvseSetId;

//心跳查询
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\GetHearbeat as ServerGetHeartbeat;
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\GetHearbeat as EvseGetHeartbeat;

//电表抄表
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\ReadMeter as ServerReadMeter;
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\ReadMeter as EvseReadMeter;

//营业额查询
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\GetTurnover as ServerGetTurnover;
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\GetTurnover as EvseGetTurnover;

//通道查询
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\GetChannelStatus as ServerGetChannelStatus;
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\GetChannelStatus as EvseGetChannelStatus;

//查询参数
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server\GetParameter as ServerGetParameter;
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\GetParameter as EvseGetParameter;

//设置时间
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\SetDateTime as EvseSetDateTime;

//查询时间
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\GetDateTime as EvseGetDateTime;

//查询ID
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\GetId as EvseGetId;

//查询设备识别号
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\GetDeviceIdentification as EvseGetDeviceIdentification;

use Illuminate\Support\Facades\Redis;

use Wormhole\Protocols\Library\Log as Logger;


//签到
use Wormhole\Protocols\LaiChongV2\TenRoad\Jobs\SignReport;
//自动停止
use Wormhole\Protocols\LaiChongV2\TenRoad\Jobs\AutoStopReport;
//日结
use Wormhole\Protocols\LaiChongV2\TenRoad\Jobs\TurnoverReport;
//心跳
use Wormhole\Protocols\LaiChongV2\TenRoad\Jobs\HeartBeatReport;

//启动充电响应
use Wormhole\Protocols\LaiChongV2\TenRoad\Jobs\StartChargeResponse;
//续费响应
use Wormhole\Protocols\LaiChongV2\TenRoad\Jobs\RenewResponse;
//停止充电响应
use Wormhole\Protocols\LaiChongV2\TenRoad\Jobs\StopChargeResponse;

/**
 * 主逻辑
 * 主要是处理 onConnect onMessage onClose 三个方法
 * onConnect 和 onClose 如果不需要可以不用实现并删除
 */
class EventsApi extends BaseEvents
{
    public static $client_id = '';
    public static $controller;
    /**
     * @param string $client_id 连接id
     * @param mixed $message 具体消息
     * @return bool
     */
    public static function message($client_id, $message)
    {
        self::$client_id = $client_id;
        self::$controller = new ProtocolController($client_id);
        Log::debug(__NAMESPACE__ . "\\" . __CLASS__ . "\\" . __FUNCTION__ . "@" . __LINE__ . "  client_id:$client_id, message:" . bin2hex($message));

        //帧解析
        $frame = new Frame();
        $frame = $frame($message);
        //判断帧是否正确
        if(empty($frame)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " " . " 帧格式不正确 ");
            return false;
        }
        //指令
        $operator = $frame->operator->getValue();
        //设备类型
        $type = $frame->type->getValue();
        //互联网模块设备类型
        $internet_type = 0x01;
        //C款充电桩主板
        $main_board_type = 0x10;
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " " . " operator:,".$operator.' type:'.$type." isValid:$frame->isValid");
        if (!empty($frame)) {
            switch ($operator.$type) {

                /*****************************************桩主动上报****************************************************/
                case (0x11.$internet_type):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到 " );
                    self::sign($message);
                    break;
                case (0x21.$internet_type):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳 " );
                    self::hearbeat($message);
                    break;
                case (0x11.$main_board_type):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 通道状态,通道状态发生变化时主动上传 " );
                    self::report($message);
                    break;

                /*****************************************控制类上报****************************************************/
                case (0x1201):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 启动充电 " );
                    self::start_charge_response($message);
                    break;
                case (0x1202):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 续费 " );
                    self::renew_response($message);
                    break;
                case (0x1203):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 停止充电 " );
                    self::stop_charge_response($message);
                    break;

                /*****************************************设置类上报****************************************************/
                case (0x22.$internet_type):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳设置 " );
                    self::set_hearbeat_response($message);
                    break;
                case (0x31.$internet_type):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 端口设置 " );
                    self::set_server_info_response($message);
                    break;
                case (0x41.$internet_type):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 连接阈值设置 " );
                    self::set_server_info_response($message);
                    break;

                /*****************************************查询类上报****************************************************/
                case (0x23.$internet_type):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳查询 " );
                    self::get_hearbeat_response($message);
                    break;
                case (0x42.$internet_type):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 连接阈值查询 " );
                    self::get_hearbeat_response($message);
                    break;
                case (0x52.$internet_type):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 电表抄表成功 " );
                    self::get_meter_response($message);
                    break;
                case (0x53.$internet_type):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 电表抄表失败 " );
                    self::get_meter_response($message);
                    break;
                case (0x43.$internet_type):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询信号强度 " );
                    self::get_signal_response($message);
                    break;
                case (0x33.$main_board_type):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 营业额查询 " );
                    self::get_turnover_response($message);
                    break;
                case (0x34.$main_board_type):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 通道时间 " );
                    self::get_channel_response($message);
                    break;
                case (0x36.$main_board_type):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 所有通道时间 " );
                    self::get_channel_response($message);
                    break;
                case (0x35.$main_board_type):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 上传状态 " );
                    self::get_channel_response($message);
                    break;
                case (0x31.$main_board_type):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询参数 " );
                    self::get_parameter_response($message);
                    break;
                case (0x32.$main_board_type):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询ID " );
                    self::get_id_response($message);
                    break;
                case (0xE0.$main_board_type):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 应答 " );
                    self::get_date_time_response($message);
                    break;
            }
        }
    }


    /*****************************************桩主动上报****************************************************/

    //签到
    private static function sign($message)
    {
        $sign = new EvseSign();
        $sign($message);
        //接收数据
        $version = $sign->version->getValue();//版本号
        $code = $sign->code->getValue(); //桩编号
        $num = $sign->num->getValue();   //枪口数量
        $heabeatCycle = $sign->heabeat_cycle->getValue();
        $device_identification = $sign->device_identification->getValue(); //设备编号

        //判断接收数据是否正确
        if(empty($code) || !is_numeric($num) || empty($device_identification) || empty($version)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到上报,数据不正确, code:$code, num:$num, device_identification:$device_identification, version:$version ");
            return false;
        }

        //每次签到重置一下流水号
        Redis::set($code.':serial_number',1);

        //记录对应某个桩的log
        $file_data = " 签到,桩上报时间 date: ".Carbon::now().PHP_EOL." 签到,桩上报参数 code:$code, num:$num, device_identification:$device_identification, heabeatCycle:$heabeatCycle, version:$version ".PHP_EOL." 签到,桩上报帧 frame: ".bin2hex($message);
        $redis_data = "签到,桩上报".'-'.json_encode(array('code'=>$code, 'num'=>$num, 'heabeatCycle'=>$heabeatCycle, 'device_identification'=>$device_identification, 'version'=>$version)).'-'.bin2hex($message).'-'.Carbon::now().'+';
        self::record_log($code, $file_data, $redis_data);

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", num:$num, code:$code, device_identification:$device_identification, heabeatCycle:$heabeatCycle, version:$version " . Carbon::now());


        //处理签到上报数据并应答桩SignReport
        $job = (new SignReport($code, $num, $device_identification, $heabeatCycle, self::$client_id, $version))
            ->onQueue(env("APP_KEY"));
        dispatch($job);




    }


    //心跳
    private static function hearbeat($message){

        
        $data = [];
        $hearbeat = new EvseHeartbeat();
        $frame_load = [$hearbeat($message)];
        $clientId = self::$client_id;
        //如果是个数组,表示多个心跳，否则一个心跳
        $evse_num = count($frame_load); //上报心跳数量
        foreach ($frame_load as $k=>$frame_obj){

            $data[$k]['code'] = $frame_obj->code->getValue();
            $data[$k]['num'] = $frame_obj->info->num; //枪口数量
            $data[$k]['signal'] = $frame_obj->info->signal;
            for($i=0;$i<$data[$k]['num'];$i++){
                $data[$k]['current'][$i] = $frame_obj->info->data[$i]['current']->getValue() / 1000;      //电流
                $data[$k]['left_time'][$i] = $frame_obj->info->data[$i]['left_time']->getValue();  //剩余时间
                $status = $frame_obj->info->data[$i]['status']->getValue();                        //设备状态
                $data[$k]['status']['worke_state'][$i] = $status['worke_state'];                   //工作状态
                $data[$k]['status']['fuses'][$i] = $status['fuses'];                                 //熔断
                $data[$k]['status']['overcurrent'][$i] = $status['overcurrent'];                    //过流
                $data[$k]['status']['connect'][$i] = $status['connect'];                             //连接
                $data[$k]['status']['full'][$i] = $status['full'];                                    //充满

                $data[$k]['status']['start_up'][$i] = $status['start_up'];                            //启动
                $data[$k]['status']['pull_out'][$i] = $status['pull_out'];                            //拔出

                Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", evse_num:$evse_num, signal:".$data[$k]['signal']."
            num:".$data[$k]['num'].", code:".$data[$k]['code'].", current:".$data[$k]['current'][$i].", left_time:".$data[$k]['left_time'][$i].", worke_state:".$data[$k]['status']['worke_state'][$i]."
             fuses:".$data[$k]['status']['fuses'][$i].", overcurrent:".$data[$k]['status']['overcurrent'][$i].", connect:".$data[$k]['status']['connect'][$i].
                    ", full:".$data[$k]['status']['full'][$i]."start_up:".$data[$k]['status']['start_up'][$i]."pull_out".$data[$k]['status']['pull_out'][$i]."" . date('Y-m-d H:i:s', time()));
            }

        }


        //处理签到上报数据并应答桩HeartBeatReport
        $job = (new HeartBeatReport($data, $evse_num, $clientId))
            ->onQueue(env("APP_KEY"));
        dispatch($job);



    }

    //桩自动停止
    private static function auto_stop($message){

        $automatic = new EvseAutomaticStop();
        $frame_load = $automatic($message);
        $code = $frame_load->code->getValue();

        //接收数据 订单号 剩余时间 停止原因
        $order_number = $frame_load->order_number->getValue();
        $start_type = $frame_load->start_type->getValue();
        $left_time = $frame_load->left_time->getValue();
        $stop_reason = $frame_load->stop_reason->getValue();

        //判断接收参数是否正确
        if(!is_numeric($order_number) || !is_numeric($left_time) || !is_numeric($stop_reason) || !is_numeric($start_type)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 桩自动停止参数不正确 ");
            return false;
        }

        //记录log
        $fiel_data = " 桩自动停止,桩上报时间 date: ".Carbon::now().PHP_EOL." 桩自动停止,桩上报参数 code:$code, order_number:$order_number, start_type:$start_type, left_time:$left_time, stop_reason:$stop_reason ".PHP_EOL." 桩自动停止,桩上报帧 frame: ".bin2hex($message);
        $redis_data = "桩自动停止上报".'-'.json_encode(array('code'=>$code, 'order_number'=>$order_number, 'start_type'=>$start_type,'left_time'=>$left_time, 'stop_reason'=>$stop_reason)).'-'.bin2hex($message).'-'.Carbon::now().'+';
        self::record_log($code, $fiel_data, $redis_data);

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 
            code:$code, order_number:$order_number, left_tine:$left_time, stop_reason:$stop_reason " . Carbon::now());

        //处理接受自动停止数据,并响应桩
        $job = (new AutoStopReport($code, $order_number, $start_type, $left_time, $stop_reason))
            ->onQueue(env("APP_KEY"));
        dispatch($job);


    }



    //日结
    private static function report($message){

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 
            收到日结start " . date('Y-m-d H:i:s', time()));
        $report = new EvseReport();
        $report($message);
        $code = $report->code->getValue();
        $meter_number = $report->meter_number->getValue();                 //电表编号
        $date = $report->date->getValue();                                 //时间
        $electricity = $report->electricity->getValue();                   //电量
        $total_electricity = $report->total_electricity->getValue() / 100; //总电量
        $coins_number = $report->coins_number->getValue();                 //投币次数
        $card_amount = $report->card_amount->getValue();                   //刷卡金额
        $card_time = $report->card_time->getValue();                       //刷卡时长

        //换算单位
        $electricity_con = [];
        foreach ($electricity as $k=>$v){
            $electricity_con[$k] = $v / 100;
        }


        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 
                code:$code, meter_number:$meter_number, date:$date, electricity:".json_encode($electricity_con).", total_electricity:$total_electricity
                 coins_number:$coins_number, card_amount:$card_amount, card_time:$card_time " . date('Y-m-d H:i:s', time()));

        //记录log
        $fiel_data = " 日结,桩上报时间 date: ".Carbon::now()." 日结,桩上报参数 code:$code, meter_number:$meter_number, date:$date, electricity:".json_encode($electricity).", total_electricity:$total_electricity, coins_number:$coins_number, card_amount:$card_amount, card_time:$card_time "." 日结,桩上报帧 frame: ".bin2hex($message);
        $redis_data = "日结上报:".'-'.json_encode(array('code'=>$code, 'meter_number'=>$meter_number, 'date'=>$date, 'electricity'=>$electricity_con, 'total_electricity'=>$total_electricity, 'coins_number'=>$coins_number, 'card_amount'=>$card_amount, 'card_time'=>$card_time)).'-'.bin2hex($message).'-'.Carbon::now().'+';
        self::record_log($code, $fiel_data, $redis_data);

        //日结进入队列
        $job = (new TurnoverReport($code, $meter_number, $date, $electricity_con, $total_electricity, $coins_number, $card_amount, $card_time))
            ->onQueue(env("APP_KEY"));
        dispatch($job);



    }





    /*****************************************控制类****************************************************/

    //启动充电响应
    private static function start_charge_response($message){

        $startChrge = new EvseStartCharge();
        $startChrge($message);
        $code = $startChrge->code->getValue();
        $order_number = $startChrge->order_number->getValue();
        $result = $startChrge->result->getValue();

        //判断接受参数是否正确
        if(empty($code) || empty($order_number) || !is_numeric($result)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 启动充电响应接受参数错误 code:$code, order_number:$order_number, result:$result ");
            return false;
        }

        //记录对应相应桩的log
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 启动充电响应: order_number:$order_number, result:$result ".Carbon::now() );

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 
            code:$code, order_number:$order_number, result:$result " . Carbon::now());

        //记录log
        $fiel_data = " 启动充电响应时间 date: ".Carbon::now()." 启动充电响应参数 code:$code, order_number:$order_number, result:$result "." 启动充电响应帧 frame: ".bin2hex($message);
        $redis_data = "启动充电响应".'-'.json_encode(array('code'=>$code, 'order_number'=>$order_number, 'result'=>$result)).'-'.bin2hex($message).'-'.Carbon::now().'+';
        self::record_log($code, $fiel_data, $redis_data);


        //启动充电
        $job = (new StartChargeResponse($code, $order_number, $result))
            ->onQueue(env("APP_KEY"));
        dispatch($job);



    }


    //续费响应
    private static function renew_response($message){

        $renew = new EvseRenew();
        $frame_load = $renew($message);
        $code = $frame_load->code->getValue();
        $order_number = $frame_load->order_number->getValue();
        $result = $frame_load->result->getValue();

        //判断接受参数是否正确
        if(empty($code) || empty($order_number) || !is_numeric($result)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 续费响应接受参数错误, code:$code, order_number:$order_number, result:$result ");
            return false;
        }

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 
            code:$code, order_number:$order_number, result:$result " . Carbon::now());


        //记录log
        $fiel_data = " 续费响应时间 date: ".Carbon::now()." 续费响应参数 code:$code, order_number:$order_number, result:$result "." 续费响应帧 frame: ".bin2hex($message);
        $redis_data = "续费响应".'-'.json_encode(array('code'=>$code, 'order_number'=>$order_number, 'result'=>$result)).'-'.bin2hex($message).'-'.Carbon::now().'+';
        self::record_log($code, $fiel_data, $redis_data);


        //处理续费响应数据
        $job = (new RenewResponse($code, $order_number, $result))
            ->onQueue(env("APP_KEY"));
        dispatch($job);

        //处理数据
        //$result = self::$controller->renew($code, $order_number, $result);


    }


    //停止充电响应
    private static function stop_charge_response($message){

        $stop = new EvseStopCharge();
        $frame_load = $stop($message);
        $code = $frame_load->code->getValue();
        $order_number = $frame_load->order_number->getValue();
        $result = $frame_load->result->getValue();
        $left_time = $frame_load->left_time->getValue();
        $stop_time = $frame_load->stop_time->getValue();

        //判断接受参数是否正确
        if(empty($code) || empty($order_number) || !is_numeric($result) || !is_numeric($left_time) || !is_numeric($stop_time)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 续费响应接受参数错误 code:$code, order_number:$order_number, result:$result, left_time:$left_time, stop_time:$stop_time ");
            return false;
        }

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 
            code:$code, order_number:$order_number, result:$result, left_time:$left_time, stop_time:$stop_time " . Carbon::now());


        //记录log
        $fiel_data = " 停止充电响应时间 date: ".Carbon::now()." 停止充电响应参数 code:$code, order_number:$order_number, result:$result, left_time:$left_time, stop_time:$stop_time "." 停止充电响应帧 frame: ".bin2hex($message);
        $redis_data = "停止充电响应".'-'.json_encode(array('code'=>$code, 'order_number'=>$order_number, 'result'=>$result, 'left_time'=>$left_time, 'stop_time'=>$stop_time)).'-'.bin2hex($message).'-'.Carbon::now().'+';
        self::record_log($code, $fiel_data, $redis_data);


        //处理停止充电响应数据
        $job = (new StopChargeResponse($code, $order_number, $result, $left_time, $stop_time))
            ->onQueue(env("APP_KEY"));
        dispatch($job);



    }


    /*****************************************设置类****************************************************/


    //心跳设置响应
    private static function set_hearbeat_response($message){

        $hearbeat = new EvseHearbeat();
        $frame_load = $hearbeat($message);
        $code = $frame_load->code->getValue();
        $result = $frame_load->result->getValue();
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 设置心跳周期收到响应：
            code:$code, result:$result " . Carbon::now());

        $date = Carbon::now();
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳设置响应时间 date: $date" );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳设置响应参数 code:$code, result:$result " );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳设置响应帧 frame: ".bin2hex($message) );

        //记录log
        $content = "心跳设置响应: code:$code, result:$result ".'-'.bin2hex($message).'-'.date('Y-m-d H:i:s', time());
        self::redis_log($code, $content);


        //处理数据
        $result = self::$controller->setHearbeatCycle($code, $result);


    }


    //服务器信息设置响应
    private static function set_server_info_response($message){

        $info = new EvseServerInfo();
        $frame_load = $info($message);
        $code = $frame_load->code->getValue();
        $result = $frame_load->result->getValue();
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 设置服务器信息收到响应：
            code:$code, result:$result " . Carbon::now());

        $date = Carbon::now();
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 服务器信息设置响应时间 date: $date" );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 服务器信息设置响应参数 code:$code, result:$result " );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 服务器信息设置响应帧 frame: ".bin2hex($message) );

        //记录log
        $expire = strtotime(date('Y-m-d 00:00:00')) + 86400 - time();
        $parameter = $code.uniqid(mt_rand(),1);
        $content = "服务器信息设置响应: code:$code, result:$result ".'-'.bin2hex($message).'-'.Carbon::now();
        Redis::set($parameter,$content,'EX',$expire);

        //处理数据
        $result = self::$controller->setServerInfo($code, $result);

    }



    //清空营业额响应
    private static function empty_turnover_response($message){

        $turnover = new EvseEmptyTurnover();
        $frame_load = $turnover($message);
        $code = $frame_load->code->getValue();
        $coin_num = $frame_load->coin_num->getValue();
        $card_cost = $frame_load->card_cost->getValue();
        $card_time = $frame_load->card_time->getValue();
        $result = $frame_load->result->getValue();

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 设置服务器信息收到响应：
            code:$code, coin_num:$coin_num, card_cost:$card_cost, card_time:$card_time result:$result " . Carbon::now());

        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 清空营业额响应时间 date: ".Carbon::now() );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 清空营业额响应参数 code:$code, coin_num:$coin_num,card_cost:$card_cost,card_time:$card_time ,result:$result " );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 清空营业额响应帧 frame: ".bin2hex($message) );

        //记录log
        $expire = strtotime(date('Y-m-d 00:00:00')) + 86400 - time();
        $parameter = $code.uniqid(mt_rand(),1);
        $content = "清空营业额响应: code:$code, coin_num:$coin_num, card_cost:$card_cost, card_time:$card_time, result:$result ".'-'.bin2hex($message).'-'.Carbon::now();
        Redis::set($parameter,$content,'EX',$expire);

        //处理数据
        $result = self::$controller->emptyTurnover($code, $coin_num, $card_cost, $card_time, $result);

    }


    //设置参数响应
    private static function set_parameter_response($message){

        $parameter = new EvseSetParameter();
        $frame_load = $parameter($message);
        $code = $frame_load->code->getValue();
        $result = $frame_load->result->getValue();

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 设置参数响应：
            code:$code, result:$result " . Carbon::now());

        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 设置参数响应时间 date: ".Carbon::now() );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 设置参数响应参数 code:$code, result:$result " );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 设置参数响应帧 frame: ".bin2hex($message) );

        //记录log
        $expire = strtotime(date('Y-m-d 00:00:00')) + 86400 - time();
        $parameter = $code.uniqid(mt_rand(),1);
        $content = "设置参数响应: code:$code, result:$result ".'-'.bin2hex($message).'-'.Carbon::now();
        Redis::set($parameter,$content,'EX',$expire);

        //处理数据
        $result = self::$controller->setParament($code, $result);



    }



    //设置ID响应
    private static function set_id_response($message){

        $setId = new EvseSetId();
        $frame_load = $setId($message);
        $code = $frame_load->code->getValue();
        $result = $frame_load->result->getValue();

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 设置ID响应：
            code:$code, result:$result " . Carbon::now());


    }

    //修改时间
    private static function set_date_time_response($message){

        $dateTime = new EvseSetDateTime();
        $frame_load = $dateTime($message);
        $code = $frame_load->code->getValue();
        $result = $frame_load->result->getValue();

        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 修改时间响应时间 date: ".Carbon::now() );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 修改时间响应参数 code:$code, result:$result " );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 修改时间响应帧 frame: ".bin2hex($message) );

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 设置时间响应：
            code:$code, result:$result " . Carbon::now());
    }





    /*****************************************查询类****************************************************/

    //心跳查询响应
    private static function get_hearbeat_response($message){

        $hearbeat = new EvseGetHeartbeat();
        $frame_load = $hearbeat($message);
        $code = $frame_load->code->getValue();
        $heartbeat_cycle = $frame_load->heartbeat_cycle->getValue();

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 心跳查询响应：
            code:$code, result:$heartbeat_cycle " . Carbon::now());

        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 修改时间响应时间 date: ".Carbon::now() );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 修改时间响应参数 code:$code, heartbeat_cycle:$heartbeat_cycle " );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 修改时间响应帧 frame: ".bin2hex($message) );

        //记录log
        $expire = strtotime(date('Y-m-d 00:00:00')) + 86400 - time();
        $parameter = $code.uniqid(mt_rand(),1);
        $content = "心跳查询响应: code:$code, heartbeat_cycle:$heartbeat_cycle ".'-'.bin2hex($message).'-'.Carbon::now();
        Redis::set($parameter,$content,'EX',$expire);

        //处理数据
        $result = self::$controller->getHearbeat($code, $heartbeat_cycle);

    }


    //电表查表查询响应
    private static function get_meter_response($message){

        $readMeter = new EvseReadMeter();
        $frame_load = $readMeter($message);
        $code = $frame_load->code->getValue();
        $result = $frame_load->result->getValue(); //结果
        $number = $frame_load->number->getValue(); //电表编号
        $meterDegree = $frame_load->meter_degree->getValue() / 100; //电表度数 kwh

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 电表查询响应：
            code:$code, number:$number, meterDegree:$meterDegree " . Carbon::now());

        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 电表查表查询响应时间 date: ".Carbon::now() );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 电表查表查询响应参数 code:$code, number:$number,meterDegree:$meterDegree " );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 电表查表查询响应帧 frame: ".bin2hex($message) );

        //记录log
        $expire = strtotime(date('Y-m-d 00:00:00')) + 86400 - time();
        $parameter = $code.uniqid(mt_rand(),1);
        $content = "电表查表查询响应: code:$code, number:$number, meterDegree:$meterDegree ".'-'.bin2hex($message).'-'.Carbon::now();
        Redis::set($parameter,$content,'EX',$expire);

        //处理数据
        $result = self::$controller->getMeter($code, $number, $meterDegree);



    }


    //营业额查询响应
    private static function get_turnover_response($message){

        $turnover = new EvseGetTurnover();
        $frame_load = $turnover($message);
        $code = $frame_load->code->getValue();
        $coin_num = $frame_load->coin_num->getValue();
        $card_cost = $frame_load->card_cost->getValue();
        $card_time = $frame_load->card_time->getValue();

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 营业额查询响应：
            code:$code, coin_num:$coin_num, card_cost:$card_cost, card_time:$card_time " . Carbon::now());

        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 营业额查询响应时间 date: ".Carbon::now() );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 营业额查询响应参数 code:$code, coin_num:$coin_num,card_cost:$card_cost,card_time:$card_time " );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 营业额查询响应帧 frame: ".bin2hex($message) );

        //记录log
        $expire = strtotime(date('Y-m-d 00:00:00')) + 86400 - time();
        $parameter = $code.uniqid(mt_rand(),1);
        $content = "营业额查询响应: code:$code, coin_num:$coin_num, card_cost:$card_cost, card_time:$card_time ".'-'.bin2hex($message).'-'.Carbon::now();
        Redis::set($parameter,$content,'EX',$expire);

        //处理数据
        $result = self::$controller->getTurnover($code, $coin_num, $card_cost, $card_time);


    }



    //通道查询响应
    private static function get_channel_response($message){

        $status = new EvseGetChannelStatus();
        $frame_load = $status($message);
        $code = $frame_load->code->getValue();

        $order_number = $frame_load->order_number->getValue();
        $channel_num = $frame_load->channel_num->getValue();
        $current_average = $frame_load->current_average->getValue();
        $max_current = $frame_load->max_current->getValue();
        $current_base = $frame_load->current_base->getValue();
        $run_time = $frame_load->run_time->getValue();
        $left_time = $frame_load->left_time->getValue();
        $full_time = $frame_load->full_time->getValue();
        $payment_mode = $frame_load->payment_mode->getValue();
        $equipment_status = $frame_load->equipment_status->getValue();

        $data = ['current_average'=>$current_average, 'max_current'=>$max_current, 'current_base'=>$current_base, 'run_time'=>$run_time, 'left_time'=>$left_time,
            'full_time'=>$full_time, 'payment_mode'=>$payment_mode];

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 查询通道状态：
            code:$code, channel_num:$channel_num, order_number:$order_number, current_average:$current_average, max_current:$max_current
             current_base：$current_base, run_time：$run_time, left_time：$left_time, full_time：$full_time,
             payment_mode:$payment_mode, equipment_status:$equipment_status
             " . Carbon::now());

        $date = Carbon::now();
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 通道查询响应时间 date: $date" );
        //Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 营业额查询响应参数 code:$code, coin_num:$coin_num,card_cost:$card_cost,card_time:$card_time " );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 通道查询响应帧 frame: ".bin2hex($message) );


        //处理数据
        $result = self::$controller->channelStatus($code, $channel_num, $order_number, $data, $equipment_status);



    }


    //查询参数响应
    private static function get_parameter_response($message){

        $parameter = new EvseGetParameter();
        $frame_load = $parameter($message);

        $code = $frame_load->code->getValue();
        $channel_maximum_current = $frame_load->channel_maximum_current->getValue();
        $full_judge = $frame_load->full_judge->getValue();
        $clock = $frame_load->clock->getValue();
        $disconnect = $frame_load->disconnect->getValue();
        $power_base = $frame_load->power_base->getValue();
        $coin_rate = $frame_load->coin_rate->getValue();
        $card_rate = $frame_load->card_rate->getValue();

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 查询参数响应：
            code:$code, channel_maximum_current:$channel_maximum_current, power_base:$power_base, coin_rate:$coin_rate, card_rate:$card_rate, 
            full_judge:$full_judge, disconnect:$disconnect, clock:$clock, 
                 " . Carbon::now());

        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询参数响应时间 date: ".Carbon::now() );
        //Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询参数响应参数 code:$code, coin_num:$coin_num,card_cost:$card_cost,card_time:$card_time " );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询参数响应帧 frame: ".bin2hex($message) );

        //记录log
        $expire = strtotime(date('Y-m-d 00:00:00')) + 86400 - time();
        $parameter = $code.uniqid(mt_rand(),1);
        $content = "查询参数响应: code:$code, channel_maximum_current:$channel_maximum_current, full_judge:$full_judge, clock:$clock, disconnect:$disconnect, power_base:$power_base, coin_rate:$coin_rate, card_rate:$card_rate ".'-'.bin2hex($message).'-'.Carbon::now();
        Redis::set($parameter,$content,'EX',$expire);

        //处理数据
        $result = self::$controller->getParameter($code, $channel_maximum_current,$full_judge,$clock,$disconnect,$power_base,$coin_rate,$card_rate);


    }


    //查询ID响应
    private static function get_id_response($message){

        $getId = new EvseGetId();
        $frame_load = $getId($message);
        $code = $frame_load->code->getValue();
        $deviceId = $frame_load->device->getValue();


        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 查询ID响应：
            code:$code, deviceId:$deviceId " . Carbon::now());


    }


    //查询设备编号响应
    private static function get_identification_response($message){

        $getDeviceIdentification = new EvseGetDeviceIdentification();
        $frame_load = $getDeviceIdentification($message);
        $code = $frame_load->code->getValue();
        $deviceIdentification = $frame_load->deviceIdentification->getValue();


        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 查询设备编号响应：
            code:$code, deviceIdentification:$deviceIdentification " . Carbon::now());


    }



    //查询时间
    private static function get_date_time_response($message){

        $getDateTime = new EvseGetDateTime();
        $frame_load = $getDateTime($message);

        $code = $frame_load->code->getValue();
        $dateTime = $frame_load->date_time->getValue();

        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询时间响应时间 date: ".Carbon::now() );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询时间响应参数 code:$code, dateTime:$dateTime " );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询时间响应帧 frame: ".bin2hex($message) );

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 查询时间：
            code:$code, dateTime:$dateTime," . Carbon::now());

    }





    //记录log,包括参数,帧,时间
    private static function record_log($code, $fiel_data, $redis_data){

        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . $fiel_data );

        //记录log
        $parameter = $code.uniqid(mt_rand(),1);
        Redis::set($parameter,$redis_data,'EX',86400);

    }
















}