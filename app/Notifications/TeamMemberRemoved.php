<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Lang;

use App\Team;
use App\User;

class TeamMemberRemoved extends Notification
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
                    'You are receiving this email because :user removed you from the team, :team.',
                    ['user' => $this->user->name, 'team' => $this->team->name]
                )
            )
            ->line(Lang::getFromJson('No further action is required.'));
    }

    public function getBody()
    {
        return (
            Lang::getFromJson(':user has removed you from :team', [
                'user' => $this->user->name,
                'team' => $this->team->name
            ])
        );
    }

    public function toApp($notifiable)
    {
        return [
            'icon' => 'supervisor_account',
            'body' => $this->getBody()
        ];
    }
}
