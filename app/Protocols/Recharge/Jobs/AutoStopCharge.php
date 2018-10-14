<?php
/**
 * Created by PhpStorm.
 * User: lingf
 * Date: 2017-03-10
 * Time: 14:36
 */

namespace Wormhole\Protocols\QianNiu\Jobs;


use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

use Wormhole\Protocols\QianNiu\Controllers\EvseController;
class AutoStopCharge implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var string monitor订单号
     */
    protected $monitorOrderId;
    
    /**
     * @var string 剩余时间
     */
    protected $left_time;

    /**
     * @var string 充电时长
     */
    protected $chargeArgs;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($monitorOrderId, $left_time, $chargeArgs)
    {

        $this->monitorOrderId = $monitorOrderId;
        $this->left_time = $left_time;
        $this->chargeArgs = $chargeArgs;

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $evseController = new EvseController();
        $evseController->AutoStopCharge($this->monitorOrderId, $this->left_time, $this->chargeArgs);

    }



}