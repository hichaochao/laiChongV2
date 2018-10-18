<?php

/**
 * Created by PhpStorm.
 * User: chao
 * Date: 2018-10-05
 * Time: 22:16
 */
$protocol = "TenRoad";
//协议名称，注意：协议为tcp时，必须手动指定协议名称；（某协议Protocol::NAME
$namespace = "\\Wormhole\\Protocols\\LaiChongV2\\$protocol";
$protocolInstance = $namespace."\\Protocol";
$protocolName =$protocolInstance::NAME;
$event = "$namespace\\EventsApi";

return [
    //需配置内容：monitor 服务器地址，本机地址和端口， gateway协议和端口，消息对应的协议名称；
    "debug"=>true,
    //监控平台信息
    "monitor_url"=>"http://m.uni.cn:80",  // http://domain:port
    "monitor_api_on_message"=>"/api/mni/api/evse_message/hash/",
    "monitor_api_on_close"=>"/api/mni/api/evse_offline/hash/",

    //本机信息
    "local_address"=>"45.62.102.179", //本机ip地址
    "local_port"=>80,//本机端口

    //平台名称
    "platform_name"=>'QuChong',
    //协议服务器ip
    "protocol_ip"=>'45.62.102.179',
    //协议服务器端口
    "protocol_port"=>'10000',

    //register
    "register"=>[
        "protocol"=> "text",
        "ip"=>"0.0.0.0",
        "port"=>1239
    ],
    //gateway服务信息
    "gateway"=>[
        "name"=>"gateway",
        "protocol"=>$protocolInstance,

        "ip"=>"0.0.0.0",
        "port"=>10086,
        "count"=>4, //线程数
        "lanIp" =>'45.62.102.179',// 本机局域网IP  172.18.0.5
        "startPort"=> 4905,// 内部通讯起始端口

    ],
    //worker
    "worker"=>[
        "event"=> $event,
        "name"=>"businessWorker",
        "count"=>4, //线程数
    ],

    //以下由开发配置
    "message"=> json_encode([
        "params"=>[
            "server_ip"=>"%s",
            "gateway_port"=>"%u",
            "client_id"=>"%s",
            "frame"=>"%s",
            "sequence"=>"%u",

            "platform_name"=>'%s',
            "protocol_ip"=>'%s',
            "protocol_port"=>'%s',

            "protocol"=>$protocolName

        ]
    ]),
    "offline"=>json_encode([
        "params" => array(
            "server_ip" => "%s",
            "gateway_port" => "%u",
            "client_id" => "%s",
            "protocol"=>$protocolName
        )
    ]),
];
