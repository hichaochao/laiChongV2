<?php
/**
 * Created by PhpStorm.
 * User: lingf
 * Date: 2017-03-02
 * Time: 18:20
 */

namespace Wormhole\Protocols\TenRoad\Protocol\Server;
use Wormhole\Protocols\TenRoad\Protocol\Frame;
use Wormhole\Protocols\Library\BIN;
use Wormhole\Protocols\Library\BCD;

class Sign extends Frame
{
    protected $instructions = 0x11;
    
    /**
     * 响应结果：0x01 成功，0x00失败
     * @var int
     */
    protected $result = [BIN::class,1,TRUE];
}