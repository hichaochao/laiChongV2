<?php

namespace Wormhole\Podcast;

use Wormhole\Jobs\SendReminderEmail;
use Illuminate\Http\Request;
use Wormhole\Http\Controllers\Controller;

class PodcastController extends Controller
{
    /**
     * Store a new podcast.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        // Create podcast...

        dispatch(new SendReminderEmail($podcast));
    }
}