<?php
/**
 * Created by PhpStorm.
 * User: lingf
 * Date: 2017-03-02
 * Time: 18:20
 */

namespace Wormhole\Protocols\TenRoad\Protocol\Evse;
use Wormhole\Protocols\TenRoad\Protocol\Frame;
use Wormhole\Protocols\TenRoad\Protocol\PortInfo;
use Wormhole\Protocols\Library\BIN;
use Wormhole\Protocols\Library\CheckSum;
use Wormhole\Protocols\Library\Variable;
class Heartbeat extends Frame
{
    protected $instructions = 0x21;

    /**
     * 当前锁定通道
     * @var int
     */
    protected $lock_thorough = [BIN::class,1,TRUE];

    /**
     * 工作状态
     * @var int
     */
    protected $work_status = [BIN::class,2,TRUE];

    /**
     * 故障状态
     * @var int
     */
    protected $fault_status = [BIN::class,2,TRUE];
}