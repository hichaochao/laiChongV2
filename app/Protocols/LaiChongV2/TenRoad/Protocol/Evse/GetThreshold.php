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

class GetThreshold extends Frame
{
    protected $instructions = 0x42;

    /**
     * 连接阈值
     * @var int
     */
    protected $threshold = [BIN::class,1,TRUE];
}