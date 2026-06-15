<?php

namespace App\Repositories\Contracts;

use App\Models\Rating;
use App\Models\RatingRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface RatingRepositoryInterface
{
    public function create(array $attributes): Rating;

    public function createRequest(array $attributes): RatingRequest;

    public function latestRequestForPair(string $repUid, string $raterUid): ?RatingRequest;

    public function pendingRequestBetweenParticipants(string $requesterUid, string $targetUid): ?RatingRequest;

    public function recentRequestBetweenParticipants(string $requesterUid, string $targetUid, int $days): ?RatingRequest;

    public function findSubmittableRequestForParticipant(int $ratingRequestId, string $participantUid): ?RatingRequest;

    public function findSubmittableRequestByUuid(string $requestUuid, string $participantUid): ?RatingRequest;

    public function findPendingRequestByUuid(string $requestUuid, string $targetUid): ?RatingRequest;

    public function findPendingRequestByUuidForRequester(string $requestUuid, string $requesterUid): ?RatingRequest;

    public function findRequestByUuid(string $requestUuid): ?RatingRequest;

    public function updateRequestStatus(RatingRequest $request, int $statusId, string $updatedByUid): RatingRequest;

    public function completeRequest(RatingRequest $request, int $completedStatusId, string $updatedByUid, \DateTimeInterface $completedAt): RatingRequest;

    public function pendingRequestsForUser(string $firebaseUid): Collection;

    /**
     * @return array{received: Collection, sent: Collection}
     */
    public function requestsForUser(string $firebaseUid): array;

    public function teamRequests(array $teamMemberUids): Collection;

    public function findByUuidForRater(string $ratingUuid, string $raterUid): ?Rating;

    public function existsForPair(string $raterUid, string $repUid): bool;

    public function createItems(Rating $rating, array $items, ?string $createdByUid): void;

    public function createEdit(Rating $rating, array $attributes): void;

    public function replaceItems(Rating $rating, array $items, ?string $updatedByUid): void;

    public function byRaterAndRep(string $raterUid, string $repUid, int $perPage = 10): LengthAwarePaginator;

    public function receivedFor(string $firebaseUid, array $filters = []): LengthAwarePaginator;

    public function givenBy(string $firebaseUid): LengthAwarePaginator;

    public function averageByQuestion(string $firebaseUid, string $locale = 'en'): Collection;

    public function recalculateRepStats(string $repUid): void;
}
