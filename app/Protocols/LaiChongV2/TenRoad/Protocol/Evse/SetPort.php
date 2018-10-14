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

class SetPort extends Frame
{
    protected $instructions = 0x31;

    /**
     * 结果
     * @var int
     */
    protected $result = [BIN::class,1,TRUE];
}