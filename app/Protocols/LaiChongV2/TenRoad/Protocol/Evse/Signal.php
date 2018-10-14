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

class Signal extends Frame
{
    protected $instructions = 0x43;

    /**
     * 信号强度
     * @var int
     */
    protected $signal_intensity = [BIN::class,1,TRUE];
}