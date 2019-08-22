<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Lang;

class TrialEnded extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct()
    {
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
                Lang::getFromJson('You are receiving this email because your trial has ended.')
            )
            ->action(
                Lang::getFromJson('Go to the Billing'),
                url(config('app.url') . '/billing/')
            );
    }

    public function getBody()
    {
        return Lang::getFromJson('Your trial has ended');
    }

    public function toApp($notifiable)
    {
        return [
            'icon' => 'account_balance_wallet',
            'body' => $this->getBody(),
            'action_text' => Lang::getFromJson('Go to the Billing'),
            'action_url' => '/billing/'
        ];
    }
}
