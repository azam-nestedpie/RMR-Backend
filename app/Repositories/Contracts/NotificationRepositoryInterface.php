<?php

namespace App\Repositories\Contracts;

use App\Models\Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface NotificationRepositoryInterface
{
    public function forRecipient(string $firebaseUid): LengthAwarePaginator;

    public function unreadCount(string $firebaseUid): int;

    public function create(array $attributes): Notification;

    public function findForRecipientByUuid(string $firebaseUid, string $notificationUuid): ?Notification;

    public function markRead(Notification $notification, string $updatedByUid): Notification;

    public function markAllRead(string $firebaseUid, string $updatedByUid): int;
}
