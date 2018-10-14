<?php
/**
 * Created by PhpStorm.
 * User: chao
 * Date: 2017-03-02
 * Time: 18:20
 */

namespace Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse;
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Frame;
use Wormhole\Protocols\Library\BIN;

class GetTurnover extends Frame
{
    protected $instructions = 0x33;

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
}