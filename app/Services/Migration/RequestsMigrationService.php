<?php

namespace App\Services\Migration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RequestsMigrationService extends BaseMigrationService
{
    protected string $collection = 'requests';

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

    protected function insertDocument(array $doc): void
    {
        $requesterUid = $doc['requesterId'] ?? $doc['requestedBy'] ?? null;
        $targetUid = $doc['targetId'] ?? $this->targetFromAssociatedIds($doc['associatedIds'] ?? [], $requesterUid);

        if (! $requesterUid || ! $targetUid) {
            throw new \InvalidArgumentException('Missing requesterId or targetId');
        }

        $requesterUid = $this->ensureReferencedUser($requesterUid);
        $targetUid = $this->ensureReferencedUser($targetUid);
        $statusId = $this->statusId($doc['status'] ?? $doc['requestStatus'] ?? null);

        $now = now();
        $requestedAt = $this->parseFirestoreDate($doc['createdAt'] ?? $doc['dateTime'] ?? null) ?? $now;

        if (($doc['requestType'] ?? null) === 'Rating') {
            $repUid = $this->userHasRole($requesterUid, 'rep') ? $requesterUid : $targetUid;
            $raterUid = $this->userHasRole($requesterUid, 'rater') ? $requesterUid : $targetUid;

            DB::table('rating_requests')->insertOrIgnore([
                'firebase_uuid' => $doc['requestId'] ?? $doc['_id'] ?? Str::uuid(),
                'requester_firebase_uid' => $requesterUid,
                'target_user_firebase_uid' => $targetUid,
                'manager_firebase_uid' => $doc['managerId'] ?? null,
                'behalf_firebase_uid' => $doc['behalfId'] ?? null,
                'requested_by_manager_firebase_uid' => $doc['requestedByManagerId'] ?? null,
                'rater_firebase_uid' => $raterUid,
                'subject_rep_firebase_uid' => $repUid,
                'requested_by_role_id' => $this->userRoleId($requesterUid),
                'status_id' => $statusId,
                'requested_at' => $requestedAt,
                'responded_at' => null,
                'completed_at' => $statusId === $this->statusId('completed') ? $requestedAt : null,
                'created_by' => $requesterUid,
                'updated_by' => $requesterUid,
                'created_at' => $requestedAt,
                'updated_at' => $this->parseFirestoreDate($doc['updatedAt'] ?? null) ?? $requestedAt,
            ]);

            return;
        }

        DB::table('connection_requests')->insertOrIgnore([
            'firebase_uuid' => $doc['requestId'] ?? $doc['_id'] ?? Str::uuid(),
            'requester_firebase_uid' => $requesterUid,
            'target_user_firebase_uid' => $targetUid,
            'status_id' => $statusId,
            'created_by' => $requesterUid,
            'updated_by' => $requesterUid,
            'created_at' => $requestedAt,
            'updated_at' => $this->parseFirestoreDate($doc['updatedAt'] ?? null) ?? $requestedAt,
        ]);
    }

    private function targetFromAssociatedIds(mixed $associatedIds, ?string $requesterUid): ?string
    {
        if (! is_array($associatedIds)) {
            return null;
        }

        return collect($associatedIds)
            ->filter(fn (mixed $uid): bool => is_string($uid) && $uid !== $requesterUid)
            ->first();
    }
}
