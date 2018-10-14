<?php
/**
 * Created by PhpStorm.
 * User: lingf
 * Date: 2017-03-02
 * Time: 18:20
 */

namespace Wormhole\Protocols\TenRoad\Protocol\Evse;



use Wormhole\Protocols\TenRoad\Protocol\Frame;
use Wormhole\Protocols\Library\BIN;


class GetDeviceIdentification extends Frame
{


    protected $instructions = 0x1407;


    /**
     * 设备识别号
     * @var int
     */
    protected $deviceIdentification = [BIN::class,12,TRUE];




}