<?php
/**
 * Created by PhpStorm.
 * User: chao
 * Date: 2017-03-02
 * Time: 18:20
 */

namespace Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse;
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Frame;
use Wormhole\Protocols\Library\BIN;
use Wormhole\Protocols\Library\BCD;

class ReadMeterSuccess extends Frame
{
    protected $instructions = 0x51;

    /**
     * 电表度数 0.01kwh
     * @var int
     */
    protected $meter_degree = [BIN::class,4,TRUE];
}