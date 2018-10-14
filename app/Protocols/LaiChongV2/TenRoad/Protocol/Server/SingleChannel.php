<?php
/**
 * Created by PhpStorm.
 * User: chao
 * Date: 2017-03-02
 * Time: 18:20
 */

namespace Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server;
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Frame;
use Wormhole\Protocols\Library\BIN;

class SingleChannel extends Frame
{
    protected $instructions = 0x25;

    /**
     * 通道号
     * @var int
     */
    protected $channel_number = [BIN::class,1,TRUE];
}