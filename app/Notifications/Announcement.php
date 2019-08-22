<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Lang;

class Announcement extends Notification
{
    use Queueable;
    /**
     * string $body
     */
    private $body;
    /**
     * string  $subject
     */
    // private $subject;
    /**
     * string  $language
     */
    // private $language;

    /**
     * Create a new notification instance.
     * @param string $body
     * @return void
     */
    public function __construct($body)
    {
        // $this->subject = $subject;
        $this->body = $body;
        // $this->language = $language;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return [AppChannel::class];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return // ->subject('[Kinchaku] ' . $this->subject)
        (new MailMessage())->line($this->getBody());
    }

    public function getBody()
    {
        return $this->body;
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }

    public function toApp($notifiable)
    {
        return [
            'created_by' => auth()->id(),
            'icon' => 'announcement',
            'body' => $this->getBody()
        ];
    }
}
