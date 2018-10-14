<?php
/**
 * Created by PhpStorm.
 * User: lingf
 * Date: 2017-03-10
 * Time: 14:36
 */

namespace Wormhole\Protocols\TenRoad\Jobs;


use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

use Wormhole\Protocols\TenRoad\Controllers\EvseController;
class CheckStartCharge implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var string monitor订单号
     */
    protected $monitorOrderId;

    /**
     * @var string monitor编号
     */
    protected $monitorCode;

    /**
     * @var string 充电类型
     */
    protected $chargeType;

    /**
     * @var string 充电时间
     */
    protected $chargeArgs;

    /**
     * @var string 订单号
     */
    protected $orderId;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($monitorOrderId, $monitorCode, $chargeType, $chargeArgs, $orderId)
    {

        $this->monitorOrderId = $monitorOrderId;
        $this->monitorCode = $monitorCode;
        $this->chargeType = $chargeType;
        $this->chargeArgs = $chargeArgs;
        $this->orderId = $orderId;

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $evseController = new EvseController();
        $evseController->checkStartCharge($this->monitorOrderId, $this->monitorCode, $this->chargeType, $this->chargeArgs, $this->orderId);

    }



}