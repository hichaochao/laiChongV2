<?php
/**
 * Created by PhpStorm.
 * User: chao
 * Date: 2017-03-02
 * Time: 18:20
 */
namespace Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server;
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Frame;

class Signal extends Frame
{
    protected $instructions = 0x43;
}