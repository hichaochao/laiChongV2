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
class offlineStartResponse implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var string 桩编号
     */
    protected $code;

    /**
     * @var string 通道号
     */
    protected $channel_number;

    /**
     * @var string 启动类型
     */
    protected $start_type;

    /**
     * @var string 金额
     */
    protected $amount_money;

    /**
     * @var string 充电时长
     */
    protected $duration;

    /**
     * @var string 卡号
     */
    protected $card_num;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($code, $channel_number, $start_type, $amount_money, $duration, $card_num)
    {
        $this->code = $code;
        $this->channel_number = $channel_number;
        $this->start_type = $start_type;
        $this->amount_money = $amount_money;
        $this->duration = $duration;
        $this->card_num = $card_num;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $evseController = new EvseController();
        $evseController->offlineStartResponse($this->code, $this->channel_number, $this->start_type, $this->amount_money, $this->duration, $this->card_num);

    }



}