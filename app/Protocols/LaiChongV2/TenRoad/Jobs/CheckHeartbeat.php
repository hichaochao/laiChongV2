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
class CheckHeartbeat implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var string 充电桩id
     */
    protected $id;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($id)
    {

        $this->id = $id;

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $evseController = new EvseController();
        $evseController->checkHeartbeat($this->id);
    }
}