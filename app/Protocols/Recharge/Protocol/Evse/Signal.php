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

class Signal extends Frame
{


    protected $instructions = 0x1408;

    /**
     * 信号强度
     * @var int
     */
    protected $signal_intensity = [BIN::class,1,TRUE];





}