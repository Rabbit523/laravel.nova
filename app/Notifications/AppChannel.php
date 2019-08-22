<?php

namespace App\Notifications;

use RuntimeException;
use Illuminate\Notifications\Notification;

class AppChannel
{
    /**
     * The notifications repository implementation.
     *
     * @var NotificationRepository
     */
    private $notifications;

    /**
     * Create a new channel instance.
     *
     * @param  NotificationRepository  $notifications
     * @return void
     */
    public function __construct(NotificationRepository $notifications)
    {
        $this->notifications = $notifications;
    }

    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  Notification  $notification
     * @return void
     */
    public function send($notifiable, Notification $notification, $failed = false)
    {
        if (!is_object($notifiable) && $notifiable == 'App\User') {
            $users = \App\User::all();
        } else {
            switch (get_class($notifiable)) {
                case 'App\Team':
                    $users = $notifiable->members;
                    $users = $users->merge([$notifiable->user])->unique('id');
                    break;
                case 'App\Project':
                    $users = $notifiable->team_members;
                    $users = $users->merge([$notifiable->user])->unique('id');
                    break;
                default:
                    $users = [$notifiable];
                    break;
            }
        }

        foreach ($users as $user) {
            $data = $this->getData($user, $notification);
            if (!count($data)) {
                return;
            }
            $data['failed'] = $failed;
            $this->notifications->create(
                $user,
                $data,
                str_replace('App\Notifications\\', '', get_class($notification))
            );
        }
    }

    /**
     * Get the data for the notification.
     *
     * @param  mixed  $notifiable
     * @param  Notification  $notification
     * @return array
     *
     * @throws RuntimeException
     */
    protected function getData($notifiable, Notification $notification)
    {
        return method_exists($notification, 'toApp') ? $notification->toApp($notifiable) : [];
    }
}
