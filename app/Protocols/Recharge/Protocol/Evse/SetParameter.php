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


class SetParameter extends Frame
{


    protected $instructions = 0x1304;


    /**
     * 响应结果 成功 0x01 失败 0x00
     * @var int
     */
    protected $result = [BIN::class,1,TRUE];




}