<?php
/**
 * Created by PhpStorm.
 * User: chao
 * Date: 2017-03-02
 * Time: 18:20
 */

namespace Wormhole\Protocols\TenRoad\Protocol\Server;
use Wormhole\Protocols\TenRoad\Protocol\Frame;
use Wormhole\Protocols\Library\BIN;

class StopCharge extends Frame
{
    protected $instructions = 0x45;

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
}