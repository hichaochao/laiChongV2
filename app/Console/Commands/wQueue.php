<?php

namespace Wormhole\Console\Commands;

use Illuminate\Console\Command;

class wQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wQueue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '在基础queue上增加一层，实现自动获取当前app_key，并监听/处理';

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
        $key = env('APP_KEY');
        $cmd = "php artisan queue:work --queue=".$key." --tries=3";
        exec($cmd);
    }
}
