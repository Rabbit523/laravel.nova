<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Lang;

class AccountCreated extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage())
            ->subject('[Kinchaku] ' . $this->getBody())
            ->line(
                Lang::getFromJson(
                    'Congratulations. You have successfully created your account with us.'
                )
            );
    }

    public function getBody()
    {
        return Lang::getFromJson('Account Created');
    }

    public function toApp($notifiable)
    {
        return [
            'icon' => 'account_box',
            'body' => $this->getBody()
        ];
    }
}
