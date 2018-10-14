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


class GetTurnover extends Frame
{


    protected $instructions = 0x1403;

    /**
     * 投币次数
     * @var int
     */
    protected $coin_num = [BIN::class,2,TRUE];

    /**
     * 刷卡金额
     * @var int
     */
    protected $card_cost = [BIN::class,4,TRUE];

    /**
     * 刷卡时长
     * @var int
     */
    protected $card_time = [BIN::class,4,TRUE];





}