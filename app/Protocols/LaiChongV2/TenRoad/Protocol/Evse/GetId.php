<?php
/**
 * Created by PhpStorm.
 * User: chao
 * Date: 2017-03-02
 * Time: 18:20
 */
namespace Wormhole\Protocols\TenRoad\Protocol\Evse;
use Wormhole\Protocols\TenRoad\Protocol\Frame;
use Wormhole\Protocols\Library\BIN;

class GetId extends Frame
{
    protected $instructions = 0x32;

    /**
     * 设备ID
     * @var int
     */
    protected $device = [BIN::class,4,TRUE];
}