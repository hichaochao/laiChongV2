<?php
namespace Wormhole\Protocols\Library;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Log{
    
    public static function log($code, $info, $type='debug'){

        $log = new Logger('');
        $logDir = storage_path("logs/$code");
        //如果不存在创建目录
        if (!is_dir($logDir)) {
            mkdir($logDir, 7777, true);
        }
        
        //当前日期为文件名
        $log->pushHandler(new StreamHandler($logDir . '/' . date('Y-m-d') . '.log', Logger::DEBUG));
        switch ($type){
            
            case 'debug' :
                $log->debug($info);
                break;
            case 'info' :
                $log->info($info);
                break;
            case 'notice' :
                $log->notice($info);
                break;
            case 'warning' :
                $log->warning($info);
                break;
            case 'error' :
                $log->error($info);
                break;
            case 'critical' :
                $log->critical($info);
                break;

        }
        







    }




}