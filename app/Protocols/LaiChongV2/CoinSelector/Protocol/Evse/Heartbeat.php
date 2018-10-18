<?php
/**
 * Created by PhpStorm.
 * User: lingf
 * Date: 2017-03-02
 * Time: 18:20
 */

namespace Wormhole\Protocols\QianNiu\Protocol\Evse;



use Wormhole\Protocols\QianNiu\Protocol\Frame;
use Wormhole\Protocols\QianNiu\Protocol\PortInfo;
use Wormhole\Protocols\Library\BIN;
use Wormhole\Protocols\Library\CheckSum;
use Wormhole\Protocols\Library\Variable;
class Heartbeat extends Frame
{

    protected $instructions = 0x1102;

    /**
     * 信号强度
     * @var int
     */
    protected $signal = [BIN::class,1,TRUE];

    /**
     * 通道数量
     * @var int
     */
    protected $num = [BIN::class,1,TRUE];



    /**
     * 通道电流and剩余时间and设备状态
     * @var int
     */
    protected $info = [PortInfo::class,12,TRUE];





}