<?php

namespace App\Services\V1;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function __construct(
        private readonly NotificationRepositoryInterface $notifications,
    ) {}

    public function list(User $user): LengthAwarePaginator
    {
        Log::info('Notifications list requested', ['user_uid' => $user->firebase_uid]);

        return $this->notifications->forRecipient($user->firebase_uid);
    }

    public function unreadCount(User $user): int
    {
        Log::info('Unread notification count requested', ['user_uid' => $user->firebase_uid]);

        return $this->notifications->unreadCount($user->firebase_uid);
    }

    public function markRead(User $user, string $notificationUuid)
    {
        Log::info('Notification markRead started', [
            'user_uid' => $user->firebase_uid,
            'notification_uuid' => $notificationUuid,
        ]);

        $notification = $this->notifications->findForRecipientByUuid($user->firebase_uid, $notificationUuid);
        if (! $notification) {
            throw ApiException::notFound('Notification not found.');
        }

        Log::info('Notification markRead completed', [
            'user_uid' => $user->firebase_uid,
            'notification_uuid' => $notificationUuid,
        ]);

        return $this->notifications->markRead($notification, $user->firebase_uid);
    }

    public function markAllRead(User $user): int
    {
        Log::info('Notification markAllRead started', ['user_uid' => $user->firebase_uid]);
        $count = $this->notifications->markAllRead($user->firebase_uid, $user->firebase_uid);
        Log::info('Notification markAllRead completed', [
            'user_uid' => $user->firebase_uid,
            'updated' => $count,
        ]);

        return $count;
    }
}
