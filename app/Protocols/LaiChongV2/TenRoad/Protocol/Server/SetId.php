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


class SetId extends Frame
{


    protected $instructions = 0x1305;

    /**
     * 设备ID
     * @var int
     */
    protected $device = [BIN::class,4,TRUE];




}