<?php
/**
 * Created by PhpStorm.
 * User: chao
 * Date: 2017-03-02
 * Time: 18:20
 */

namespace Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Server;
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Frame;
use Wormhole\Protocols\Library\BIN;
use Wormhole\Protocols\Library\BCD;

class SetTime extends Frame
{
    protected $instructions = 0x42;

    /**
     * 单号
     * @var int
     */
    protected $order_no = [BIN::class,1,TRUE];

    /**
     * 年
     * @var int
     */
    protected $year = [BIN::class,1,TRUE];

    /**
     * 月
     * @var int
     */
    protected $moth = [BIN::class,1,TRUE];

    /**
     * 日
     * @var int
     */
    protected $day = [BIN::class,1,TRUE];

    /**
     * 时
     * @var int
     */
    protected $hour = [BIN::class,1,TRUE];

    /**
     * 分
     * @var int
     */
    protected $minute = [BIN::class,1,TRUE];
}