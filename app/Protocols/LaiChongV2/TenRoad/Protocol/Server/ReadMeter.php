<?php
/**
 * Created by PhpStorm.
 * User: lingf
 * Date: 2017-03-02
 * Time: 18:20
 */

namespace Wormhole\Protocols\TenRoad\Protocol\Server;
use Wormhole\Protocols\TenRoad\Protocol\Frame;
use Wormhole\Protocols\Library\BCD;
class ReadMeter extends Frame
{
    protected $instructions = 0x51;

    /**
     * 电表地址
     * @var int
     */
    protected $address = [BCD::class,6,TRUE];
}