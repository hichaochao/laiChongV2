<?php
/**
 * Created by PhpStorm.
 * User: lingf
 * Date: 2017-03-10
 * Time: 14:36
 */

namespace Wormhole\Protocols\LaiChongV2\TenRoad\Jobs;


use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

use Wormhole\Protocols\LaiChongV2\TenRoad\Controllers\EvseController;
class StopChargeResponse implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var string 桩编号
     */
    protected $code;

    /**
     * @var string 订单编号
     */
    protected $order_no;

    /**
     * @var string 剩余时间
     */
    protected $left_time;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($code, $order_no, $left_time)
    {
        $this->code = $code;
        $this->order_number = $order_no;
        $this->left_time = $left_time;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $evseController = new EvseController();
        $evseController->stopChargeResponse($this->code, $this->order_number, $this->left_timess);
    }



}