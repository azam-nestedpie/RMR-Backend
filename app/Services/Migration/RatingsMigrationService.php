<?php

namespace App\Services\Migration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RatingsMigrationService extends BaseMigrationService
{
    protected string $collection = 'ratings';

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
        return $doc['ratingId'] ?? $doc['_id'] ?? 'unknown_'.uniqid();
    }

    protected function insertDocument(array $doc): void
    {
        $raterUid = $doc['raterId'] ?? $doc['from'] ?? null;
        $repUid = $doc['repId'] ?? $doc['to'] ?? null;

        if (! $raterUid || ! $repUid) {
            throw new \InvalidArgumentException('Missing raterId or repId');
        }

        $externalUserId = $this->externalUserId($raterUid);
        $raterUid = $externalUserId === null ? $this->ensureReferencedUser($raterUid) : null;
        $repUid = $this->ensureReferencedUser($repUid);
        $now = now();
        $ratedAt = $this->parseFirestoreDate($doc['ratedAt'] ?? $doc['dateTime'] ?? null) ?? $now;
        $averageScore = $this->averageScore($doc);

        $ratingId = DB::table('ratings')->insertGetId([
            'firebase_uuid' => $doc['ratingId'] ?? $doc['_id'] ?? Str::uuid(),
            'rater_firebase_uid' => $raterUid,
            'external_user_id' => $externalUserId,
            'rep_firebase_uid' => $repUid,
            'rating_request_id' => isset($doc['ratingRequestId']) ? DB::table('rating_requests')->where('firebase_uuid', $doc['ratingRequestId'])->value('id') : null,
            'from_external_link' => $externalUserId !== null || (bool) ($doc['fromExternalLink'] ?? $doc['fromLink'] ?? false),
            'rated_at' => $ratedAt,
            'average_score' => $averageScore,
            'created_by' => $raterUid,
            'updated_by' => $raterUid,
            'created_at' => $ratedAt,
            'updated_at' => $ratedAt,
        ]);

        // Migrate rating items (scores per question)
        $scores = $this->scores($doc);
        foreach ($scores as $code => $score) {
            $questionId = $this->ratingQuestionId((string) $code);
            if ($questionId) {
                DB::table('rating_items')->insertOrIgnore([
                    'rating_id' => $ratingId,
                    'question_id' => $questionId,
                    'score' => $score,
                    'created_by' => $raterUid,
                    'updated_by' => $raterUid,
                    'created_at' => $ratedAt,
                    'updated_at' => $ratedAt,
                ]);
            }
        }
    }

    private function externalUserId(string $externalUuid): ?int
    {
        $externalUserId = DB::table('external_users')
            ->where('external_uuid', $externalUuid)
            ->value('id');

        return $externalUserId ? (int) $externalUserId : null;
    }

    /**
     * @return array<string, int|float>
     */
    private function scores(array $doc): array
    {
        if (isset($doc['scores']) && is_array($doc['scores'])) {
            return $doc['scores'];
        }

        return collect($doc['ratings'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['id'], $item['rating']))
            ->mapWithKeys(fn (array $item): array => [(string) $item['id'] => $item['rating']])
            ->all();
    }

    private function averageScore(array $doc): float
    {
        $average = (float) ($doc['averageScore'] ?? $doc['avgRating'] ?? 0);

        if ($average > 5) {
            return round($average / 2, 2);
        }

        return round($average, 2);
    }

    private function ratingQuestionId(string $questionCode): int
    {
        $questionId = DB::table('rating_questions')
            ->where('question_code', $questionCode)
            ->value('id');

        if ($questionId) {
            return (int) $questionId;
        }

        return (int) DB::table('rating_questions')->insertGetId([
            'question_code' => $questionCode,
            'title_en' => 'Question '.$questionCode,
            'title_es' => null,
            'title_pt' => null,
            'is_active' => true,
            'created_by' => null,
            'updated_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
