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


class Sign extends Frame
{


    protected $instructions = 0x1101;
    
    /**
     * 通道数量
     * @var int
     */
    protected $num = [BIN::class,1,TRUE];

    /**
     * 设备识别号
     * @var int
     */
    protected $device_identification = [BIN::class,12,TRUE];

    /**
     * 心跳周期
     * @var int
     */
    protected $heabeat_cycle = [BIN::class,1,TRUE];



}