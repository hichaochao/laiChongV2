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


class EmptyTurnover extends Frame
{


    protected $instructions = 0x1303;


    /**
     * 响应结果 成功 0x01 失败 0x00
     * @var int
     */
    protected $result = [BIN::class,1,TRUE];

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