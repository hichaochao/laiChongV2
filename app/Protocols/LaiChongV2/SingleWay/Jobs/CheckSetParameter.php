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
class CheckSetParameter implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var string 参数名称
     */
    protected $parameterName;

    /**
     * @var string 参数
     */
    protected $parameter;

    /**
     * @var string 充电桩code
     */
    protected $code;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($parameterName, $parameter, $code)
    {

        $this->parameterName = $parameterName;
        $this->parameter = $parameter;
        $this->code = $code;
        

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $evseController = new EvseController();
        $evseController->checkSetParameter($this->parameterName,  $this->parameter, $this->code);

    }



}