<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Lang;

use App\Team;
use App\User;

class TeamLeft extends Notification
{
    use Queueable;

    private $team;
    private $user;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Team $team, User $user)
    {
        $this->team = $team;
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

    public function getBody()
    {
        return (
            Lang::getFromJson(':user has left :team', [
                'user' => $this->user->name,
                'team' => $this->team->name
            ])
        );
    }

    public function toApp($notifiable)
    {
        return [
            'icon' => 'supervisor_account',
            'body' => $this->getBody(),
            'action_text' => Lang::getFromJson('See The Team'),
            'action_url' => '/teams/' . $this->team->id
        ];
    }
}
