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
class CheckStopChargeResponse implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var string 桩编号
     */
    protected $code;

    /**
     * @var string 订单编号
     */
    protected $order_number;

    /**
     * @var string 启动结果
     */
    protected $result;

    /**
     * @var string 剩余时间
     */
    protected $left_time;

    /**
     * @var string 停止时间
     */
    protected $stop_time;



    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($code, $order_number, $result, $left_time, $stop_time)
    {

        $this->code = $code;
        $this->order_number = $order_number;
        $this->result = $result;
        $this->left_time = $left_time;
        $this->stop_time = $stop_time;

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $evseController = new EvseController();
        $evseController->checkStopChargeResponse($this->code, $this->order_number, $this->result, $this->left_time, $this->stop_time);

    }



}