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
class CheckReport implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var string 桩编号
     */
    protected $code;

    /**
     * @var string 电表编号
     */
    protected $meter_number;

    /**
     * @var string 时间
     */
    protected $date;

    /**
     * @var string 电量
     */
    protected $electricity;

    /**
     * @var string 总电量
     */
    protected $total_electricity;


    /**
     * @var string 投币次数
     */
    protected $coins_number;


    /**
     * @var string 刷卡金额
     */
    protected $card_amount;


    /**
     * @var string 刷卡时长
     */
    protected $card_time;










    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($code, $meter_number, $date, $electricity, $total_electricity, $coins_number, $card_amount, $card_time)
    {

        $this->code = $code;
        $this->meter_number = $meter_number;
        $this->date = $date;
        $this->electricity = $electricity;
        $this->total_electricity = $total_electricity;
        $this->coins_number = $coins_number;
        $this->card_amount = $card_amount;
        $this->card_time = $card_time;

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $evseController = new EvseController();
        $evseController->checkReport($this->code, $this->meter_number, $this->date, $this->electricity, $this->total_electricity, $this->coins_number, $this->card_amount, $this->card_time);

    }



}