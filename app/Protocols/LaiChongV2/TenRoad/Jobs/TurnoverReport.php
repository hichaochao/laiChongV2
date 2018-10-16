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
class TurnoverReport implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var string 桩编号
     */
    protected $code;

    /**
     * @var string 投币金额
     */
    protected $coin_num;

    /**
     * @var string 刷卡金额
     */
    protected $card_cost;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($code, $coin_num, $card_cost)
    {
        $this->code = $code;
        $this->coin_num = $coin_num;
        $this->card_cost = $card_cost;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $evseController = new EvseController();
        $evseController->getTurnover($this->code, $this->coin_num, $this->card_cost);
    }
}