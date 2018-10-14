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
use Wormhole\Protocols\Library\BCD;

class ReadMeter extends Frame
{


    protected $instructions = 0x1402;


    /**
     * 响应结果 成功 0x01 失败 0x00
     * @var int
     */
    protected $result = [BIN::class,1,TRUE];

    /**
     * 电表编号
     * @var int
     */
    protected $number = [BCD::class,6,TRUE];

    /**
     * 电表度数 0.01kwh
     * @var int
     */
    protected $meter_degree = [BIN::class,4,TRUE];


}