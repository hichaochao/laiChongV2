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
class CheckResponse implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var string 桩编号
     */
    protected $code;

    /**
     * @var string workId
     */
    protected $workeId;

    /**
     * @var string 帧
     */
    protected $frame;

    /**
     * @var string 订单号
     */
    protected $orderId;

    /**
     * @var string 类型
     */
    protected $type;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($code, $orderId, $type, $workeId, $frame)
    {
        $this->code = $code;
        $this->workeId = $workeId;
        $this->frame = $frame;
        $this->orderId = $orderId;
        $this->type = $type;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $evseController = new EvseController();
        $evseController->response( $this->code, $this->orderId, $this->type, $this->workeId, $this->frame );
    }



}