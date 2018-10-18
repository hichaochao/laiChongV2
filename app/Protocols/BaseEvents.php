<?php
/**
 * Created by PhpStorm.
 * User: chao
 * Date: 2018-09-15
 * Time: 11:19
 */
namespace Wormhole\Protocols;

use Wormhole\Console\Commands\Worker;
use Illuminate\Support\Facades\Log;
use \GatewayWorker\Lib\Gateway;
use Wormhole\Protocols\Unicharge\Protocol\Base\UpgradeFrame;
use Wormhole\Protocols\Unicharge\Protocol\Evse\DataArea\GetControl AS EvseGetControlDataArea;
use Wormhole\Protocols\Unicharge\Protocol\Evse\Frame\GetControl AS EvseGetControlFrame;
use Wormhole\Protocols\Unicharge\Protocol\Server\DataArea\GetControl AS ServerGetControlDataArea;
use Wormhole\Protocols\Unicharge\Protocol\Server\Frame\GetControl AS ServerGetControlFrame;
use \Workerman\Lib\Timer;
//use Workerman\Worker as Workers;
use Wormhole\Protocols\Licence;
class BaseEvents extends Worker
{
    protected static $hasUpgradeFrame = TRUE;
    /**
     * @param $client_id
     * @param $msg
     * @param $serverAddress
     * @return bool
     */
    public static function sendMsg($client_id, $msg){
        Log::info( __NAMESPACE__ .  "/".__CLASS__."/" . __FUNCTION__ . "@" . __LINE__ . " Client_id = $client_id; msg = ".bin2hex($msg));
        $result = Gateway::sendToClient($client_id,  $msg);
        return $result;
    }

    /**
     * 当客户端连接时触
     * 如果业务不需此回调可以删除onConnect
     *
     * @param int $client_id 连接id
     */
    public static function onConnect($client_id) {
        //清掉之前的缓存
        $clentId = \Cache::get($client_id);
        if(!empty($clentId)){
            \Cache::forget($client_id);
            \Cache::forget($clentId);
        }
        $address = $_SERVER['REMOTE_ADDR'];
        $port = $_SERVER['REMOTE_PORT'];

        $message = "New connect id : $client_id , address:$address , port : $port ";
        if(isset($_SERVER['HTTP-X-REAL-IP'])){
            $message .= " Real Ip(ngix):". $_SERVER['HTTP-X-REAL-IP'];
        }
        Log::debug(__NAMESPACE__. "/".__CLASS__."/" . __FUNCTION__ . "@" . __LINE__ . $message .PHP_EOL);
    }

    /**
     * 当客户端发来消息时触发
     * @param string $client_id 连接id
     * @param mixed $message 具体消息
     */
    public static function message($client_id, $message){
        $sendClentId = \Cache::get($client_id);
        $result = FALSE;
        if(!empty($sendClentId)){
            Log::debug(__NAMESPACE__. "/".__CLASS__."/".__FUNCTION__."@".__LINE__." sendClentId:".$sendClentId);
            $result = Gateway::sendToClient($sendClentId,  $message);
        }
        return $result;
    }

    /**
     * @param string $client_id 链接ID
     * @return bool true:continue ; false :end;
     */
    public static function continueMessage($client_id){
        $sendToClientId = \Cache::get($client_id);
        return empty($sendToClientId)?TRUE:FALSE;
    }

    /**
     * 当客户端发来消息时触发
     * @param int $client_id 连接id
     * @param mixed $message 具体消息
     */
    public static function onMessage($client_id, $message) {
        $result = static::message($client_id,$message);
        Log::debug(__NAMESPACE__. "/".__CLASS__."/".__FUNCTION__."@".__LINE__." message result : $result");
        if(FALSE === $result) {
            return FALSE;
        }
        Log::debug(__NAMESPACE__. "/".__CLASS__."/".__FUNCTION__."@".__LINE__." END".PHP_EOL.PHP_EOL);
    }

    /**
     * 当用户断开连接时触发
     * @param int $client_id 连接id
     */
    public static function onClose($client_id) {
        Log::debug(__NAMESPACE__. "/".__CLASS__."/".__FUNCTION__."@".__LINE__." Start, client: $client_id ");
        $client2 = \Cache::get($client_id);
        if(!empty($ClentId)){
            Log::debug(__NAMESPACE__. "/".__CLASS__."/".__FUNCTION__."@".__LINE__." 清空链接关系, client: $client_id ，client2 ： $client2 ");
            \Cache::forget($client_id);
            \Cache::forget($client2);
        }
        Log::debug(__NAMESPACE__. "/".__CLASS__."/".__FUNCTION__."@".__LINE__." END".PHP_EOL.PHP_EOL);
    }

}