<?php
/**
 * Created by PhpStorm.
 * User: lingf
 * Date: 2017-03-02
 * Time: 18:20
 */

namespace Wormhole\Protocols\QianNiu\Protocol\Evse;



use Wormhole\Protocols\QianNiu\Protocol\Frame;
use Wormhole\Protocols\Library\BIN;


class AutomaticStop extends Frame
{


    protected $instructions = 0x1103;


    /**
     * 通道号
     * @var int
     */
    protected $channel_number = [BIN::class,1,TRUE];

    /**
     * 订单号
     * @var int
     */
    protected $order_number = [BIN::class,1,TRUE];

    /**
     * 启动类型
     * @var int
     */
    protected $start_type = [BIN::class,1,TRUE];

    /**
     * 剩余时间
     * @var int
     */
    protected $left_time = [BIN::class,2,TRUE];

    /**
     * 停止原因
     * @var int
     */
    protected $stop_reason = [BIN::class,1,TRUE];


}