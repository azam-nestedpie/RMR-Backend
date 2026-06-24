<?php

namespace App\Repositories;

use App\Models\Connection;
use App\Models\DashboardExport;
use App\Models\Rating;
use App\Models\RatingEdit;
use App\Models\RatingRequest;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardRepository implements DashboardRepositoryInterface
{
    public function averageTeamRating(array $teamMemberUids): float
    {
        return round((float) Rating::query()
            ->whereIn('rep_firebase_uid', $teamMemberUids)
            ->avg('average_score'), 2);
    }

    public function averageRatingByQuestion(array $teamMemberUids, string $locale = 'en'): Collection
    {
        return $this->averageRatingByQuestionForColumn($teamMemberUids, 'rep_firebase_uid', $locale);
    }

    public function averageRatingByQuestionForRole(array $teamMemberUids, int $managerRole, string $locale = 'en'): Collection
    {
        return $this->averageRatingByQuestionForColumn($teamMemberUids, $this->ratingColumnForManagerRole($managerRole), $locale);
    }

    private function averageRatingByQuestionForColumn(array $teamMemberUids, string $column, string $locale = 'en'): Collection
    {
        $titleColumn = in_array($locale, ['en', 'es', 'pt']) ? "title_{$locale}" : 'title_en';

        return DB::table('rating_items')
            ->join('ratings', 'rating_items.rating_id', '=', 'ratings.id')
            ->join('rating_questions', 'rating_items.question_id', '=', 'rating_questions.id')
            ->whereIn("ratings.{$column}", $teamMemberUids)
            ->groupBy('rating_items.question_id', "rating_questions.{$titleColumn}", 'rating_questions.question_code')
            ->selectRaw("rating_items.question_id, rating_questions.question_code, rating_questions.{$titleColumn} as title, AVG(rating_items.score) as average_score, COUNT(*) as response_count")
            ->orderBy('rating_questions.question_code')
            ->get();
    }

    public function ratingAverageBetween(array $teamMemberUids, \DateTimeInterface $from, \DateTimeInterface $to): float
    {
        return $this->ratingAverageBetweenForColumn($teamMemberUids, 'rep_firebase_uid', $from, $to);
    }

    public function ratingAverageBetweenForRole(array $teamMemberUids, int $managerRole, \DateTimeInterface $from, \DateTimeInterface $to): float
    {
        return $this->ratingAverageBetweenForColumn($teamMemberUids, $this->ratingColumnForManagerRole($managerRole), $from, $to);
    }

    private function ratingAverageBetweenForColumn(array $teamMemberUids, string $column, \DateTimeInterface $from, \DateTimeInterface $to): float
    {
        return round((float) Rating::query()
            ->whereIn($column, $teamMemberUids)
            ->whereBetween('rated_at', [$from, $to])
            ->avg('average_score'), 2);
    }

    public function feedbackCountBetween(array $teamMemberUids, \DateTimeInterface $from, \DateTimeInterface $to): int
    {
        return Rating::query()
            ->whereIn('rep_firebase_uid', $teamMemberUids)
            ->whereBetween('rated_at', [$from, $to])
            ->count();
    }

    public function historicalRatings(array $teamMemberUids, \DateTimeInterface $from, \DateTimeInterface $to): Collection
    {
        return $this->historicalRatingsForColumn($teamMemberUids, 'rep_firebase_uid', $from, $to);
    }

    public function historicalRatingsForRole(array $teamMemberUids, int $managerRole, \DateTimeInterface $from, \DateTimeInterface $to): Collection
    {
        return $this->historicalRatingsForColumn($teamMemberUids, $this->ratingColumnForManagerRole($managerRole), $from, $to);
    }

    private function historicalRatingsForColumn(array $teamMemberUids, string $column, \DateTimeInterface $from, \DateTimeInterface $to): Collection
    {
        $monthExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', rated_at)"
            : "DATE_FORMAT(rated_at, '%Y-%m')";

        return Rating::query()
            ->whereIn($column, $teamMemberUids)
            ->whereBetween('rated_at', [$from, $to])
            ->selectRaw($monthExpression.' as month, AVG(average_score) as average_rating, COUNT(*) as ratings_count')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');
    }

    public function recentFeedback(array $teamMemberUids, int $limit = 10): Collection
    {
        return $this->recentFeedbackForColumn($teamMemberUids, 'rep_firebase_uid', $limit);
    }

    public function recentFeedbackForRole(array $teamMemberUids, int $managerRole, int $limit = 10): Collection
    {
        return $this->recentFeedbackForColumn($teamMemberUids, $this->ratingColumnForManagerRole($managerRole), $limit);
    }

    private function recentFeedbackForColumn(array $teamMemberUids, string $column, int $limit): Collection
    {
        return Rating::query()
            ->whereIn($column, $teamMemberUids)
            ->with(['rater.roles', 'rep.roles', 'items.question'])
            ->orderByDesc('rated_at')
            ->limit($limit)
            ->get();
    }

    public function recentConnectionsForRater(string $raterUid, int $limit = 10): Collection
    {
        return Connection::query()
            ->forUser($raterUid)
            ->active()
            ->with(['userA.roles', 'userB.roles'])
            ->orderByDesc('connected_at')
            ->limit($limit)
            ->get();
    }

    public function recentRatingsGivenByRater(string $raterUid, int $limit = 10): Collection
    {
        return Rating::query()
            ->givenBy($raterUid)
            ->with('rep.roles')
            ->orderByDesc('rated_at')
            ->limit($limit)
            ->get();
    }

    public function recentRatingsForRep(string $repUid, int $limit = 10): Collection
    {
        return Rating::query()
            ->forRep($repUid)
            ->whereNotNull('rater_firebase_uid')
            ->with('rater.roles')
            ->orderByDesc('rated_at')
            ->limit($limit)
            ->get();
    }

    public function repRatingStats(array $repUids): Collection
    {
        return Rating::query()
            ->whereIn('rep_firebase_uid', $repUids)
            ->groupBy('rep_firebase_uid')
            ->selectRaw('rep_firebase_uid as firebase_uid, AVG(average_score) as average_rating, COUNT(*) as ratings_count')
            ->get()
            ->keyBy('firebase_uid');
    }

    public function ratingRequestCounts(array $teamMemberUids, \DateTimeInterface $from, \DateTimeInterface $to): object
    {
        return RatingRequest::query()
            ->where(function ($query) use ($teamMemberUids) {
                $query->whereIn('requester_firebase_uid', $teamMemberUids)
                    ->orWhereIn('target_user_firebase_uid', $teamMemberUids)
                    ->orWhereIn('behalf_firebase_uid', $teamMemberUids);
            })
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('COUNT(*) as total_requests')
            ->selectRaw('SUM(CASE WHEN completed_at IS NOT NULL THEN 1 ELSE 0 END) as completed_requests')
            ->first();
    }

    public function activeConnectionCount(array $teamMemberUids): int
    {
        return Connection::query()
            ->active()
            ->forTeamMembers($teamMemberUids)
            ->count();
    }

    public function teamMembersForManager(User $manager): Collection
    {
        return $manager->teamMembers()
            ->with(['roles', 'salesRepProfile'])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    public function averageTeamRatingForRole(array $teamMemberUids, int $managerRole): float
    {
        return round((float) $this->ratingsForManagerRole($teamMemberUids, $managerRole)->avg('average_score'), 2);
    }

    public function ratingsCountBetweenForRole(array $teamMemberUids, int $managerRole, \DateTimeInterface $from, \DateTimeInterface $to): int
    {
        return $this->ratingsForManagerRole($teamMemberUids, $managerRole)
            ->whereBetween('rated_at', [$from, $to])
            ->count();
    }

    public function ratingRequestsCountBetweenForRole(array $teamMemberUids, int $managerRole, \DateTimeInterface $from, \DateTimeInterface $to): int
    {
        $column = $managerRole === Role::MANAGER_OF_RATERS
            ? 'rater_firebase_uid'
            : 'subject_rep_firebase_uid';

        return RatingRequest::query()
            ->whereIn($column, $teamMemberUids)
            ->whereBetween('requested_at', [$from, $to])
            ->count();
    }

    public function teamMemberRatingStats(array $teamMemberUids, int $managerRole): Collection
    {
        $column = $managerRole === Role::MANAGER_OF_RATERS
            ? 'rater_firebase_uid'
            : 'rep_firebase_uid';

        return Rating::query()
            ->whereIn($column, $teamMemberUids)
            ->groupBy($column)
            ->selectRaw($column.' as firebase_uid, AVG(average_score) as rating, AVG(average_score) as average_rating, COUNT(*) as ratings_count')
            ->get()
            ->keyBy('firebase_uid');
    }

    public function teamMemberMonthlyRatingStats(array $teamMemberUids, \DateTimeInterface $from, \DateTimeInterface $to): Collection
    {
        return $this->teamMemberMonthlyRatingStatsForColumn($teamMemberUids, 'rep_firebase_uid', $from, $to);
    }

    public function teamMemberMonthlyRatingStatsForRole(array $teamMemberUids, int $managerRole, \DateTimeInterface $from, \DateTimeInterface $to): Collection
    {
        return $this->teamMemberMonthlyRatingStatsForColumn($teamMemberUids, $this->ratingColumnForManagerRole($managerRole), $from, $to);
    }

    private function teamMemberMonthlyRatingStatsForColumn(array $teamMemberUids, string $column, \DateTimeInterface $from, \DateTimeInterface $to): Collection
    {
        return Rating::query()
            ->whereIn($column, $teamMemberUids)
            ->whereBetween('rated_at', [$from, $to])
            ->groupBy($column)
            ->selectRaw($column.' as firebase_uid, AVG(average_score) as average_rating, COUNT(*) as ratings_count')
            ->get()
            ->keyBy('firebase_uid');
    }

    public function teamMemberConnectionCounts(array $teamMemberUids): Collection
    {
        return Connection::query()
            ->active()
            ->forTeamMembers($teamMemberUids)
            ->selectRaw('user_a_firebase_uid, user_b_firebase_uid')
            ->get()
            ->flatMap(function (Connection $connection) use ($teamMemberUids): array {
                $members = [];

                if (in_array($connection->user_a_firebase_uid, $teamMemberUids, true)) {
                    $members[] = $connection->user_a_firebase_uid;
                }

                if (in_array($connection->user_b_firebase_uid, $teamMemberUids, true)) {
                    $members[] = $connection->user_b_firebase_uid;
                }

                return $members;
            })
            ->countBy();
    }

    public function teamMemberRatingRequestCounts(array $teamMemberUids, \DateTimeInterface $from, \DateTimeInterface $to, array $resolvedStatusIds = []): Collection
    {
        $resolvedStatusIds = array_values(array_filter($resolvedStatusIds));
        $resolvedSql = 'SUM(CASE WHEN completed_at IS NOT NULL THEN 1 ELSE 0 END) as resolved_requests';

        if ($resolvedStatusIds !== []) {
            $resolvedSql = 'SUM(CASE WHEN completed_at IS NOT NULL OR status_id IN ('.implode(',', array_fill(0, count($resolvedStatusIds), '?')).') THEN 1 ELSE 0 END) as resolved_requests';
        }

        return RatingRequest::query()
            ->whereIn('subject_rep_firebase_uid', $teamMemberUids)
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('subject_rep_firebase_uid')
            ->selectRaw('subject_rep_firebase_uid as firebase_uid, COUNT(*) as total_requests')
            ->selectRaw('SUM(CASE WHEN completed_at IS NOT NULL THEN 1 ELSE 0 END) as completed_requests')
            ->selectRaw($resolvedSql, $resolvedStatusIds)
            ->get()
            ->keyBy('firebase_uid');
    }

    public function teamMemberClientEngagementCounts(array $teamMemberUids, int $managerRole): Collection
    {
        $requestColumn = $managerRole === Role::MANAGER_OF_RATERS
            ? 'rater_firebase_uid'
            : 'subject_rep_firebase_uid';

        return RatingRequest::query()
            ->whereIn($requestColumn, $teamMemberUids)
            ->groupBy($requestColumn)
            ->selectRaw($requestColumn.' as firebase_uid, COUNT(*) as total_requests')
            ->selectRaw(
                'SUM(CASE WHEN completed_at IS NOT NULL OR EXISTS (
                    SELECT 1
                    FROM ratings
                    WHERE ratings.rating_request_id = rating_requests.id
                        OR (
                            ratings.rater_firebase_uid = rating_requests.rater_firebase_uid
                            AND ratings.rep_firebase_uid = rating_requests.subject_rep_firebase_uid
                            AND ratings.rated_at >= rating_requests.requested_at
                        )
                ) THEN 1 ELSE 0 END) as submitted_ratings'
            )
            ->get()
            ->keyBy('firebase_uid');
    }

    public function teamMemberResolutionCounts(array $teamMemberUids, int $managerRole, float $threshold = 3.0): Collection
    {
        $column = $this->ratingColumnForManagerRole($managerRole);

        $ratings = Rating::query()
            ->whereIn($column, $teamMemberUids)
            ->select(['id', $column, 'rater_firebase_uid', 'rep_firebase_uid', 'average_score', 'rated_at'])
            ->get()
            ->keyBy('id');

        $edits = RatingEdit::query()
            ->whereIn('rating_id', $ratings->keys())
            ->select([
                'id',
                'rating_id',
                'previous_average_score',
                'new_average_score',
                'edited_at',
            ])
            ->orderBy('rating_id')
            ->orderBy('edited_at')
            ->orderBy('id')
            ->get();

        return $ratings
            ->groupBy($column)
            ->map(function (Collection $memberRatings, string $firebaseUid) use ($edits, $threshold): object {
                $lowRatings = 0;
                $resolvedRatings = 0;
                $memberRatingIds = $memberRatings->pluck('id');
                $memberEdits = $edits->whereIn('rating_id', $memberRatingIds);
                $ratingEvents = $this->ratingResolutionEvents($memberRatings, $memberEdits);

                $memberRatings->each(function (Rating $rating) use ($threshold, $ratingEvents, &$lowRatings, &$resolvedRatings): void {
                    $events = $ratingEvents->where('rating_id', $rating->id);
                    $firstLowEvent = $events->first(fn (array $event): bool => $event['score'] < $threshold);

                    if (! $firstLowEvent) {
                        return;
                    }

                    $lowRatings++;

                    $isResolved = $ratingEvents
                        ->where('rater_firebase_uid', $rating->rater_firebase_uid)
                        ->where('rep_firebase_uid', $rating->rep_firebase_uid)
                        ->filter(
                            fn (array $event): bool => $event['happened_at']->gt($firstLowEvent['happened_at'])
                                || (
                                    $event['happened_at']->equalTo($firstLowEvent['happened_at'])
                                    && $event['sequence'] > $firstLowEvent['sequence']
                                )
                        )
                        ->contains(fn (array $event): bool => $event['score'] > $threshold);

                    if ($isResolved) {
                        $resolvedRatings++;
                    }
                });

                return (object) [
                    'firebase_uid' => $firebaseUid,
                    'low_ratings' => $lowRatings,
                    'resolved_ratings' => $resolvedRatings,
                ];
            });
    }

    private function ratingResolutionEvents(Collection $ratings, Collection $edits): Collection
    {
        return $ratings
            ->flatMap(function (Rating $rating) use ($edits): array {
                $ratingEdits = $edits->where('rating_id', $rating->id)->values();
                $events = [
                    [
                        'rating_id' => $rating->id,
                        'rater_firebase_uid' => $rating->rater_firebase_uid,
                        'rep_firebase_uid' => $rating->rep_firebase_uid,
                        'score' => (float) ($ratingEdits->first()?->previous_average_score ?? $rating->average_score),
                        'happened_at' => $rating->rated_at,
                        'sequence' => 0,
                    ],
                ];

                foreach ($ratingEdits as $edit) {
                    $events[] = [
                        'rating_id' => $rating->id,
                        'rater_firebase_uid' => $rating->rater_firebase_uid,
                        'rep_firebase_uid' => $rating->rep_firebase_uid,
                        'score' => (float) $edit->new_average_score,
                        'happened_at' => $edit->edited_at,
                        'sequence' => (int) $edit->id,
                    ];
                }

                return $events;
            })
            ->sortBy([
                ['happened_at', 'asc'],
                ['sequence', 'asc'],
            ])
            ->values();
    }

    public function createExport(array $attributes): DashboardExport
    {
        return DashboardExport::create($attributes);
    }

    private function ratingsForManagerRole(array $teamMemberUids, int $managerRole): Builder
    {
        return Rating::query()->whereIn($this->ratingColumnForManagerRole($managerRole), $teamMemberUids);
    }

    private function ratingColumnForManagerRole(int $managerRole): string
    {
        return $managerRole === Role::MANAGER_OF_RATERS
            ? 'rater_firebase_uid'
            : 'rep_firebase_uid';
    }
}
