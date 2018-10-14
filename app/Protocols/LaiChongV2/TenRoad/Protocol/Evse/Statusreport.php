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
class Statusreport extends Frame
{

    protected $instructions = 0x1406;
    /**
     * 通道数量
     * @var int
     */
    protected $num = [BIN::class,2,TRUE];



    /**
     * 通道电流and剩余时间and设备状态
     * @var int
     */
    protected $info = [PortInfo::class,12, TRUE];





}