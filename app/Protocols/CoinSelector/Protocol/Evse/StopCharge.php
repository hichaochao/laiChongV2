<?php
/**
 * Created by PhpStorm.
 * User: lingf
 * Date: 2017-03-02
 * Time: 18:20
 */

namespace Wormhole\Protocols\QianNiu\Protocol\Evse;



use Wormhole\Protocols\Library\BCD;
use Wormhole\Protocols\QianNiu\Protocol\Frame;
use Wormhole\Protocols\Library\BIN;


class StopCharge extends Frame
{


    protected $instructions = 0x1203;


    /**
     * 订单号
     * @var int
     */
    protected $order_number = [BIN::class,1,TRUE];

    /**
     * 响应结果 成功 0x01 失败 0x00
     * @var int
     */
    protected $result = [BIN::class,1,TRUE];


    /**
     * 剩余时间
     * @var int
     */
    protected $left_time = [BIN::class,2,TRUE];

    /**
     * 停止时间
     * @var int
     */
    protected $stop_time = [BIN::class,6,TRUE];



}