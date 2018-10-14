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

class AllThoroughTime extends Frame
{
    protected $instructions = 0x36;

    /**
     * 通道0剩余时间
     * @var int
     */
    protected $channel0 = [BIN::class,2,TRUE];

    /**
     * 通道1剩余时间
     * @var int
     */
    protected $channel1 = [BIN::class,2,TRUE];

    /**
     * 通道2剩余时间
     * @var int
     */
    protected $channel2 = [BIN::class,2,TRUE];

    /**
     * 通道3剩余时间
     * @var int
     */
    protected $channel3 = [BIN::class,2,TRUE];

    /**
     * 通道4剩余时间
     * @var int
     */
    protected $channel4 = [BIN::class,2,TRUE];

    /**
     * 通道5剩余时间
     * @var int
     */
    protected $channel5 = [BIN::class,2,TRUE];

    /**
     * 通道6剩余时间
     * @var int
     */
    protected $channel6 = [BIN::class,2,TRUE];

    /**
     * 通道7剩余时间
     * @var int
     */
    protected $channel7 = [BIN::class,2,TRUE];

    /**
     * 通道8剩余时间
     * @var int
     */
    protected $channel8 = [BIN::class,2,TRUE];

    /**
     * 通道9剩余时间
     * @var int
     */
    protected $channel9 = [BIN::class,2,TRUE];
}