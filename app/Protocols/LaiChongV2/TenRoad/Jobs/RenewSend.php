<?php
/**
 * Created by PhpStorm.
 * User: chao
 * Date: 2017-03-10
 * Time: 14:36
 */

namespace Wormhole\Protocols\LaiChongV2\TenRoad\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

use Wormhole\Protocols\LaiChongV2\TenRoad\Controllers\EvseController;
class RenewSend implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var string monitor订单号
     */
    protected $monitor_order_id;

    /**
     * @var string monitor编号
     */
    protected $monitor_code;

    /**
     * @var string 充电类型
     */
    protected $port_numbers;

    /**
     * @var string 充电时间
     */
    protected $charge_time;

    /**
     * @var string 订单号
     */
    protected $order_no;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($monitor_order_id, $monitor_code, $port_numbers, $charge_time, $order_no)
    {
        $this->monitor_order_id = $monitor_order_id;
        $this->monitor_code = $monitor_code;
        $this->port_numbers = $port_numbers;
        $this->charge_time = $charge_time;
        $this->order_no = $order_no;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $evseController = new EvseController();
        $evseController->renewSend($this->monitor_order_id, $this->monitor_code, $this->port_numbers, $this->charge_time, $this->order_no);
    }



}