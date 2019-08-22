<?php

namespace App\Notifications;

use Illuminate\Support\Str;

class NotificationRepository
{
    /**
     * {@inheritdoc}
     */
    public function recent($user)
    {
        // Retrieve all unread notifications for the user...
        $unreadNotifications = AppNotification::with('creator')
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->orderBy('created_at', 'desc')
            ->get();

        // Retrieve the 8 most recent read notifications for the user...
        $readNotifications = AppNotification::with('creator')
            ->where('user_id', $user->id)
            ->whereNotNull('read_at')
            ->orderBy('created_at', 'desc')
            ->take(8)
            ->get();

        // Add the read notifications to the unread notifications so they show afterwards...
        $notifications = $unreadNotifications
            ->merge($readNotifications)
            ->sortByDesc('created_at');

        // if (count($notifications) > 0) {
        //     AppNotification::whereNotIn('id', $notifications->pluck('id'))
        //         ->where('user_id', $user->id)
        //         ->where('created_at', '<', $notifications->first()->created_at)
        //         ->delete();
        // }

        return $notifications->values();
    }

    /**
     * {@inheritdoc}
     */
    public function create($user, array $data, $type)
    {
        $creator = array_pull($data, 'from');
        $creator_id = array_pull($data, 'created_by');
        $data['id'] = Str::uuid()->toString();
        $data['user_id'] = $user->id;
        $data['created_by'] = $creator ? $creator->id : $creator_id;
        $data['type'] = $type;
        $data['channel'] = 'app';

        return AppNotification::create($data);
    }
}
