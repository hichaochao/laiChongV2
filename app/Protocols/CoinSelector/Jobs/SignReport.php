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
class SignReport implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var string 桩编号
     */
    protected $code;

    /**
     * @var string 枪口数量
     */
    protected $num;

    /**
     * @var string 设备编号
     */
    protected $device_identification;

    /**
     * @var string 心跳周期
     */
    protected $heabeat_cycle;

    /**
     * @var string 设备编号
     */
    protected $worker_id;

    /**
     * @var string 版本号
     */
    protected $version;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($code, $num, $device_identification, $heabeatCycle, $worker_id, $version)
    {

        $this->code = $code;
        $this->num = $num;
        $this->device_identification = $device_identification;
        $this->heabeat_cycle = $heabeatCycle;
        $this->worker_id = $worker_id;
        $this->version = $version;

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $evseController = new EvseController();
        $evseController->signReport($this->code, $this->num, $this->device_identification, $this->heabeat_cycle, $this->worker_id, $this->version);

    }



}