<?php

namespace App\Repositories\Contracts;

use App\Models\DashboardExport;
use App\Models\User;
use Illuminate\Support\Collection;

interface DashboardRepositoryInterface
{
    public function averageTeamRating(array $teamMemberUids): float;

    public function averageRatingByQuestion(array $teamMemberUids, string $locale = 'en'): Collection;

    public function averageRatingByQuestionForRole(array $teamMemberUids, int $managerRole, string $locale = 'en'): Collection;

    public function ratingAverageBetween(array $teamMemberUids, \DateTimeInterface $from, \DateTimeInterface $to): float;

    public function ratingAverageBetweenForRole(array $teamMemberUids, int $managerRole, \DateTimeInterface $from, \DateTimeInterface $to): float;

    public function feedbackCountBetween(array $teamMemberUids, \DateTimeInterface $from, \DateTimeInterface $to): int;

    public function historicalRatings(array $teamMemberUids, \DateTimeInterface $from, \DateTimeInterface $to): Collection;

    public function historicalRatingsForRole(array $teamMemberUids, int $managerRole, \DateTimeInterface $from, \DateTimeInterface $to): Collection;

    public function recentFeedback(array $teamMemberUids, int $limit = 10): Collection;

    public function recentFeedbackForRole(array $teamMemberUids, int $managerRole, int $limit = 10): Collection;

    public function recentConnectionsForRater(string $raterUid, int $limit = 10): Collection;

    public function recentRatingsGivenByRater(string $raterUid, int $limit = 10): Collection;

    public function recentRatingsForRep(string $repUid, int $limit = 10): Collection;

    public function repRatingStats(array $repUids): Collection;

    public function ratingRequestCounts(array $teamMemberUids, \DateTimeInterface $from, \DateTimeInterface $to): object;

    public function activeConnectionCount(array $teamMemberUids): int;

    public function teamMembersForManager(User $manager): Collection;

    public function averageTeamRatingForRole(array $teamMemberUids, int $managerRole): float;

    public function ratingsCountBetweenForRole(array $teamMemberUids, int $managerRole, \DateTimeInterface $from, \DateTimeInterface $to): int;

    public function ratingRequestsCountBetweenForRole(array $teamMemberUids, int $managerRole, \DateTimeInterface $from, \DateTimeInterface $to): int;

    public function teamMemberRatingStats(array $teamMemberUids, int $managerRole): Collection;

    public function teamMemberMonthlyRatingStats(array $teamMemberUids, \DateTimeInterface $from, \DateTimeInterface $to): Collection;

    public function teamMemberMonthlyRatingStatsForRole(array $teamMemberUids, int $managerRole, \DateTimeInterface $from, \DateTimeInterface $to): Collection;

    public function teamMemberConnectionCounts(array $teamMemberUids): Collection;

    public function teamMemberRatingRequestCounts(array $teamMemberUids, \DateTimeInterface $from, \DateTimeInterface $to, array $resolvedStatusIds = []): Collection;

    public function teamMemberClientEngagementCounts(array $teamMemberUids, int $managerRole): Collection;

    public function teamMemberResolutionCounts(array $teamMemberUids, int $managerRole, float $threshold = 3.0): Collection;

    public function createExport(array $attributes): DashboardExport;
}
