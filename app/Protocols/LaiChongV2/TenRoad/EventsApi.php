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

//心跳
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\Heartbeat as EvseHeartbeat;

//通道状态
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\WorkStatus as EvseWorkStatus;

//心跳设置
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\SetHearbeat as EvseHearbeat;

//端口设置
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\SetPort as EvseSetPort;

//连接阈值设置
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\SetThreshold as EvseSetThreshold;

//心跳查询
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\GetHearbeat as EvseGetHeartbeat;

//信号强度
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\Signal as EvseSignal;

//连接阈值查询
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\GetThreshold as EvseGetThreshold;

//电表抄表
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\ReadMeterSuccess as EvseReadMeterSuccess;
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\ReadMeterFail as EvseReadMeterFail;

//营业额查询
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\GetTurnover as EvseGetTurnover;

//查询参数
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\GetParameter as EvseGetParameter;

//状态查询
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\GetWorkStatus as EvseGetWorkStatus;

//单通道时间查询
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\SingleChannel as EvseSingleChannel;

//所有通道时间查询
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\AllThoroughTime as EvseAllThoroughTime;

//控制,统一相应
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\Answer as EvseAnswer;

//查询ID
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse\GetId as EvseGetId;

use Illuminate\Support\Facades\Redis;

use Wormhole\Protocols\Library\Log as Logger;

