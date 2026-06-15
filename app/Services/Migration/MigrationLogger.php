<?php

namespace App\Services\Migration;

use App\Models\MigrationLog;
use Illuminate\Support\Facades\Log;

class MigrationLogger
{
    private array $stats = [];

    public function __construct()
    {
        //
    }

    public function logSuccess(string $collection, string $docId): void
    {
        MigrationLog::create([
            'entity_type' => $collection,
            'collection' => $collection,
            'firestore_doc_id' => $docId,
            'old_id' => $docId,
            'status' => 'success',
            'migrated_by' => 'service',
            'migrated_at' => now(),
        ]);
        $this->inc($collection, 'success');
        Log::info("[{$collection}] ✓ {$docId}");
    }

    public function logFailed(string $collection, string $docId, string $error, array $raw = []): void
    {
        MigrationLog::create([
            'entity_type' => $collection,
            'collection' => $collection,
            'firestore_doc_id' => $docId,
            'old_id' => $docId,
            'status' => 'failed',
            'error_message' => $error,
            'raw_data' => $raw,
            'migrated_by' => 'service',
            'migrated_at' => now(),
        ]);
        $this->inc($collection, 'failed');
        Log::error("[{$collection}] ✗ {$docId} — {$error}");
    }

    public function logSkipped(string $collection, string $docId, string $reason): void
    {
        MigrationLog::create([
            'entity_type' => $collection,
            'collection' => $collection,
            'firestore_doc_id' => $docId,
            'old_id' => $docId,
            'status' => 'skipped',
            'error_message' => $reason,
            'migrated_by' => 'service',
            'migrated_at' => now(),
        ]);
        $this->inc($collection, 'skipped');
        Log::info("[{$collection}] ~ {$docId} skipped: {$reason}");
    }

    public function info(string $msg, array $ctx = []): void
    {
        Log::info($msg, $ctx);
    }

    public function warning(string $msg, array $ctx = []): void
    {
        Log::warning($msg, $ctx);
    }

    public function error(string $msg, array $ctx = []): void
    {
        Log::error($msg, $ctx);
    }

    public function getSummary(): array
    {
        return $this->stats;
    }

    public function printSummary(): void
    {
        foreach ($this->stats as $col => $c) {
            $this->info("[{$col}] total=".array_sum($c)." success={$c['success']} failed={$c['failed']} skipped={$c['skipped']}");
        }
    }

    private function inc(string $col, string $key): void
    {
        $this->stats[$col] ??= ['success' => 0, 'failed' => 0, 'skipped' => 0];
        $this->stats[$col][$key]++;
    }
}
