<?php
/**
 * Created by PhpStorm.
 * User: lingf
 * Date: 2017-03-02
 * Time: 18:20
 */

namespace Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse;
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Frame;
use Wormhole\Protocols\Library\BIN;

class PulloutAutoStaop extends Frame
{
    protected $instructions = 0x37;

    /**
     * 通道号
     * @var int
     */
    protected $channel_number = [BIN::class,1,TRUE];
}