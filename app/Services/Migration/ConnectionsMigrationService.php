<?php

namespace App\Services\Migration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConnectionsMigrationService extends BaseMigrationService
{
    protected string $collection = 'connections';

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
        $associatedIds = $doc['associatedIds'] ?? [];
        $userA = $doc['userAId'] ?? (is_array($associatedIds) ? ($associatedIds[0] ?? null) : null);
        $userB = $doc['userBId'] ?? (is_array($associatedIds) ? ($associatedIds[1] ?? null) : null);

        if (! $userA || ! $userB) {
            throw new \InvalidArgumentException('Missing userAId or userBId');
        }

        $userA = $this->ensureReferencedUser($userA);
        $userB = $this->ensureReferencedUser($userB);
        $connectedBy = $this->ensureReferencedUser($doc['connectedById'] ?? $doc['connectedBy'] ?? null);
        $now = now();
        $connectedAt = $this->parseFirestoreDate($doc['connectedAt'] ?? $doc['dateTime'] ?? null) ?? $now;

        DB::table('connections')->insertOrIgnore([
            'firebase_uuid' => $doc['connectionId'] ?? $doc['_id'] ?? Str::uuid(),
            'user_a_firebase_uid' => $userA,
            'user_b_firebase_uid' => $userB,
            'connected_by_uid' => $connectedBy,
            'source_request_id' => isset($doc['sourceRequestId']) || isset($doc['requestId']) ? DB::table('connection_requests')->where('firebase_uuid', $doc['sourceRequestId'] ?? $doc['requestId'])->value('id') : null,
            'connected_at' => $connectedAt,
            'is_active' => $doc['isActive'] ?? true,
            'created_by' => $userA,
            'updated_by' => $userA,
            'created_at' => $connectedAt,
            'updated_at' => $connectedAt,
        ]);
    }
}
