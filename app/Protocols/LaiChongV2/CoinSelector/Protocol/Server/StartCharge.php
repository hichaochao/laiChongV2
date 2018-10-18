<?php
/**
 * Created by PhpStorm.
 * User: lingf
 * Date: 2017-03-02
 * Time: 18:20
 */

namespace Wormhole\Protocols\QianNiu\Protocol\Server;



use Wormhole\Protocols\QianNiu\Protocol\Frame;
use Wormhole\Protocols\Library\BIN;


class StartCharge extends Frame
{


    protected $instructions = 0x1201;

    /**
     * 订单号
     * @var int
     */
    protected $order_number = [BIN::class,1,TRUE];

    /**
     * 通道号
     * @var int
     */
    protected $channel_number = [BIN::class,1,TRUE];

    /**
     * 充电模式
     * @var int
     */
    protected $charge_mode = [BIN::class,1,TRUE];


    /**
     * 充电参数
     * @var int
     */
    protected $charge_parameter = [BIN::class,2,TRUE];



}