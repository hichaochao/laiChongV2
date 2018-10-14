<?php
/**
 * Created by PhpStorm.
 * User: lingf
 * Date: 2017-03-02
 * Time: 18:20
 */

namespace Wormhole\Protocols\QianNiu\Protocol\Evse;

use Wormhole\Protocols\QianNiu\Protocol\Frame;
use Wormhole\Protocols\Library\BIN;


class GetHearbeat extends Frame
{


    protected $instructions = 0x1401;

    /**
     * 心跳周期 单位：分钟
     * @var int
     */
    protected $heartbeat_cycle = [BIN::class,1,TRUE];




}