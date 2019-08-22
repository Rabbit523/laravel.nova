<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;

use Illuminate\Http\Request;
use App\Notifications\AppNotification;
use App\Notifications\NotificationRepository;

class NotificationController extends ApiController
{
    /**
     * The notifications repository.
     *
     * @var NotificationRepository
     */
    protected $notifications;

    /**
     * Create a new controller instance.
     *
     * @param  NotificationRepository  $notifications
     * @return void
     */
    public function __construct(NotificationRepository $notifications)
    {
        $this->notifications = $notifications;
    }

    /**
     * Get the recent notifications and announcements for the user.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        return $this->respond([
            "notifications" => $this->notifications->recent(user())->toArray()
        ]);
    }

    /**
     * Mark the given notifications as read.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsRead(Request $request)
    {
        AppNotification::whereIn('id', collect($request->notifications)->pluck('id'))
            ->whereUserId(auth()->id())
            ->update(['read_at' => now()]);
        return $this->respondSuccess();
    }
}
