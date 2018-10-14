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
     * @var string worker_id
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
    public function __construct($code, $num, $worker_id, $version)
    {
        $this->code = $code;
        $this->num = $num;
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
        $evseController->signReport($this->code, $this->num, $this->worker_id, $this->version);
    }
}