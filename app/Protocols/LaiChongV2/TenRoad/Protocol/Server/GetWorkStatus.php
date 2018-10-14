<?php
/**
 * Created by PhpStorm.
 * User: chao
 * Date: 2017-03-02
 * Time: 18:20
 */

namespace Wormhole\Protocols\TenRoad\Protocol\Server;
use Wormhole\Protocols\Library\BCD;
use Wormhole\Protocols\TenRoad\Protocol\Frame;
use Wormhole\Protocols\Library\BIN;

class GetWorkStatus extends Frame
{
    protected $instructions = 0x24;
}