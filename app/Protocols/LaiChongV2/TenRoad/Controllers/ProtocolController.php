<?php
namespace Wormhole\Protocols\LaiChongV2\TenRoad\Controllers;

use Carbon\Carbon;
use Faker\Provider\Uuid;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Wormhole\Protocols\LaiChongV2\TenRoad\EventsApi;
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol;
use Wormhole\Protocols\LaiChongV2\MonitorServer;
use Wormhole\Protocols\LaiChongV2\TenRoad\Models\Evse;
use Wormhole\Protocols\LaiChongV2\TenRoad\Models\Port;
use Wormhole\Protocols\LaiChongV2\TenRoad\Models\ChargeOrderMapping;
use Wormhole\Protocols\LaiChongV2\TenRoad\Models\ChargeRecords;
use Wormhole\Protocols\LaiChongV2\TenRoad\Models\Turnover;
use Wormhole\Protocols\LaiChongV2\TenRoad\Models\ModifyInfoLog;
//心跳队列
use Wormhole\Protocols\LaiChongV2\TenRoad\Jobs\CheckHeartbeat;

use Wormhole\Protocols\CommonTools;

use Illuminate\Support\Facades\Redis;

/**
 * Created by PhpStorm.
 * User: chao
 * Date: 2018-10-05
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

    //设置心跳周期
    public function setHearbeatCycle($code, $result){
        //如果设置成功,更新心跳周期结果
        if($result){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳周期设置响应成功 ");
            $evse = Evse::where("code",$code)->first(); //firstOrFail
            if(empty($evse)){
                Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 心跳周期设置响应成功,未找到桩相应数据 ");
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
            $requestResult->port_number = 1;
            $evse->request_result = json_encode($requestResult);
            $evse->save();
        }
    }

    //设置连接阈值
    public function setThreshold($code, $result){
        //如果设置成功,更新连接阈值
        if($result){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 服务器信息设置响应成功 ");
            $evse = Evse::where("code",$code)->first(); //firstOrFail
            $requestResult = $evse->request_result;
            $requestResult = json_decode($requestResult);
            $requestResult->threshold = 1;
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

    //信号强度查询强度
    public function getSignal($code, $signal_intensity){
        //如果设置成功,更新信号强度
        if($signal_intensity){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 信号强度查询响应成功 ");
            $evse = Evse::where("code",$code)->first(); //firstOrFail
            if(empty($evse)){
                Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 信号强度查询响应成功,未找到相应桩数据 ");
                return false;
            }
            $evse->signal_intensity = $signal_intensity;
            $evse->save();
            return true;
        }
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 信号强度查询响应成功,信号强度有误 signal_intensity:$signal_intensity ");
    }

    //查询连接阈值
    public function getThreshold($code, $threshold){
        //如果设置成功,更新连接阈值
        if($threshold){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询连接阈值响应成功 ");
            $evse = Evse::where("code",$code)->first(); //firstOrFail
            if(empty($evse)){
                Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询连接阈值响应成功,未找到相应桩数据 ");
                return false;
            }
            $evse->threshold = $threshold;
            $evse->save();
            return true;
        }
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询连接阈值响应成功,信号强度有误 threshold:$threshold ");
    }

    //电表查表查询
    public function getMeterSuccess($code, $meterDegree){
        //收到电表数据,保存到前一天
        $frontDate = date("Y-m-d",strtotime("-1 day"));
        $condition = [
            ['code', '=', $code],
            ['stat_date', '=', $frontDate]
        ];
        //如果收到数据则更新
        if(!empty($code) && is_numeric($meterDegree)){
            //找到当天的数据
            $turnover = Turnover::where($condition)->first();
            //如果没有数据则创建
            if(empty($turnover)){
                $result = Turnover::create([
                    'code'=>$code,
                    'stat_date'=>$frontDate,
                    'charged_power_time'=>'',
                    'charged_power'=>$meterDegree,
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
            $turnover->charged_power = $meterDegree;
            $res = $turnover->save();
            if($res){
                Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 电表查询更新成功 res:$res ");
                return true;
            }
        }
        Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 电表查询更新失败 ");
        return false;
    }

    //查询参数
    public function getParameter($code, $card_rate, $card_time, $coin_rate, $power_base, $channel_maximum_current, $disconnect){
        $evse = Evse::where("code",$code)->first(); //firstOrFail
        if(empty($evse)){
            Log::debug(__NAMESPACE__ . "/" . __CLASS__ . "/" . __FUNCTION__ . "@" . __LINE__ . " 查询参数响应成功,未找到数据 ");
            return false;
        }
        $data = ['card_rate'=>$card_rate, 'card_time'=>$card_time, 'coin_rate'=>$coin_rate, 'power_base'=>$power_base, 'channel_maximum_current'=>$channel_maximum_current, 'disconnect'=>$disconnect];
        $info = json_encode($data);
        $evse->parameter = $info;
        $evse->save();
        return true;
    }

}