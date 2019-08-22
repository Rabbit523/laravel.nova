<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Lang;

use App\User;

class UsernameChanged extends Notification
{
    use Queueable;

    private $name;
    private $user;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(User $user, $name)
    {
        $this->user = $user;
        $this->name = $name;
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

    public function getBody()
    {
        return (
            Lang::getFromJson(':user has changed his name to :name', [
                'user' => $this->user->name,
                'name' => $this->name
            ])
        );
    }

    public function toApp($notifiable)
    {
        return [
            'icon' => 'update',
            'body' => $this->getBody()
        ];
    }
}
