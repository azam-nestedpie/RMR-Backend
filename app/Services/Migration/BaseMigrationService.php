<?php

namespace App\Services\Migration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

abstract class BaseMigrationService
{
    protected string $collection;

    protected int $batchSize;

    protected int $batchDelayMs;

    protected int $maxRetries;

    public function __construct(protected MigrationLogger $logger)
    {
        $this->batchSize = config('migration.batch_size', 200);
        $this->batchDelayMs = config('migration.batch_delay_ms', 200);
        $this->maxRetries = config('migration.max_retries', 3);
    }

    abstract public function migrate(array $documents): array;

    abstract protected function insertDocument(array $doc): void;

    protected function migrateDocument(array $doc): string
    {
        $docId = $this->getDocId($doc);

        if ($this->alreadyMigrated($docId)) {
            $this->logger->logSkipped($this->collection, $docId, 'Already migrated');

            return 'skipped';
        }

        $attempt = 0;
        $lastErr = null;

        while ($attempt < $this->maxRetries) {
            $attempt++;
            try {
                DB::beginTransaction();
                $this->insertDocument($doc);
                DB::commit();
                $this->logger->logSuccess($this->collection, $docId);

                return 'success';
            } catch (\Throwable $e) {
                DB::rollBack();
                $lastErr = $e;
                if ($attempt < $this->maxRetries) {
                    usleep($this->batchDelayMs * 1000 * $attempt);
                }
            }
        }

        $this->logger->logFailed($this->collection, $docId, $lastErr?->getMessage() ?? 'Unknown', $doc);

        if (config('migration.stop_on_error')) {
            throw new \RuntimeException("Halted [{$this->collection}] [{$docId}]: ".$lastErr?->getMessage());
        }

        return 'failed';
    }

    /**
     * Resolve a Firestore firebase_uid → users.firebase_uid (the MySQL PK).
     *
     * In the new schema, firebase_uid is the primary key and foreign key everywhere.
     */
    protected function resolveFirebaseUid(string $firebaseUid): ?string
    {
        $exists = DB::table('users')
            ->where('firebase_uid', $firebaseUid)
            ->exists();

        return $exists ? $firebaseUid : null;
    }

    /**
     * Resolve multiple firebase UIDs.
     */
    protected function resolveFirebaseUids(array $firebaseUids): array
    {
        if (empty($firebaseUids)) {
            return ['resolved' => [], 'missing' => []];
        }

        $resolved = DB::table('users')
            ->whereIn('firebase_uid', $firebaseUids)
            ->pluck('firebase_uid')
            ->toArray();

        $missing = array_values(array_diff($firebaseUids, $resolved));

        return ['resolved' => $resolved, 'missing' => $missing];
    }

    protected function ensureReferencedUser(?string $firebaseUid): ?string
    {
        if (! is_string($firebaseUid) || trim($firebaseUid) === '') {
            return null;
        }

        $firebaseUid = trim($firebaseUid);

        if ($this->resolveFirebaseUid($firebaseUid) !== null) {
            return $firebaseUid;
        }

        $now = now();

        DB::table('users')->insertOrIgnore([
            'firebase_uid' => $firebaseUid,
            'first_name' => 'Migrated',
            'last_name' => 'User',
            'email' => Str::lower($firebaseUid).'@migration.local',
            'password' => null,
            'bio' => null,
            'image_url' => null,
            'company_name' => null,
            'position' => null,
            'is_blocked' => false,
            'is_deleted' => false,
            'fcm_token' => null,
            'created_by' => null,
            'updated_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $roleId = DB::table('roles')->where('name', 'rater')->value('id');

        if ($roleId !== null) {
            DB::table('user_roles')->insertOrIgnore([
                'user_firebase_uid' => $firebaseUid,
                'role_id' => $roleId,
                'created_by' => null,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $firebaseUid;
    }

    protected function statusId(?string $status, string $fallback = 'pending'): int
    {
        $normalized = Str::lower(trim((string) $status));

        return (int) (DB::table('statuses')->where('name', $normalized)->value('id')
            ?? DB::table('statuses')->where('name', $fallback)->value('id')
            ?? DB::table('statuses')->value('id'));
    }

    protected function userRoleId(string $firebaseUid): int
    {
        return (int) (DB::table('user_roles')
            ->where('user_firebase_uid', $firebaseUid)
            ->value('role_id')
            ?? DB::table('roles')->where('name', 'rater')->value('id')
            ?? DB::table('roles')->value('id'));
    }

    protected function userHasRole(string $firebaseUid, string $role): bool
    {
        return DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_firebase_uid', $firebaseUid)
            ->where('roles.name', $role)
            ->exists();
    }

    /**
     * Convert Firestore date string → MySQL dateTime-compatible string.
     * Handles: "2025-02-02 13:59:31.849159" and "2025-02-02T13:59:31.849159Z"
     */
    protected function parseFirestoreDate(?string $raw): ?string
    {
        if (empty($raw)) {
            return null;
        }
        try {
            $n = str_replace('T', ' ', rtrim($raw, 'Z'));
            $dt = \DateTime::createFromFormat('Y-m-d H:i:s.u', $n)
               ?? \DateTime::createFromFormat('Y-m-d H:i:s', $n);

            return $dt ? $dt->format('Y-m-d H:i:s') : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function getDocId(array $doc): string
    {
        return $doc['_id'] ?? $doc['userId'] ?? $doc['requestId']
            ?? $doc['connectionId'] ?? $doc['id'] ?? 'unknown_'.uniqid();
    }

    protected function alreadyMigrated(string $docId): bool
    {
        return DB::table('migration_logs')
            ->where('collection', $this->collection)
            ->where('firestore_doc_id', $docId)
            ->where('status', 'success')
            ->exists();
    }
}
