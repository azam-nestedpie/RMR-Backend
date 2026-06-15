<?php

namespace App\Services\Migration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationsMigrationService extends BaseMigrationService
{
    protected string $collection = 'notifications';

    public function migrate(array $documents): array
    {
        $results = ['success' => 0, 'failed' => 0, 'skipped' => 0];
        $this->logger->info("[{$this->collection}] Starting. Total: ".count($documents));

        foreach ($documents as $doc) {
            $results[$this->migrateDocument($doc)]++;
        }

        $this->logger->info("[{$this->collection}] Done: ".json_encode($results));

        return $results;
    }

    protected function getDocId(array $doc): string
    {
        return $doc['notificationId'] ?? $doc['_id'] ?? 'unknown_'.uniqid();
    }

    protected function insertDocument(array $doc): void
    {
        $toUserUid = $doc['toUserId'] ?? $doc['to'] ?? null;
        if (! $toUserUid) {
            throw new \InvalidArgumentException('Missing toUserId');
        }

        $toUserUid = $this->ensureReferencedUser($toUserUid);
        $fromUserUid = $this->ensureReferencedUser($doc['fromUserId'] ?? $doc['from'] ?? null);
        $now = now();
        $sentAt = $this->parseFirestoreDate($doc['sentAt'] ?? $doc['dateTime'] ?? null) ?? $now;

        DB::table('notifications')->insertOrIgnore([
            'firebase_uuid' => $doc['notificationId'] ?? $doc['id'] ?? $doc['_id'] ?? Str::uuid(),
            'to_user_firebase_uid' => $toUserUid,
            'from_user_firebase_uid' => $fromUserUid,
            'message' => $doc['message'] ?? '',
            'screen' => $doc['screen'] ?? null,
            'tab_index' => $doc['tabIndex'] ?? null,
            'is_for_external_rating' => $doc['isForExternalRating'] ?? false,
            'is_read' => $doc['isRead'] ?? false,
            'read_at' => $this->parseFirestoreDate($doc['readAt'] ?? null),
            'sent_at' => $sentAt,
            'created_by' => $fromUserUid ?? $toUserUid,
            'updated_by' => $fromUserUid ?? $toUserUid,
            'created_at' => $sentAt,
            'updated_at' => $this->parseFirestoreDate($doc['updatedAt'] ?? null) ?? $sentAt,
        ]);
    }
}
