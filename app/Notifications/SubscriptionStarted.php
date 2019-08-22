<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Lang;

class SubscriptionStarted extends Notification
{
    use Queueable;
    private $plan;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($plan)
    {
        $this->plan = $plan;
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
            ->line(Lang::getFromJson('You can add more projects or change the plan anytime.'))
            ->action(
                Lang::getFromJson('Go to the Billing'),
                url(config('app.url') . '/billing/')
            );
    }

    public function getBody()
    {
        return Lang::getFromJson('Subscribed to :plan', [
            'plan' => Lang::getFromJson($this->plan)
        ]);
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
