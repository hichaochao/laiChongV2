<?php
/**
 * Created by PhpStorm.
 * User: lingf
 * Date: 2017-03-02
 * Time: 18:20
 */

namespace Wormhole\Protocols\TenRoad\Protocol\Evse;



use Wormhole\Protocols\TenRoad\Protocol\Frame;
use Wormhole\Protocols\Library\BIN;


class OfflineStart extends Frame
{


    protected $instructions = 0x1106;

    /**
     * 通道号
     * @var int
     */
    protected $channel_number = [BIN::class,1,TRUE];

    /**
     * 启动类型
     * @var int
     */
    protected $start_type = [BIN::class,1,TRUE];

    /**
     * 金额
     * @var int
     */
    protected $amount_money = [BIN::class,2,TRUE];

    /**
     * 充电时长
     * @var int
     */
    protected $duration = [BIN::class,2,TRUE];

    /**
     * 卡号
     * @var int
     */
    protected $card_num = [BIN::class,4,TRUE];
}