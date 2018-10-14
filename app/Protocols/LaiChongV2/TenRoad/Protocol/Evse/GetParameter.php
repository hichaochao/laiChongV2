<?php
/**
 * Created by PhpStorm.
 * User: chao
 * Date: 2017-03-02
 * Time: 18:20
 */
namespace Wormhole\Protocols\TenRoad\Protocol\Evse;
use Wormhole\Protocols\TenRoad\Protocol\Frame;
use Wormhole\Protocols\Library\BIN;
use Wormhole\Protocols\Library\BCD;

class GetParameter extends Frame
{
    protected $instructions = 0x31;

    /**
     * 刷卡费率
     * @var int
     */
    protected $card_rate = [BIN::class,1,TRUE];

    /**
     * 刷卡时间
     * @var int
     */
    protected $card_time = [BIN::class,1,TRUE];

    /**
     * 投币费率
     * @var int
     */
    protected $coin_rate = [BIN::class,1,TRUE];

    /**
     * 表中电流
     * @var int
     */
    protected $power_base = [BIN::class,1,TRUE];

    /**
     * 通道最大电流
     * @var int
     */
    protected $channel_maximum_current = [BIN::class,1,TRUE];

    /**
     * 插头拔断断电开关
     * @var int
     */
    protected $disconnect = [BIN::class,1,TRUE];
}