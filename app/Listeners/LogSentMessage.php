<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSent;

use Illuminate\Support\Facades\Log;

class LogSentMessage
{
    public function handle(MessageSent $event)
    {
        if (!empty($event->data['user'])) {
            Log::debug('Message sent.', [
                'id' => $event->message->getID(),
                'email' => $event->data['user']->email
            ]);
        }
    }
}
