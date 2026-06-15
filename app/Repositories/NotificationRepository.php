<?php

namespace App\Repositories;

use App\Models\Notification;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationRepository implements NotificationRepositoryInterface
{
    public function forRecipient(string $firebaseUid): LengthAwarePaginator
    {
        return Notification::forRecipient($firebaseUid)
            ->with('sender')
            ->orderByDesc('sent_at')
            ->paginate(20);
    }

    public function unreadCount(string $firebaseUid): int
    {
        return Notification::forRecipient($firebaseUid)->unread()->count();
    }

    public function create(array $attributes): Notification
    {
        return Notification::create($attributes);
    }

    public function findForRecipientByUuid(string $firebaseUid, string $notificationUuid): ?Notification
    {
        return Notification::where('firebase_uuid', $notificationUuid)
            ->forRecipient($firebaseUid)
            ->first();
    }

    public function markRead(Notification $notification, string $updatedByUid): Notification
    {
        $notification->update([
            'is_read' => true,
            'read_at' => now(),
            'updated_by' => $updatedByUid,
            'updated_at' => now(),
        ]);

        return $notification;
    }

    public function markAllRead(string $firebaseUid, string $updatedByUid): int
    {
        return Notification::forRecipient($firebaseUid)
            ->unread()
            ->update([
                'is_read' => true,
                'read_at' => now(),
                'updated_by' => $updatedByUid,
                'updated_at' => now(),
            ]);
    }
}
