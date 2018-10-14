<?php
/**
 * Created by PhpStorm.
 * User: lingf
 * Date: 2017-01-09
 * Time: 18:18
 */

namespace Wormhole\Protocols;

use Illuminate\Support\Facades\Config;
use Kozz\Laravel\Facades\Guzzle;
use Illuminate\Support\Facades\Log;
use \Illuminate\Database\Eloquent\ModelNotFoundException;
class LicenceServer
{

    public static function validate($id){

        $api = Config::get('licence.ip');
        $server =Config::get('licence.host');
        //获取协议名称
        $protocolInstance = Config::get('gateway.gateway.protocol');
        $protocol_name = $protocolInstance::NAME;
        $url = $server.$api;

        $data =[
            "id"=>$id
        ];

        $data = self::request($url, $data, $protocol_name);

        if(FALSE === $data || empty($data['res'])){
            return FALSE;
        }
        return $data;

    }




    private static function request($url,$data,$protocol_name, $method="POST"){
        $data = ["params"=>$data];

        Log::debug(__NAMESPACE__ .  "/" . __CLASS__."/" . __FUNCTION__ . "@" . __LINE__ . " request license url:$url data:".json_encode($data)."protocol_name:$protocol_name");

        $request = [
            "headers"=>[
                'Content-Type' => 'application/json;charset=utf-8',
                'Accept' => 'application/gateway.wormhole.'.$protocol_name.'+json'
            ],
            "body"=> json_encode($data)

        ];
        $reponse = Guzzle::post($url,$request);
        Log::debug(__NAMESPACE__ .  "/" . __CLASS__."/" . __FUNCTION__ . "@" . __LINE__ . " 返回码: ".$reponse->getStatusCode());
        if($reponse->getStatusCode() == 200){
            $data = json_decode($reponse->getBody(),TRUE);
            Log::debug( __NAMESPACE__ .  "/" . __CLASS__."/" . __FUNCTION__ . "@" . __LINE__ . " ". $reponse->getBody());


            return $data;

        }
        return false;



    }


















}