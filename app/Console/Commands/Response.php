<?php

namespace Wormhole\Console\Commands;

use Illuminate\Console\Command;

class Response extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'response';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '接受桩主动上报或响应，并监听/处理';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $cmd = "php artisan queue:work --queue=".$this->signature;
        exec($cmd);
    }
}
