<?php
/**
 * Created by PhpStorm.
 * User: chao
 * Date: 2017-03-02
 * Time: 18:20
 */

namespace Wormhole\Protocols\TenRoad\Protocol\Evse;
use Wormhole\Protocols\Library\BCD;
use Wormhole\Protocols\TenRoad\Protocol\Frame;
use Wormhole\Protocols\Library\BIN;

class WorkStatus extends Frame
{
    protected $instructions = 0x11;

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