//签到
use Wormhole\Protocols\LaiChongV2\TenRoad\Jobs\SignReport;

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
        $instruct = '';
        //如果是统一应答
        if($operator == 0xE0){
            $answer = new EvseAnswer();
            $frame_load = $answer($message);
            $instruct = $frame_load->instruct->getValue();
        }
        //设备类型
        $type = $frame->type->getValue();
        //互联网模块设备类型
        $internet_type = 0x01;
        //C款充电桩主板
        $main_board_type = 0x10;

        //修改参数指令
        $fix_parameter = 0x41;
        //修改时钟
        $fix_date = 0x42;
        //启动充电
        $start_charge = 0x43;
        //续费
        $renew = 0x44;
        //停止充电
        $stop_chage = 0x45;
        //清空营业额
        $empty_turnover = 0x46;
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " " . " operator:,".$operator.' type:'.$type." isValid:$frame->isValid");
        if (!empty($frame)) {
            switch ($operator.$type.$instruct) {

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
                    self::work_status($message);
                    break;

                /*****************************************控制类上报****************************************************/
                case (0xE0.$main_board_type.$start_charge):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 启动充电 " );
                    self::start_charge_response($message);
                    break;
                case (0xE0.$main_board_type.$renew):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 续费 " );
                    self::renew_response($message);
                    break;
                case (0xE0.$main_board_type.$stop_chage):
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
                    self::set_threshold_response($message);
                    break;
                case (0xE0.$main_board_type.$fix_parameter):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 修改参数 " );
                    self::set_parameter_response($message);
                    break;
                case (0xE0.$main_board_type.$fix_date):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 修改时钟 " );
                    self::set_date_time_response($message);
                    break;
                case (0xE0.$main_board_type.$empty_turnover):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 清空营业额 " );
                    self::empty_turnover_response($message);
                    break;


                /*****************************************查询类上报****************************************************/
                case (0x23.$internet_type):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳查询 " );
                    self::get_hearbeat_response($message);
                    break;
                case (0x42.$internet_type):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 连接阈值查询 " );
                    self::get_threshold_response($message);
                    break;
                case (0x52.$internet_type):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 电表抄表成功 " );
                    self::get_meter_success_response($message);
                    break;
                case (0x53.$internet_type):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 电表抄表失败 " );
                    self::get_meter_fail_response($message);
                    break;
                case (0x43.$internet_type):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询信号强度 " );
                    self::get_signal_response($message);
                    break;
                case (0x33.$main_board_type):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 营业额查询 " );
                    self::get_turnover_response($message);
                    break;
                case (0x35.$main_board_type):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 状态查询 " );
                    self::get_status_infos_response($message);
                    break;
                case (0x34.$main_board_type):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 通道时间 " );
                    self::get_channel_response($message);
                    break;
                case (0x36.$main_board_type):
                    Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 所有通道时间 " );
                    self::get_all_channel_response($message);
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
        $num = 10; //枪口数量
        //判断接收数据是否正确
        if(empty($code) || empty($version)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 签到上报,数据不正确, code:$code, version:$version ");
            return false;
        }

        //记录对应某个桩的log
        $file_data = " 签到,桩上报时间 date: ".Carbon::now().PHP_EOL." 签到,桩上报参数 code:$code, version:$version ".PHP_EOL." 签到,桩上报帧 frame: ".bin2hex($message);
        $redis_data = "签到,桩上报".'-'.json_encode(array('code'=>$code, 'version'=>$version)).'-'.bin2hex($message).'-'.Carbon::now().'+';
        self::record_log($code, $file_data, $redis_data);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " version:$version " . Carbon::now());

        //处理签到上报数据并应答桩SignReport
        $job = (new SignReport($code, $num, self::$client_id, $version))
            ->onQueue(env("APP_KEY"));
        dispatch($job);
    }

    //心跳
    private static function hearbeat($message){
        $hearbeat = new EvseHeartbeat();
        $frame_load = $hearbeat($message);
        $client_id = self::$client_id;

        $code = $frame_load->code->getValue(); //设备编号
        $lock_status = $frame_load->lock_thorough->getValue(); //当前锁定通道
        $work_status = $frame_load->work_status->getValue(); //工作状态
        $fault_status = $frame_load->fault_status->getValue(); //故障状态

        if(empty($code)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 接收心跳,桩编号为空 " . Carbon::now());
        }
        //记录对应某个桩的log
        $file_data = " 心跳,桩上报时间 date: ".Carbon::now().PHP_EOL." 心跳,桩上报参数 code:$code, lock_status:$lock_status, work_status:$work_status, fault_status:$fault_status ".PHP_EOL." 心跳,桩上报帧 frame: ".bin2hex($message);
        $redis_data = "心跳,桩上报".'-'.json_encode(array('code'=>$code,'lock_status'=>$lock_status, 'work_status'=>$work_status, 'fault_status'=>$fault_status)).'-'.bin2hex($message).'-'.Carbon::now().'+';
        self::record_log($code, $file_data, $redis_data);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " code:$code, lock_status:$lock_status, work_status:$work_status, fault_status:$fault_status " . Carbon::now());

        //处理签到上报数据并应答桩HeartBeatReport
        $job = (new HeartBeatReport($code, $client_id, $lock_status, $work_status, $fault_status))
            ->onQueue(env("APP_KEY"));
        dispatch($job);
    }

    //工作状态,当通道状态发生变化时主动上传
    private static function work_status($message){
        $workStatus = new EvseWorkStatus();
        $frame_load = $workStatus($message);
        $client_id = self::$client_id;
        $code = $frame_load->code->getValue(); //设备编号
        $lock_status = $frame_load->lock_thorough->getValue(); //当前锁定通道
        $work_status = $frame_load->work_status->getValue(); //工作状态
        $fault_status = $frame_load->fault_status->getValue(); //故障状态
        if(empty($code)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 接收工作状态,桩编号为空 " . Carbon::now());
            return false;
        }
        //记录对应某个桩的log
        $file_data = " 工作状态,桩上报时间 date: ".Carbon::now().PHP_EOL." 工作状态,桩上报参数 code:$code, lock_status:$lock_status, work_status:$work_status, fault_status:$fault_status ".PHP_EOL." 工作状态,桩上报帧 frame: ".bin2hex($message);
        $redis_data = "工作状态,桩上报".'-'.json_encode(array('code'=>$code,'lock_status'=>$lock_status, 'work_status'=>$work_status, 'fault_status'=>$fault_status)).'-'.bin2hex($message).'-'.Carbon::now().'+';
        self::record_log($code, $file_data, $redis_data);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " code:$code, lock_status:$lock_status, work_status:$work_status, fault_status:$fault_status " . Carbon::now());

        //处理签到上报数据并应答桩HeartBeatReport
        $job = (new HeartBeatReport($code, $client_id, $lock_status, $work_status, $fault_status))
            ->onQueue(env("APP_KEY"));
        dispatch($job);
    }

    /*****************************************控制类****************************************************/

    //启动充电响应
    private static function start_charge_response($message){
        $startChrge = new EvseAnswer();
        $startChrge($message);
        $code = $startChrge->code->getValue();
        $order_no = $startChrge->order_no->getValue();
        $instruct = $startChrge->instruct->getValue();
        $result = $startChrge->result->getValue();
        //判断接受参数是否正确
        if(empty($code) || empty($order_no) || empty($instruct) || !is_numeric($result)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 启动充电响应接受参数错误 code:$code, order_no:$order_no, result:$result ");
            return false;
        }
        //记录对应相应桩的log
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 启动充电响应: order_no:$order_no, instruct:$instruct, result:$result ".Carbon::now() );

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 
            code:$code, order_no:$order_no, result:$result " . Carbon::now());

        //记录log
        $fiel_data = " 启动充电响应时间 date: ".Carbon::now()." 启动充电响应参数 code:$code, sorder_no:$order_no, result:$result "." 启动充电响应帧 frame: ".bin2hex($message);
        $redis_data = "启动充电响应".'-'.json_encode(array('code'=>$code, 'order_no'=>$order_no, 'result'=>$result)).'-'.bin2hex($message).'-'.Carbon::now().'+';
        self::record_log($code, $fiel_data, $redis_data);

        //启动充电
        $job = (new StartChargeResponse($code, $order_no, $result))
            ->onQueue(env("APP_KEY"));
        dispatch($job);
    }

    //续费响应
    private static function renew_response($message){
        $renew = new EvseAnswer();
        $renew($message);
        $code = $renew->code->getValue();
        $order_no = $renew->order_no->getValue();
        $instruct = $renew->instruct->getValue();
        $result = $renew->result->getValue();
        //判断接受参数是否正确
        if(empty($code) || empty($order_no) || empty($instruct) || !is_numeric($result)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "续费响应接受参数错误 code:$code, order_no:$order_no, result:$result ");
            return false;
        }
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 
            code:$code, order_no:$order_no, result:$result " . Carbon::now());
        //记录log
        $fiel_data = " 续费响应时间 date: ".Carbon::now()." 续费响应参数 code:$code, order_no:$order_no, result:$result "." 续费响应帧 frame: ".bin2hex($message);
        $redis_data = "续费响应".'-'.json_encode(array('code'=>$code, 'order_no'=>$order_no, 'result'=>$result)).'-'.bin2hex($message).'-'.Carbon::now().'+';
        self::record_log($code, $fiel_data, $redis_data);

        //处理续费响应数据
        $job = (new RenewResponse($code, $order_no, $result))
            ->onQueue(env("APP_KEY"));
        dispatch($job);
        //处理数据
        //$result = self::$controller->renew($code, $order_number, $result);
    }

    //停止充电响应
    private static function stop_charge_response($message){
        $renew = new EvseAnswer();
        $renew($message);
        $code = $renew->code->getValue();
        $order_no = $renew->order_no->getValue();
        $instruct = $renew->instruct->getValue();
        $left_time = $renew->result->getValue(); //剩余时间
        //判断接受参数是否正确
        if(empty($code) || empty($order_no) || empty($instruct) || !is_numeric($left_time)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "停止充电响应接受参数错误 code:$code, order_no:$order_no, left_time:$left_time ");
            return false;
        }
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 
            code:$code, order_no:$order_no, left_time:$left_time " . Carbon::now());
        //记录log
        $fiel_data = " 停止充电响应时间 date: ".Carbon::now()." 停止充电响应参数 code:$code, order_no:$order_no, left_time:$left_time "." 停止充电响应帧 frame: ".bin2hex($message);
        $redis_data = "停止充电响应".'-'.json_encode(array('code'=>$code, 'order_no'=>$order_no, 'left_time'=>$left_time)).'-'.bin2hex($message).'-'.Carbon::now().'+';
        self::record_log($code, $fiel_data, $redis_data);

        //处理停止充电响应数据
        $job = (new StopChargeResponse($code, $order_no, $left_time))
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
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 设置心跳周期收到响应：code:$code, result:$result " . Carbon::now());
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳设置响应时间 date: ".Carbon::now() );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳设置响应参数 code:$code, result:$result " );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳设置响应帧 frame: ".bin2hex($message) );

        //记录log
        $content = "心跳设置响应: code:$code, result:$result ".'-'.bin2hex($message).'-'.Carbon::now();
        self::redis_log($code, $content);

        //处理数据
        $result = self::$controller->setHearbeatCycle($code, $result);
    }

    //服务器信息设置响应
    private static function set_server_info_response($message){
        $setPort = new EvseSetPort();
        $frame_load = $setPort($message);
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

    //连接阈值设置响应
    public static function set_threshold_response($message){
        $threshold = new EvseSetThreshold();
        $frame_load = $threshold($message);
        $code = $frame_load->code->getValue();
        $result = $frame_load->result->getValue();
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 连接阈值设置响应：code:$code, result:$result " . Carbon::now());
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 连接阈值设置响应时间 date: ".Carbon::now() );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 连接阈值设置响应参数 code:$code, result:$result " );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 连接阈值设置响应帧 frame: ".bin2hex($message) );

        //记录log
        $expire = strtotime(date('Y-m-d 00:00:00')) + 86400 - time();
        $parameter = $code.uniqid(mt_rand(),1);
        $content = "连接阈值设置响应: code:$code, result:$result ".'-'.bin2hex($message).'-'.Carbon::now();
        Redis::set($parameter,$content,'EX',$expire);

        //处理数据
        $result = self::$controller->setThreshold($code, $result);
    }

    //修改参数响应
    private static function set_parameter_response($message){
        $answer = new EvseAnswer();
        $frame_load = $answer($message);
        $code = $frame_load->code->getValue();
        $order_no = $frame_load->order_no->getValue();
        $instruct = $frame_load->instruct->getValue();
        $result = $frame_load->result->getValue();
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "统一应答响应参数 code:$code, order_no:$order_no, instruct:$instruct, result:$result, frame: ".bin2hex($message).' date:'.Carbon::now() );
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 统一应答： code:$code, order_no:$order_no, instruct:$instruct, result:$result, frame:".bin2hex($message) . Carbon::now());
        //处理数据
        $result = self::$controller->setParament($code, $result);
    }

    //修改时钟响应
    private static function set_date_time_response($message){
        $answer = new EvseAnswer();
        $frame_load = $answer($message);
        $code = $frame_load->code->getValue();
        $order_no = $frame_load->order_no->getValue();
        $instruct = $frame_load->instruct->getValue();
        $result = $frame_load->result->getValue();
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "修改时钟响应参数 code:$code, order_no:$order_no, instruct:$instruct, result:$result, frame: ".bin2hex($message).' date:'.Carbon::now() );
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 修改时钟响应s： code:$code, order_no:$order_no, instruct:$instruct, result:$result, frame:".bin2hex($message) . Carbon::now());
    }

    //清空营业额响应
    private static function empty_turnover_response($message){
        $answer = new EvseAnswer();
        $frame_load = $answer($message);
        $code = $frame_load->code->getValue();
        $order_no = $frame_load->order_no->getValue();
        $instruct = $frame_load->instruct->getValue();
        $result = $frame_load->result->getValue();
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "清空营业额响应参数 code:$code, order_no:$order_no, instruct:$instruct, result:$result, frame: ".bin2hex($message).' date:'.Carbon::now() );
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 清空营业额响应： code:$code, order_no:$order_no, instruct:$instruct, result:$result, frame:".bin2hex($message) . Carbon::now());

        //记录log
        $expire = strtotime(date('Y-m-d 00:00:00')) + 86400 - time();
        $parameter = $code.uniqid(mt_rand(),1);
        $content = "清空营业额响应: code:$code, result:$result ".'-'.bin2hex($message).'-'.Carbon::now();
        Redis::set($parameter,$content,'EX',$expire);

        //处理数据
        //$result = self::$controller->emptyTurnover($code, $coin_num, $card_cost, $card_time, $result);
    }

    //控制,统一应答
    private static function get_date_time_response($message){
        $answer = new EvseAnswer();
        $frame_load = $answer($message);
        $code = $frame_load->code->getValue();
        $order_no = $frame_load->order_no->getValue();
        $instruct = $frame_load->instruct->getValue();
        $result = $frame_load->result->getValue();
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . "统一应答响应参数 code:$code, order_no:$order_no, instruct:$instruct, result:$result, frame: ".bin2hex($message).' date:'.Carbon::now() );
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 统一应答： code:$code, order_no:$order_no, instruct:$instruct, result:$result, frame:".bin2hex($message) . Carbon::now());
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

    /*****************************************查询类****************************************************/

    //心跳查询响应
    private static function get_hearbeat_response($message){
        $hearbeat = new EvseGetHeartbeat();
        $frame_load = $hearbeat($message);
        $code = $frame_load->code->getValue();
        $heartbeat_cycle = $frame_load->heartbeat_cycle->getValue();

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 心跳查询响应：
            code:$code, result:$heartbeat_cycle " . Carbon::now());

        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳查询响应时间 date: ".Carbon::now() );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳查询响应参数 code:$code, heartbeat_cycle:$heartbeat_cycle " );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳查询响应帧 frame: ".bin2hex($message) );

        //记录log
        $expire = strtotime(date('Y-m-d 00:00:00')) + 86400 - time();
        $parameter = $code.uniqid(mt_rand(),1);
        $content = "心跳查询响应: code:$code, heartbeat_cycle:$heartbeat_cycle ".'-'.bin2hex($message).'-'.Carbon::now();
        Redis::set($parameter,$content,'EX',$expire);

        //处理数据
        $result = self::$controller->getHearbeat($code, $heartbeat_cycle);
    }

    //连接阈值查询响应
    private static function get_threshold_response($message){
        $threshold = new EvseGetThreshold();
        $frame_load = $threshold($message);
        $code = $frame_load->code->getValue();
        $threshold = $frame_load->threshold->getValue(); //连接阈值

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 连接阈值查询响应：code:$code, threshold:$threshold " . Carbon::now());
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 连接阈值查询响应时间 date: ".Carbon::now() );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 连接阈值查询响应参数 code:$code, threshold:$threshold " );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 连接阈值查询响应帧 frame: ".bin2hex($message) );

        //记录log
        $expire = strtotime(date('Y-m-d 00:00:00')) + 86400 - time();
        $parameter = $code.uniqid(mt_rand(),1);
        $content = "连接阈值查询响应: code:$code, threshold:$threshold ".'-'.bin2hex($message).'-'.Carbon::now();
        Redis::set($parameter,$content,'EX',$expire);

        //处理数据
        $result = self::$controller->getThreshold($code, $threshold);
    }

    //信号强度查询响应
    private static function get_signal_response($message){
        $signal = new EvseSignal();
        $frame_load = $signal($message);
        $code = $frame_load->code->getValue();
        $signal_intensity = $frame_load->signal_intensity->getValue(); //信号强度

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 信号强度查询响应：code:$code, signal_intensity:$signal_intensity " . Carbon::now());
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 信号强度查询响应时间 date: ".Carbon::now() );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 信号强度查询响应参数 code:$code, signal_intensity:$signal_intensity " );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 信号强度查询响应帧 frame: ".bin2hex($message) );

        //记录log
        $expire = strtotime(date('Y-m-d 00:00:00')) + 86400 - time();
        $parameter = $code.uniqid(mt_rand(),1);
        $content = "电表查表查询响应: code:$code, signal_intensity:$signal_intensity ".'-'.bin2hex($message).'-'.Carbon::now();
        Redis::set($parameter,$content,'EX',$expire);

        //处理数据
        $result = self::$controller->getSignal($code, $signal_intensity);
    }

    //电表抄表成功响应
    private static function get_meter_success_response($message){
        $readMeter = new EvseReadMeterSuccess();
        $frame_load = $readMeter($message);
        $code = $frame_load->code->getValue();
        $meterDegree = $frame_load->meter_degree->getValue() / 100; //电表度数 kwh

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 电表查询响应：code:$code, meterDegree:$meterDegree " . Carbon::now());
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 电表查表查询响应时间 date: ".Carbon::now() );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 电表查表查询响应参数 code:$code,meterDegree:$meterDegree " );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 电表查表查询响应帧 frame: ".bin2hex($message) );

        //记录log
        $expire = strtotime(date('Y-m-d 00:00:00')) + 86400 - time();
        $parameter = $code.uniqid(mt_rand(),1);
        $content = "电表查表查询响应: code:$code, meterDegree:$meterDegree ".'-'.bin2hex($message).'-'.Carbon::now();
        Redis::set($parameter,$content,'EX',$expire);

        //处理数据
        $result = self::$controller->getMeterSuccess($code, $meterDegree);
    }

    //电表抄表失败响应
    private static function get_meter_fail_response($message){
        $readMeter = new EvseReadMeterFail();
        $frame_load = $readMeter($message);
        $code = $frame_load->code->getValue();

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 电表查询失败响应：code:$code " . Carbon::now());
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 电表查询失败响应时间 date: ".Carbon::now() );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 电表查询失败响应参数 code:$code " );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 电表查询失败响应帧 frame: ".bin2hex($message) );

        //记录log
        $expire = strtotime(date('Y-m-d 00:00:00')) + 86400 - time();
        $parameter = $code.uniqid(mt_rand(),1);
        $content = "电表查表查询响应: code:$code ".'-'.bin2hex($message).'-'.Carbon::now();
        Redis::set($parameter,$content,'EX',$expire);
    }

    //营业额查询响应
    private static function get_turnover_response($message){
        $turnover = new EvseGetTurnover();
        $frame_load = $turnover($message);
        $code = $frame_load->code->getValue();
        $coin_num = $frame_load->coin_num->getValue();
        $card_cost = $frame_load->card_cost->getValue();
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 营业额查询响应：code:$code, coin_num:$coin_num, card_cost:$card_cost " . Carbon::now());
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 营业额查询响应时间 date: ".Carbon::now() );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 营业额查询响应参数 code:$code, coin_num:$coin_num,card_cost:$card_cost " );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 营业额查询响应帧 frame: ".bin2hex($message) );

        //记录log
        $expire = strtotime(date('Y-m-d 00:00:00')) + 86400 - time();
        $parameter = $code.uniqid(mt_rand(),1);
        $content = "营业额查询响应: code:$code, coin_num:$coin_num, card_cost:$card_cost ".'-'.bin2hex($message).'-'.Carbon::now();
        Redis::set($parameter,$content,'EX',$expire);

        //处理数据
        //$result = self::$controller->getTurnover($code, $coin_num, $card_cost);
        //日结进入队列
        $job = (new TurnoverReport($code, $coin_num, $card_cost))
            ->onQueue(env("APP_KEY"));
        dispatch($job);
    }

    //状态查询
    private static function get_status_infos_response($message){
        $workStatus = new EvseGetWorkStatus();
        $frame_load = $workStatus($message);

        $code = $frame_load->code->getValue(); //设备编号
        $lock_status = $frame_load->lock_thorough->getValue(); //当前锁定通道
        $work_status = $frame_load->work_status->getValue(); //工作状态
        $fault_status = $frame_load->fault_status->getValue(); //故障状态
        if(empty($code)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 接收状态查询,桩编号为空 " . Carbon::now());
        }
        //记录对应某个桩的log
        $file_data = " 状态查询,桩上报时间 date: ".Carbon::now().PHP_EOL." 状态查询,桩上报参数 code:$code, lock_status:$lock_status, work_status:$work_status, fault_status:$fault_status ".PHP_EOL." 状态查询,桩上报帧 frame: ".bin2hex($message);
        $redis_data = "状态查询,桩上报".'-'.json_encode(array('code'=>$code,'lock_status'=>$lock_status, 'work_status'=>$work_status, 'fault_status'=>$fault_status)).'-'.bin2hex($message).'-'.Carbon::now().'+';
        self::record_log($code, $file_data, $redis_data);
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " code:$code, lock_status:$lock_status, work_status:$work_status, fault_status:$fault_status " . Carbon::now());
    }

    //单通道时间查询响应
    private static function get_channel_response($message){
        $singleChannel = new EvseSingleChannel();
        $frame_load = $singleChannel($message);
        $code = $frame_load->code->getValue();
        $channel_number = $frame_load->channel_number->getValue(); //通道号
        $left_time = $frame_load->left_time->getValue(); //剩余时间
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 单通道时间查询响应:code:$code, channel_number:$channel_number, left_time:$left_time" . Carbon::now());
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 通道查询响应帧 frame: ".bin2hex($message).'时间:'.Carbon::now() );
    }

    //所有通道时间查询响应
    private static function get_all_channel_response($message){
        $thoroughTime = new EvseAllThoroughTime();
        $frame_load = $thoroughTime($message);
        $code = $frame_load->code->getValue();
        $left_time0 = $frame_load->channel0->getValue(); //0通道时间
        $left_time1 = $frame_load->channel1->getValue(); //1通道时间
        $left_time2 = $frame_load->channel2->getValue(); //2通道时间
        $left_time3 = $frame_load->channel3->getValue(); //3通道时间
        $left_time4 = $frame_load->channel4->getValue(); //4通道时间
        $left_time5 = $frame_load->channel5->getValue(); //5通道时间
        $left_time6 = $frame_load->channel6->getValue(); //6通道时间
        $left_time7 = $frame_load->channel7->getValue(); //7通道时间
        $left_time8 = $frame_load->channel8->getValue(); //8通道时间
        $left_time9 = $frame_load->channel9->getValue(); //9通道时间
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 所有通道时间查询响应:code:$code, ".bin2hex($message).Carbon::now());
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 所有通道时间查询响应帧 frame: ".bin2hex($message).'时间:'.Carbon::now() );
    }

    //查询参数响应
    private static function get_parameter_response($message){
        $parameter = new EvseGetParameter();
        $frame_load = $parameter($message);
        $code = $frame_load->code->getValue();
        $card_rate = $frame_load->card_rate->getValue();
        $card_time = $frame_load->card_time->getValue();
        $coin_rate = $frame_load->coin_rate->getValue();
        $power_base = $frame_load->power_base->getValue();
        $channel_maximum_current = $frame_load->channel_maximum_current->getValue();
        $disconnect = $frame_load->disconnect->getValue();

        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 查询参数响应：
            code:$code, card_rate:$card_rate, card_time:$card_time, coin_rate:$coin_rate,, 
            power_base:$power_base, channel_maximum_current:$channel_maximum_current, disconnect:$disconnect " . Carbon::now());
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询参数响应时间 date: ".Carbon::now() );
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询参数响应帧 frame: ".bin2hex($message) );

        //记录log
        $expire = strtotime(date('Y-m-d 00:00:00')) + 86400 - time();
        $parameter = $code.uniqid(mt_rand(),1);
        $content = "查询参数响应: code:$code, card_rate:$card_rate, card_time:$card_time, coin_rate:$coin_rate, power_base:$power_base, channel_maximum_current:$channel_maximum_current, disconnect:$disconnect ".'-'.bin2hex($message).'-'.Carbon::now();
        Redis::set($parameter,$content,'EX',$expire);

        //处理数据
        $result = self::$controller->getParameter($code, $card_rate, $card_time, $coin_rate, $power_base, $channel_maximum_current, $disconnect);
    }

    //查询ID响应
    private static function get_id_response($message){
        $getId = new EvseGetId();
        $frame_load = $getId($message);
        $code = $frame_load->code->getValue();
        $deviceId = $frame_load->device->getValue();
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . ", 查询ID响应：code:$code, deviceId:$deviceId " . Carbon::now());
    }

    //记录log,包括参数,帧,时间
    private static function record_log($code, $fiel_data, $redis_data){
        Logger::log($code, __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . $fiel_data );
        //记录log
        $parameter = $code.uniqid(mt_rand(),1);
        Redis::set($parameter,$redis_data,'EX',86400);
    }

}