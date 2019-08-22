<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;

use Illuminate\Support\Facades\Log;

class LogSendingMessage
{
    public function handle(MessageSending $event)
    {
        Log::debug('Message sending...');
        // Log::debug($event);
    }
}
