<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as base;
use Illuminate\Support\Facades\Lang;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPassword extends base
{
    /**
     * Build the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        if (static::$toMailCallback) {
            return call_user_func(static::$toMailCallback, $notifiable, $this->token);
        }

        return (new MailMessage())
            ->subject('[Kinchaku] ' . $this->getBody())
            ->line(
                Lang::getFromJson(
                    'You are receiving this email because we received a password reset request for your account.'
                )
            )
            ->action(
                Lang::getFromJson('Reset Password'),
                url(config('app.url') . '/reset/' . $this->token)
            )
            ->line(
                Lang::getFromJson(
                    'If you did not request a password reset, no further action is required.'
                )
            );
    }

    public function getBody()
    {
        return Lang::getFromJson('Reset Password Notification');
    }

    public function toApp($notifiable)
    {
        return [
            'icon' => 'vpn_key',
            'body' => Lang::getFromJson('Account password has been updated')
        ];
    }
}
