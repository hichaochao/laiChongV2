<?php

namespace Wormhole\Http\Controllers;

use Wormhole\Http\Controllers\ProcessPodcast;
use Illuminate\Http\Request;
use Wormhole\Http\Controllers\Controller;

///use Illuminate\Http\Request;
use Illuminate\Database\Query;
class SendReminderEmail extends Controller
{
    public function store(Request $request)
    {
        echo 123;
//        for($i = 0; $i < 100; $i ++) {
//            Queue::push(new SendEmail("ssss".$i));
//        }

    }
}
