<?php

return [

    "host"=>"http://quchong.unicharge.cn", //monitor 地址  172.18.0.6:8080  http://10.44.64.18:2222

    //签到接口
    "signAPI"=>"/api/quchong/protocol/device_online",

    //断线接口
    "offlineApi"=>"/api/quchong/protocol/device_offline",

    //心跳接口
    "hearbeatApi"=>"/api/quchong/protocol/device_status_changed",

    //启动充电成功
    "startChargeSuccessApi"=>"/api/quchong/protocol/start_charge_success_response",

    //启动充电失败
    "startChargeFailApi"=>"/api/quchong/protocol/start_charge_failed_response",

    //停止充电成功
    "stopChargeSuccessApi"=>"/api/quchong/protocol/stop_charge_success_response",

    //停止充电失败
    "stopChargeFailApi"=>"/api/quchong/protocol/stop_charge_failed_response",

    //续费成功
    "continueChargeSuccessApi"=>"/api/quchong/protocol/continue_charge_success_response",

    //续费失败
    "continueChargeFailApi"=>"/api/quchong/protocol/continue_charge_failed_response",

    //日结
    "turnoverApi"=>"/api/quchong/protocol/add_device_turnover",

    
    //错误日志
    "deviceErrorApi"=>"/api/quchong/protocol/add_device_error_log",




];