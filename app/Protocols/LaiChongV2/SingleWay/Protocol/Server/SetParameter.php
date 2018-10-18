<?php
/**
 * Created by PhpStorm.
 * User: lingf
 * Date: 2017-03-02
 * Time: 18:20
 */

namespace Wormhole\Protocols\QianNiu\Protocol\Server;



use Wormhole\Protocols\QianNiu\Protocol\Frame;
use Wormhole\Protocols\Library\BIN;


class SetParameter extends Frame
{


    protected $instructions = 0x1304;

    /**
     * 通道最大电流
     * @var int
     */
    protected $channel_maximum_current = [BIN::class,2,TRUE];

    /**
     * 功率基数
     * @var int
     */
    protected $power_base = [BIN::class,2,TRUE];

    /**
     * 投币费率
     * @var int
     */
    protected $coin_rate = [BIN::class,2,TRUE];


    /**
     * 刷卡费率
     * @var int
     */
    protected $card_rate = [BIN::class,2,TRUE];

    /**
     * 充满判断比例
     * @var int
     */
    protected $full_judge = [BIN::class,1,TRUE];



    /**
     * 插头拔断断电开关
     * @var int
     */
    protected $disconnect = [BIN::class,1,TRUE];








}