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


class PayCard extends Frame
{


    protected $instructions = 0x1106;

    /**
     * 通道号
     * @var int
     */
    protected $channel_number = [BIN::class,1,TRUE];

    /**
     * 订单号
     * @var int
     */
    protected $order_num = [BIN::class,1,TRUE];

    /**
     * 结果
     * @var int
     */
    protected $result = [BIN::class,1,TRUE];

}