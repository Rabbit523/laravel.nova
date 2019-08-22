<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Lang;

use App\User;

class TeamMemberJoined extends Notification
{
    use Queueable;

    private $by;
    private $user;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(User $by, User $user)
    {
        $this->by = $by;
        $this->user = $user;
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

    public function getBody($name = '')
    {
        return (
            Lang::getFromJson(':user has added :user2 to :team', [
                'user' => $this->by->name,
                'user2' => $this->user->name,
                'team' => $name
            ])
        );
    }

    public function toApp($notifiable)
    {
        return [
            'icon' => 'supervisor_account',
            'body' => $this->getBody($notifiable->name),
            'action_text' => Lang::getFromJson('See The Team'),
            'action_url' => '/teams/' . $notifiable->id
        ];
    }
}
