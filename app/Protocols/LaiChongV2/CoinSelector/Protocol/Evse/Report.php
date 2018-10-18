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
use Wormhole\Protocols\QianNiu\Protocol\ElectricityInfo;


class Report extends Frame
{


    protected $instructions = 0x1105;

    /**
     * 电表编号
     * @var int
     */
    protected $meter_number = [BCD::class,6,TRUE];

    /**
     * 时间
     * @var int
     */
    protected $date = [BCD::class,3,TRUE];

    /**
     * 电量
     * @var int
     */
    protected $electricity = [ElectricityInfo::class,96,TRUE]; //96

    /**
     * 总电量
     * @var int
     */
    protected $total_electricity = [BIN::class,4,TRUE];

    /**
     * 投币次数
     * @var int
     */
    protected $coins_number = [BIN::class,2,TRUE];

    /**
     * 刷卡金额
     * @var int
     */
    protected $card_amount = [BIN::class,4,TRUE];


    /**
     * 刷卡时长
     * @var int
     */
    protected $card_time = [BIN::class,4,TRUE];


}