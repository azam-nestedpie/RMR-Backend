<?php

namespace App\Services\Migration;

use Illuminate\Support\Facades\DB;

class ExternalUsersMigrationService extends BaseMigrationService
{
    protected string $collection = 'external_users';

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
        return $doc['inviteId'] ?? $doc['_id'] ?? 'unknown_'.uniqid();
    }

    protected function insertDocument(array $doc): void
    {
        $externalUuid = $doc['_id'] ?? $doc['userId'] ?? null;

        if (empty($externalUuid)) {
            throw new \InvalidArgumentException('External user doc missing UUID.');
        }

        if (empty($doc['email'])) {
            throw new \InvalidArgumentException("External user [{$externalUuid}] missing email.");
        }

        $now = now();

        DB::table('external_users')->updateOrInsert(
            ['external_uuid' => $externalUuid],
            [
                'first_name' => $doc['name'] ?? 'External',
                'last_name' => $doc['lastName'] ?? null,
                'email' => strtolower(trim($doc['email'])),
                'company_name' => $doc['companyName'] ?? null,
                'position' => $doc['position'] ?? null,
                'created_at' => $this->parseFirestoreDate($doc['createdAt'] ?? null) ?? $now,
                'updated_at' => $this->parseFirestoreDate($doc['updatedAt'] ?? null) ?? $now,
            ]
        );
    }
}
