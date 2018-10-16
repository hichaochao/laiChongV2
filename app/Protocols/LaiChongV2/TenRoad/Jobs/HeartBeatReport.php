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
class HeartBeatReport implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var string 设备编号
     */
    protected $code;

    /**
     * @var string client_id
     */
    protected $client_id;

    /**
     * @var string 锁定通道
     */
    protected $lock_status;

    /**
     * @var string 工作状态
     */
    protected $work_status;

    /**
     * @var string 故障状态
     */
    protected $fault_status;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($code, $client_id, $lock_status, $work_status, $fault_status)
    {
        $this->code = $code;
        $this->client_id = $client_id;
        $this->lock_status = $lock_status;
        $this->work_status = $work_status;
        $this->fault_status = $fault_status;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $evseController = new EvseController();
        $evseController->heartBeatReport($this->code, $this->client_id, $this->lock_status, $this->work_status, $this->fault_status);
    }



}