<?php
/**
 * Created by PhpStorm.
 * User: lingf
 * Date: 2016-11-08
 * Time: 11:21
 */

namespace Wormhole\Protocols\QianNiu;

use Workerman\Connection\TcpConnection;
use Wormhole\Protocols\Tools;
use Wormhole\Protocols\QianNiu\Protocol\Frame;
use Illuminate\Support\Facades\Log;
class Protocol
{
    const NAME="QianNiu";//QuChong
    const MAX_TIMEOUT=30;

    /**
     * 包头长度
     *
     * @var int
     */
    const HEAD_LEN = 10;

    /**
     * 判断包长
     * @param string $recv_buffer
     * @param TcpConnection $connection
     * @return int
     */
    public static function input($recv_buffer, TcpConnection $connection)
    {
        Log::debug(__NAMESPACE__ .  "/" . __CLASS__."/" . __FUNCTION__ . "@" . __LINE__ . " 包长度 ".strlen($recv_buffer));
        return strlen($recv_buffer);

    }

    /**
     * 从http数据包中解析$_POST、$_GET、$_COOKIE等
     * @param string $recv_buffer
     * @param TcpConnection $connection
     * @return string
     */
    public static function decode($recv_buffer, TcpConnection $connection)
    {
        return $recv_buffer;
    }

    /**
     * 编码，增加HTTP头
     * @param string $content
     * @param TcpConnection $connection
     * @return string
     */
    public static function encode($content, TcpConnection $connection)
    {
        return $content;

    }
}