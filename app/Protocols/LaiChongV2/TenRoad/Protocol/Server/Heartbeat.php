<?php
/**
 * Created by PhpStorm.
 * User: lingf
 * Date: 2017-03-02
 * Time: 18:20
 */

namespace Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server;
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Frame;
use Wormhole\Protocols\Library\BIN;
class Heartbeat extends Frame
{
    //指令
    protected $instructions = 0x21;
}