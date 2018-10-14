<?php
/**
 * Created by PhpStorm.
 * User: lingf
 * Date: 2017-03-02
 * Time: 18:20
 */

namespace Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse;

use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Frame;
use Wormhole\Protocols\Library\BIN;

class GetChannelStatus extends Frame
{


    protected $instructions = 0x1405;

    /**
     * 订单号
     * @var int
     */
    protected $order_number = [BIN::class,1,TRUE];

    /**
     * 通道号
     * @var int
     */
    protected $channel_num = [BIN::class,1,TRUE];

    /**
     * 电流平均值
     * @var int
     */
    protected $current_average = [BIN::class,2,TRUE];


    /**
     * 最大电流
     * @var int
     */
    protected $max_current = [BIN::class,2,TRUE];


    /**
     * 电流基数
     * @var int
     */
    protected $current_base = [BIN::class,2,TRUE];

    /**
     * 运行时间
     * @var int
     */
    protected $run_time = [BIN::class,2,TRUE];

    /**
     * 剩余时间
     * @var int
     */
    protected $left_time = [BIN::class,2,TRUE];

    /**
     * 充满计时
     * @var int
     */
    protected $full_time = [BIN::class,2,TRUE];

    /**
     * 支付方式
     * @var int
     */
    protected $payment_mode = [BIN::class,1,TRUE];

    /**
     * 设备状态
     * @var int
     */
    protected $equipment_status = [BIN::class,1,TRUE];







}