<?php

namespace App\Services\V1;

use App\Mail\DashboardReportMail;
use App\Models\Connection;
use App\Models\Rating;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class DashboardService
{
    public function __construct(
        private readonly DashboardRepositoryInterface $dashboards,
        private readonly NotificationRepositoryInterface $notifications,
    ) {}

    public function dashboard(User $manager): array
    {
        $this->managerRole($manager);

        return Cache::remember(
            "dashboard_{$manager->firebase_uid}",
            now()->addMinutes(15),
            fn (): array => $this->dashboardPayload($manager)
        );
    }

    private function dashboardPayload(User $manager): array
    {
        $managerRole = $this->managerRole($manager);
        $locale = $manager->prefered_locale ?? 'en';
        $teamMembers = $this->dashboards->teamMembersForManager($manager);
        $teamMemberUids = $teamMembers->pluck('firebase_uid')->all();
        $thisMonthStart = now()->startOfMonth();
        $thisMonthEnd = now()->endOfMonth();
        $lastMonthStart = now()->subMonthNoOverflow()->startOfMonth();
        $lastMonthEnd = now()->subMonthNoOverflow()->endOfMonth();
        $historicalStart = now()->subMonthsNoOverflow(11)->startOfMonth();
        $historicalEnd = now()->endOfMonth();
        $currentAverage = $this->dashboards->ratingAverageBetweenForRole($teamMemberUids, $managerRole, $thisMonthStart, $thisMonthEnd);
        $lastAverage = $this->dashboards->ratingAverageBetweenForRole($teamMemberUids, $managerRole, $lastMonthStart, $lastMonthEnd);
        $currentRatingsCount = $this->dashboards->ratingsCountBetweenForRole($teamMemberUids, $managerRole, $thisMonthStart, $thisMonthEnd);
        $lastRatingsCount = $this->dashboards->ratingsCountBetweenForRole($teamMemberUids, $managerRole, $lastMonthStart, $lastMonthEnd);
        $memberStats = $this->dashboards->teamMemberRatingStats($teamMemberUids, $managerRole);
        $currentMemberStats = $this->dashboards->teamMemberMonthlyRatingStatsForRole($teamMemberUids, $managerRole, $thisMonthStart, $thisMonthEnd);
        $lastMemberStats = $this->dashboards->teamMemberMonthlyRatingStatsForRole($teamMemberUids, $managerRole, $lastMonthStart, $lastMonthEnd);
        $engagementCounts = $this->dashboards->teamMemberClientEngagementCounts($teamMemberUids, $managerRole);
        $submittedRatings = (int) $engagementCounts->sum('submitted_ratings');
        $totalRequests = (int) $engagementCounts->sum('total_requests');
        $resolutionCounts = $this->dashboards->teamMemberResolutionCounts($teamMemberUids, $managerRole);
        $lowRatings = (int) $resolutionCounts->sum('low_ratings');
        $resolvedRatings = (int) $resolutionCounts->sum('resolved_ratings');
        $averageTeamResolutionRate = $this->rate($resolvedRatings, $lowRatings);
        $ratingByQuestion = $this->ratingByQuestionPayload($teamMemberUids, $managerRole, $locale);

        return [
            'avg_team_resolution_rate' => $averageTeamResolutionRate,
            'team_members' => $this->resolutionRateMembers($teamMembers, $resolutionCounts),
            'summary' => [
                'avg_team_rating' => $this->dashboards->averageTeamRatingForRole($teamMemberUids, $managerRole),
                'trend' => $this->trend($currentAverage, $lastAverage),
                'ratings_count' => [
                    'current_month' => $currentRatingsCount,
                    'last_month' => $lastRatingsCount,
                ],
            ],
            'monthly_average_team_rating' => $this->monthlyAverageTeamRating($teamMemberUids, $managerRole, $historicalStart, $historicalEnd),
            'rating_by_question' => $ratingByQuestion,
            'team_snapshot' => $teamMembers
                ->filter(fn (User $member): bool => (int) ($memberStats->get($member->firebase_uid)?->ratings_count ?? 0) > 0)
                ->sortByDesc(fn (User $member): int => (int) ($memberStats->get($member->firebase_uid)?->ratings_count ?? 0))
                ->take(10)
                ->map(fn (User $member): array => $this->teamSnapshotPayload(
                    $member,
                    $memberStats->get($member->firebase_uid),
                    $currentMemberStats->get($member->firebase_uid),
                    $lastMemberStats->get($member->firebase_uid),
                ))
                ->values()
                ->all(),
            'engagement_metrics' => [
                'overall_rate' => $this->rate($submittedRatings, $totalRequests),
                'submitted_ratings' => $submittedRatings,
                'total_requests' => $totalRequests,
                'members' => $this->rateMembers($teamMembers, $engagementCounts, 'submitted_ratings', 'total_requests'),
            ],
            'resolution_metrics' => [
                'overall_rate' => $averageTeamResolutionRate,
                'resolved_ratings' => $resolvedRatings,
                'low_ratings' => $lowRatings,
                'members' => $this->resolutionRateMembers($teamMembers, $resolutionCounts),
            ],
            'recent_feedback' => $this->dashboards->recentFeedbackForRole($teamMemberUids, $managerRole)
                ->map(fn (Rating $rating): array => [
                    'from' => $this->feedbackUserPayload($rating->rater),
                    'to' => $this->feedbackUserPayload($rating->rep),
                    'rating' => round((float) $rating->average_score, 2),
                    'created_at' => $rating->created_at?->toJSON(),
                ])
                ->values()
                ->all(),
            'export' => [
                'email_enabled' => true,
            ],
        ];
    }

    public function managerHome(User $manager): array
    {
        $managerRole = $this->managerRole($manager);
        $teamMembers = $this->dashboards->teamMembersForManager($manager);
        $teamMemberUids = $teamMembers->pluck('firebase_uid')->all();
        $thisMonthStart = now()->startOfMonth();
        $thisMonthEnd = now()->endOfMonth();
        $ratingsThisMonth = $this->dashboards->ratingsCountBetweenForRole($teamMemberUids, $managerRole, $thisMonthStart, $thisMonthEnd);
        $requestsSent = $this->dashboards->ratingRequestsCountBetweenForRole($teamMemberUids, $managerRole, $thisMonthStart, $thisMonthEnd);
        $engagementCounts = $this->dashboards->teamMemberClientEngagementCounts($teamMemberUids, $managerRole);
        $submittedRatings = (int) $engagementCounts->sum('submitted_ratings');
        $totalRequests = (int) $engagementCounts->sum('total_requests');
        $memberStats = $this->dashboards->teamMemberRatingStats($teamMemberUids, $managerRole);

        return [
            'team' => [
                'id' => $manager->firebase_uid,
                'name' => trim($manager->first_name.' '.$manager->last_name).' Team',
                'team_size' => $teamMembers->count(),
                'avg_rating' => $this->dashboards->averageTeamRatingForRole($teamMemberUids, $managerRole),
            ],
            'quick_stats' => [
                'ratings_this_month' => $ratingsThisMonth,
                'requests_sent' => $requestsSent,
                'engagement' => $this->rate($submittedRatings, $totalRequests),
            ],
            'notifications' => [
                'unread_count' => $this->notifications->unreadCount($manager->firebase_uid),
            ],
            'team_members' => $teamMembers
                ->map(fn (User $member): array => $this->teamMemberPayload($member, $memberStats->get($member->firebase_uid)))
                ->values()
                ->all(),
        ];
    }

    public function teamOverview(User $manager): array
    {
        $locale = $manager->prefered_locale ?? 'en';
        $teamMemberUids = $manager->teamMembers()->pluck('users.firebase_uid')->all();
        $thisMonthStart = now()->startOfMonth();
        $thisMonthEnd = now()->endOfMonth();
        $lastMonthStart = now()->subMonthNoOverflow()->startOfMonth();
        $lastMonthEnd = now()->subMonthNoOverflow()->endOfMonth();
        $thisMonthFeedback = $this->dashboards->feedbackCountBetween($teamMemberUids, $thisMonthStart, $thisMonthEnd);
        $lastMonthFeedback = $this->dashboards->feedbackCountBetween($teamMemberUids, $lastMonthStart, $lastMonthEnd);
        $requestCounts = $this->dashboards->ratingRequestCounts($teamMemberUids, $thisMonthStart, $thisMonthEnd);

        return [
            'average_team_rating' => $this->dashboards->averageTeamRating($teamMemberUids),
            'average_rating_by_question' => $this->dashboards->averageRatingByQuestion($teamMemberUids, $locale),
            'monthly_change_percent' => $this->percentageChange($lastMonthFeedback, $thisMonthFeedback),
            'total_feedback_this_month' => $thisMonthFeedback,
            'total_feedback_last_month' => $lastMonthFeedback,
            'resolution_rate' => $this->rate((int) ($requestCounts->completed_requests ?? 0), (int) ($requestCounts->total_requests ?? 0)),
            'client_engagement_rate' => $this->rate($thisMonthFeedback, $this->dashboards->activeConnectionCount($teamMemberUids)),
            'recent_feedback' => $this->dashboards->recentFeedback($teamMemberUids),
            'weekly_performance_highlights' => $this->weeklyPerformanceHighlights($teamMemberUids),
        ];
    }

    public function raterHome(User $rater): array
    {
        $recentConnections = $this->dashboards->recentConnectionsForRater($rater->firebase_uid);
        $connectedReps = $recentConnections
            ->map(fn ($connection): ?User => $this->repFromConnection($connection, $rater->firebase_uid))
            ->filter()
            ->values();
        $repStats = $this->dashboards->repRatingStats($connectedReps->pluck('firebase_uid')->all());

        return [
            'profile' => array_merge($this->userPayload($rater), [
                'email' => $rater->email,
            ]),
            'recent_connections' => $connectedReps
                ->map(fn (User $rep): array => $this->connectedRepPayload($rep, $repStats->get($rep->firebase_uid)))
                ->values()
                ->all(),
            'recent_ratings' => $this->dashboards->recentRatingsGivenByRater($rater->firebase_uid)
                ->map(fn (Rating $rating): array => $this->recentRatingPayload($rating->rep, (float) $rating->average_score))
                ->values()
                ->all(),
        ];
    }

    public function repHome(User $rep): array
    {
        $stats = $this->dashboards->repRatingStats([$rep->firebase_uid])->get($rep->firebase_uid);

        return [
            'profile' => array_merge($this->userPayload($rep), [
                'email' => $rep->email,
                'avg_rating' => round((float) ($stats->average_rating ?? 0), 2),
                'ratings_count' => (int) ($stats->ratings_count ?? 0),
            ]),
            'recent_ratings' => $this->dashboards->recentRatingsForRep($rep->firebase_uid)
                ->map(fn (Rating $rating): array => $this->recentReceivedRatingPayload($rating->rater, (float) $rating->average_score))
                ->values()
                ->all(),
        ];
    }

    public function queueEmailExport(User $manager, array $filters = []): array
    {
        $this->managerRole($manager);

        $pendingStatusId = Status::idByName('pending')
            ?? throw new \RuntimeException('Required status seed is missing: pending.');
        $completedStatusId = Status::idByName('completed')
            ?? throw new \RuntimeException('Required status seed is missing: completed.');

        $export = $this->dashboards->createExport([
            'requested_by_firebase_uid' => $manager->firebase_uid,
            'scope_type' => 'team',
            'scope_user_firebase_uid' => $manager->firebase_uid,
            'filters_json' => $filters,
            'status_id' => $pendingStatusId,
            'requested_at' => now(),
            'created_by' => $manager->firebase_uid,
            'updated_by' => $manager->firebase_uid,
        ]);

        Mail::to($manager->email)->send(new DashboardReportMail($this->dashboard($manager)));

        $export->update([
            'status_id' => $completedStatusId,
            'completed_at' => now(),
            'updated_by' => $manager->firebase_uid,
        ]);

        return ['export_id' => $export->id, 'status' => 'sent'];
    }

    private function weeklyPerformanceHighlights(array $teamMemberUids): array
    {
        $currentWeek = $this->dashboards->feedbackCountBetween($teamMemberUids, now()->startOfWeek(), now()->endOfWeek());
        $previousWeek = $this->dashboards->feedbackCountBetween(
            $teamMemberUids,
            Carbon::now()->subWeek()->startOfWeek(),
            Carbon::now()->subWeek()->endOfWeek()
        );

        return [
            'feedback_this_week' => $currentWeek,
            'feedback_previous_week' => $previousWeek,
            'change_percent' => $this->percentageChange($previousWeek, $currentWeek),
        ];
    }

    private function percentageChange(int $previous, int $current): float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    private function rate(int $part, int $whole): float
    {
        if ($whole === 0) {
            return 0.0;
        }

        return min(round(($part / $whole) * 100, 2), 100.0);
    }

    private function trend(float $current, float $previous): array
    {
        $value = round($current - $previous, 2);

        return [
            'value' => $value,
            'is_positive' => $value >= 0,
        ];
    }

    private function monthlyAverageTeamRating(array $teamMemberUids, int $managerRole, CarbonInterface $from, CarbonInterface $to): array
    {
        $ratingsByMonth = $this->dashboards->historicalRatingsForRole($teamMemberUids, $managerRole, $from, $to);
        $months = [];
        $cursor = $from->copy();

        while ($cursor->lessThanOrEqualTo($to)) {
            $monthKey = $cursor->format('Y-m');
            $rating = $ratingsByMonth->get($monthKey);
            $months[] = [
                'month' => $cursor->format('M'),
                'rating' => round((float) ($rating->average_rating ?? 0), 2),
            ];
            $cursor->addMonthNoOverflow();
        }

        return $months;
    }

    private function teamSnapshotPayload(User $member, ?object $stats, ?object $currentStats, ?object $lastStats): array
    {
        return [
            'firebase_uid' => $member->firebase_uid,
            'full_name' => trim(($member->first_name ?? '').' '.($member->last_name ?? '')),
            'image_url' => $member->image_url,
            'company_name' => $member->company_name,
            'position' => $member->position,
            'avg_rating' => round((float) ($stats->average_rating ?? 0), 2),
            'ratings_count' => (int) ($stats->ratings_count ?? 0),
            'trend' => $this->percentageTrend(
                round((float) ($currentStats->average_rating ?? 0), 2),
                round((float) ($lastStats->average_rating ?? 0), 2),
            ),
        ];
    }

    private function percentageTrend(float $current, float $previous): array
    {
        $value = $previous === 0.0
            ? ($current > 0.0 ? 100.0 : 0.0)
            : round((($current - $previous) / $previous) * 100, 2);

        return [
            'value' => $value,
            'is_positive' => $value >= 0,
        ];
    }

    private function ratingByQuestionPayload(array $teamMemberUids, int $managerRole = Role::MANAGER_OF_REPRESENTATIVES, string $locale = 'en'): array
    {
        $questions = $this->dashboards->averageRatingByQuestionForRole($teamMemberUids, $managerRole, $locale);
        $responseCount = (int) $questions->sum('response_count');
        $scoreTotal = $questions->sum(fn (object $question): float => (float) $question->average_score * (int) $question->response_count);

        return [
            'average_score' => $responseCount === 0 ? 0.0 : round($scoreTotal / $responseCount, 2),
            'questions' => $questions
                ->map(fn (object $question): array => [
                    'question_id' => (int) $question->question_id,
                    'question' => $question->title,
                    'score' => round((float) $question->average_score, 2),
                ])
                ->values()
                ->all(),
        ];
    }

    private function rateMembers(Collection $teamMembers, Collection $memberRequests, string $numeratorKey, string $denominatorKey): array
    {
        return $teamMembers
            ->map(fn (User $member): array => [
                'firebase_uid' => $member->firebase_uid,
                'name' => trim($member->first_name.' '.$member->last_name),
                $numeratorKey => (int) ($memberRequests->get($member->firebase_uid)?->{$numeratorKey} ?? 0),
                $denominatorKey => (int) ($memberRequests->get($member->firebase_uid)?->{$denominatorKey} ?? 0),
                'rate' => $this->rate(
                    (int) ($memberRequests->get($member->firebase_uid)?->{$numeratorKey} ?? 0),
                    (int) ($memberRequests->get($member->firebase_uid)?->{$denominatorKey} ?? 0)
                ),
            ])
            ->values()
            ->all();
    }

    private function resolutionRateMembers(Collection $teamMembers, Collection $resolutionCounts): array
    {
        return $teamMembers
            ->map(fn (User $member): array => [
                'firebase_uid' => $member->firebase_uid,
                'name' => trim($member->first_name.' '.$member->last_name),
                'resolved_ratings' => (int) ($resolutionCounts->get($member->firebase_uid)?->resolved_ratings ?? 0),
                'low_ratings' => (int) ($resolutionCounts->get($member->firebase_uid)?->low_ratings ?? 0),
                'resolution_rate' => $this->rate(
                    (int) ($resolutionCounts->get($member->firebase_uid)?->resolved_ratings ?? 0),
                    (int) ($resolutionCounts->get($member->firebase_uid)?->low_ratings ?? 0)
                ),
                'rate' => $this->rate(
                    (int) ($resolutionCounts->get($member->firebase_uid)?->resolved_ratings ?? 0),
                    (int) ($resolutionCounts->get($member->firebase_uid)?->low_ratings ?? 0)
                ),
            ])
            ->values()
            ->all();
    }

    private function feedbackUserPayload(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'firebase_uid' => $user->firebase_uid,
            'first_name' => $user->first_name,
            // 'last_name' => $user->last_name,
        ];
    }

    private function managerRole(User $manager): int
    {
        if ($manager->hasRole(Role::MANAGER_OF_REPRESENTATIVES)) {
            return Role::MANAGER_OF_REPRESENTATIVES;
        }

        if ($manager->hasRole(Role::MANAGER_OF_RATERS)) {
            return Role::MANAGER_OF_RATERS;
        }

        throw new AuthorizationException;
    }

    private function teamMemberPayload(User $member, ?object $stats): array
    {
        return [
            'id' => $member->firebase_uid,
            'name' => trim($member->first_name.' '.$member->last_name),
            'image_url' => $member->image_url,
            'rating' => round((float) ($stats->rating ?? 0), 2),
            'ratings_count' => (int) ($stats->ratings_count ?? 0),
            'status' => $member->is_blocked || $member->is_deleted ? 'Inactive' : 'Active',
        ];
    }

    private function repFromConnection(Connection $connection, string $raterUid): ?User
    {
        $user = $connection->user_a_firebase_uid === $raterUid
            ? $connection->userB
            : $connection->userA;

        return $user?->hasRole(Role::REPRESENTATIVE) ? $user : null;
    }

    private function userPayload(User $user): array
    {
        return [
            'firebase_uid' => $user->firebase_uid,
            'image_url' => $user->image_url,
            'full_name' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
            'company_name' => $user->company_name,
            'position' => $user->position,
        ];
    }

    private function connectedRepPayload(User $rep, ?object $stats): array
    {
        return array_merge($this->userPayload($rep), [
            'avg_rating' => round((float) ($stats->average_rating ?? 0), 2),
            'ratings_count' => (int) ($stats->ratings_count ?? 0),
        ]);
    }

    private function recentRatingPayload(User $rep, float $rating): array
    {
        return array_merge($this->userPayload($rep), [
            'avg_rating' => round($rating, 2),
        ]);
    }

    private function recentReceivedRatingPayload(User $rater, float $rating): array
    {
        return array_merge($this->userPayload($rater), [
            'avg_rating' => round($rating, 2),
        ]);
    }
}
