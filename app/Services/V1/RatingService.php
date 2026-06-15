<?php

namespace App\Services\V1;

use App\Exceptions\ApiException;
use App\Models\ExternalUser;
use App\Models\Rating;
use App\Models\RatingRequest;
use App\Models\Status;
use App\Models\User;
use App\Repositories\Contracts\ConnectionRepositoryInterface;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Repositories\Contracts\RatingRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RatingService
{
    public function __construct(
        private readonly RatingRepositoryInterface $ratings,
        private readonly NotificationRepositoryInterface $notifications,
        private readonly UserRepositoryInterface $users,
        private readonly ConnectionRepositoryInterface $connections,
    ) {}

    public function submit(User $rater, array $payload): array
    {
        Log::info('Rating submission started', [
            'rater_uid' => $rater->firebase_uid,
            'rated_user_uid' => $payload['rated_user_uid'] ?? null,
            'items_count' => isset($payload['items']) && is_array($payload['items']) ? count($payload['items']) : 0,
        ]);

        $ratedUserUid = $payload['rated_user_uid'];
        if ($rater->firebase_uid === $ratedUserUid) {
            throw ApiException::badRequest('You cannot rate yourself.');
        }

        $ratedUser = $this->users->findByFirebaseUid($ratedUserUid);
        if (! $ratedUser) {
            throw ApiException::notFound('Target user not found.');
        }

        if (! $rater->hasRole('rater') || ! $ratedUser->hasRole('rep')) {
            throw ApiException::badRequest('Only rater users can submit ratings for rep users.');
        }

        $ratingRequest = null;
        if (! empty($payload['rating_request_id'])) {
            $ratingRequest = $this->ratings->findSubmittableRequestByUuid($payload['rating_request_id'], $rater->firebase_uid);
            if (! $ratingRequest || ! $this->ratingRequestAllowsDirection($ratingRequest, $rater->firebase_uid, $ratedUserUid)) {
                throw ApiException::notFound('Rating request not found.');
            }
        }

        $items = $payload['items'];
        $avgScore = round(collect($items)->avg('score'), 2);
        $ratingUuid = $this->generateUniqueUuid();
        $ratedAt = now();

        DB::transaction(function () use ($rater, $ratedUserUid, $items, $avgScore, $ratingUuid, $ratingRequest, $ratedAt) {
            $rating = $this->ratings->create([
                'firebase_uuid' => $ratingUuid,
                'rater_firebase_uid' => $rater->firebase_uid,
                'rep_firebase_uid' => $ratedUserUid,
                'rating_request_id' => $ratingRequest?->id,
                'average_score' => $avgScore,
                'rated_at' => $ratedAt,
                'created_by' => $rater->firebase_uid,
                'updated_by' => $rater->firebase_uid,
            ]);

            $this->ratings->createItems($rating, $items, $rater->firebase_uid);
            $this->ratings->recalculateRepStats($ratedUserUid);

            if ($ratingRequest) {
                $completedStatusId = Status::idByName('completed');
                if ($completedStatusId) {
                    $this->ratings->completeRequest($ratingRequest, $completedStatusId, $rater->firebase_uid, $ratedAt);
                }
            }

            $uid = $this->generateUniqueUuid();

            $this->notifications->create([
                'firebase_uuid' => $uid,
                'to_user_firebase_uid' => $ratedUserUid,
                'from_user_firebase_uid' => $rater->firebase_uid,
                'message' => 'You received a new rating',
                'screen' => 'ratings',
                'tab_index' => 0,
                'is_read' => false,
                'created_by' => $rater->firebase_uid,
                'updated_by' => $rater->firebase_uid,
            ]);
        });

        Log::info('Rating submission completed', [
            'rater_uid' => $rater->firebase_uid,
            'rated_user_uid' => $ratedUserUid,
            'rating_uuid' => $ratingUuid,
            'average_score' => $avgScore,
        ]);

        return [
            'rating_uuid' => $ratingUuid,
            'average_score' => $avgScore,
            'rated_at' => $ratedAt->toDateTimeString(),
        ];
    }

    public function submitExternal(array $payload): array
    {
        Log::info('External rating submission started', [
            'rep_uid' => $payload['rep_uid'],
            'external_user_email' => $payload['email'] ?? null,
        ]);

        $rep = $this->userOrFail($payload['rep_uid']);

        if (! $rep->hasRole('rep')) {
            throw ApiException::badRequest('External ratings are only allowed for rep users.');
        }

        $items = [
            [
                'question_id' => $payload['question_id'],
                'score' => $payload['score'],
                'comment' => $payload['comment'] ?? null,
            ],
        ];

        $avgScore = $payload['score'];

        $ratingUuid = $this->generateUniqueUuid();

        $externalUser = DB::transaction(function () use ($payload, $items, $avgScore, $ratingUuid): ExternalUser {

            $externalUser = $this->storeExternalUser([
                'first_name' => $payload['first_name'],
                'last_name' => $payload['last_name'] ?? null,
                'email' => $payload['email'] ?? null,
                'company_name' => $payload['company_name'] ?? null,
                'position' => $payload['position'] ?? null,
            ]);

            $rating = $this->ratings->create([
                'firebase_uuid' => $ratingUuid,
                'rater_firebase_uid' => null,
                'external_user_id' => $externalUser->id,
                'rep_firebase_uid' => $payload['rep_uid'],
                'rating_request_id' => null,
                'from_external_link' => true,
                'average_score' => $avgScore,
                'rated_at' => now(),
                'created_by' => null,
                'updated_by' => null,
            ]);

            $this->ratings->createItems($rating, $items, null);

            $this->ratings->recalculateRepStats($payload['rep_uid']);

            $this->notifications->create([
                'firebase_uuid' => $this->generateUniqueUuid(),
                'to_user_firebase_uid' => $payload['rep_uid'],
                'from_user_firebase_uid' => null,
                'message' => 'You received a new external rating',
                'screen' => 'ratings',
                'tab_index' => 0,
                'is_for_external_rating' => true,
                'is_read' => false,
                'sent_at' => now(),
                'created_by' => null,
                'updated_by' => null,
            ]);

            return $externalUser;
        });

        Log::info('External rating submission completed', [
            'rep_uid' => $payload['rep_uid'],
            'rating_uuid' => $ratingUuid,
            'average_score' => $avgScore,
        ]);

        return [
            'rating_uuid' => $ratingUuid,
            'average_score' => $avgScore,
            'external_user_id' => $externalUser->id,
        ];
    }

    public function sendRequest(User $sender, string $targetUid): array
    {
        return $this->createRatingRequest($sender, $sender, $this->userOrFail($targetUid), null);
    }

    public function sendRequestOnBehalf(User $manager, string $behalfUid, string $targetUid): array
    {
        return $this->createRatingRequest(
            $manager,
            $this->userOrFail($behalfUid),
            $this->userOrFail($targetUid),
            $manager
        );
    }

    public function rejectRequest(User $user, string $requestUuid): void
    {
        $rejectedStatusId = Status::idByName('rejected')
            ?? throw new \LogicException('Required status seed is missing: rejected.');

        $request = $this->pendingRequestForResponder($requestUuid, $user);

        DB::transaction(function () use ($request, $user, $rejectedStatusId): void {
            $this->ratings->updateRequestStatus($request, $rejectedStatusId, $user->firebase_uid);
            $this->notify($request->manager_firebase_uid ?: $request->requester_firebase_uid, $user->firebase_uid, 'Rating request rejected');
        });
    }

    public function cancelRequest(User $user, string $requestUuid): void
    {
        $cancelledStatusId = Status::idByName('cancelled')
            ?? throw new \LogicException('Required status seed is missing: cancelled.');

        $request = $this->pendingRequestForRequester($requestUuid, $user);

        DB::transaction(function () use ($request, $user, $cancelledStatusId): void {
            $this->ratings->updateRequestStatus($request, $cancelledStatusId, $user->firebase_uid);
            $this->notify($request->target_user_firebase_uid, $user->firebase_uid, 'Rating request cancelled');
        });
    }

    public function pendingRequests(string $firebaseUid): Collection
    {
        return $this->ratings->pendingRequestsForUser($firebaseUid);
    }

    /**
     * @return array{received?: Collection, sent?: Collection}
     */
    public function requests(User $user): array
    {
        $requests = $this->ratings->requestsForUser($user->firebase_uid);

        if ($user->hasRole('rep')) {
            return ['sent' => $requests['sent']];
        }

        if ($user->hasRole('rater')) {
            return ['received' => $requests['received']];
        }

        return $requests;
    }

    public function teamRatings(User $manager): Collection
    {
        $teamMemberUids = $manager->teamMembers()->pluck('users.firebase_uid')->all();

        return $this->ratings->teamRequests($teamMemberUids);
    }

    public function findRequestForAuthorization(string $requestUuid): RatingRequest
    {
        return $this->ratings->findRequestByUuid($requestUuid)
            ?? throw ApiException::notFound('Rating request not found.');
    }

    public function findUserForAuthorization(string $firebaseUid): User
    {
        return $this->userOrFail($firebaseUid);
    }

    public function received(string $firebaseUid, array $filters = []): LengthAwarePaginator
    {
        Log::info('Received ratings requested', [
            'user_uid' => $firebaseUid,
            'filters' => $filters,
        ]);

        return $this->ratings->receivedFor($firebaseUid, $filters);
    }

    public function forAuthenticatedUser(User $user, array $filters = []): LengthAwarePaginator
    {
        if ($user->hasRole('rater')) {
            return $this->given($user->firebase_uid);
        }

        if ($user->hasRole('rep')) {
            return $this->received($user->firebase_uid, $filters);
        }

        throw new AuthorizationException;
    }

    public function given(string $firebaseUid): LengthAwarePaginator
    {
        Log::info('Given ratings requested', ['user_uid' => $firebaseUid]);

        return $this->ratings->givenBy($firebaseUid);
    }

    public function forUser(string $firebaseUid): LengthAwarePaginator
    {
        Log::info('Ratings for user requested', ['user_uid' => $firebaseUid]);

        return $this->ratings->receivedFor($firebaseUid);
    }

    public function update(User $rater, string $ratingUuid, array $payload): array
    {
        $rating = $this->ratings->findByUuidForRater($ratingUuid, $rater->firebase_uid)
            ?? throw ApiException::notFound('Rating not found.');

        if ($rating->from_external_link) {
            throw ApiException::forbidden('External ratings cannot be edited.');
        }

        if ($rating->rated_at->lt(now()->subHours(24))) {
            throw ApiException::forbidden('Ratings can only be edited within 24 hours.');
        }

        $items = $payload['items'];
        $avgScore = round(collect($items)->avg('score'), 2);

        DB::transaction(function () use ($rating, $items, $avgScore, $rater): void {
            $this->ratings->createEdit($rating, [
                'previous_average_score' => $rating->average_score,
                'new_average_score' => $avgScore,
                'edited_at' => now(),
                'created_by' => $rater->firebase_uid,
                'updated_by' => $rater->firebase_uid,
            ]);

            $rating->update([
                'average_score' => $avgScore,
                'updated_by' => $rater->firebase_uid,
            ]);

            $this->ratings->replaceItems($rating, $items, $rater->firebase_uid);
            $this->ratings->recalculateRepStats($rating->rep_firebase_uid);
        });

        return [
            'rating_uuid' => $rating->firebase_uuid,
            'average_score' => $avgScore,
        ];
    }

    public function averageByQuestion(string $firebaseUid, string $locale = 'en'): Collection
    {
        Log::info('Average rating by question requested', ['user_uid' => $firebaseUid, 'locale' => $locale]);

        return $this->ratings->averageByQuestion($firebaseUid, $locale);
    }

    private function createRatingRequest(User $actor, User $requester, User $target, ?User $manager): array
    {
        $pendingStatusId = Status::idByName('pending')
            ?? throw new \LogicException('Required status seed is missing: pending.');

        $this->assertRatingPair($requester, $target);

        if (! $this->connections->existsActiveBetween($requester->firebase_uid, $target->firebase_uid)) {
            throw ApiException::badRequest('Rating requests are only allowed between active connected users.');
        }

        if ($this->ratings->pendingRequestBetweenParticipants($requester->firebase_uid, $target->firebase_uid)) {
            throw ApiException::conflict('Rating request already pending.');
        }

        if ($this->ratings->recentRequestBetweenParticipants($requester->firebase_uid, $target->firebase_uid, 30)) {
            throw ApiException::conflict('A rating request for this pair already exists within the last 30 days.');
        }
        $requestUuid = $this->generateUniqueUuid();

        $requesterRoleId = $actor->roles()->value('roles.id')
            ?? throw new \LogicException('Role not found.');

        $ratingRequest = DB::transaction(function () use ($actor, $requester, $target, $manager, $pendingStatusId, $requesterRoleId, $requestUuid) {
            $repUid = $requester->hasRole('rep') ? $requester->firebase_uid : $target->firebase_uid;
            $raterUid = $requester->hasRole('rater') ? $requester->firebase_uid : $target->firebase_uid;

            $ratingRequest = $this->ratings->createRequest([
                'firebase_uuid' => $requestUuid,
                'requester_firebase_uid' => $requester->firebase_uid,
                'target_user_firebase_uid' => $target->firebase_uid,
                'manager_firebase_uid' => $manager?->firebase_uid,
                'behalf_firebase_uid' => $manager ? $requester->firebase_uid : null,
                'requested_by_manager_firebase_uid' => $manager?->firebase_uid,
                'rater_firebase_uid' => $raterUid,
                'subject_rep_firebase_uid' => $repUid,
                'requested_by_role_id' => $requesterRoleId,
                'status_id' => $pendingStatusId,
                'requested_at' => now(),
                'created_by' => $actor->firebase_uid,
                'updated_by' => $actor->firebase_uid,
            ]);

            $message = $manager
                ? $manager->first_name.' sent a rating request on behalf of '.$requester->first_name
                : $requester->first_name.' sent you a rating request';

            $this->notify($target->firebase_uid, $actor->firebase_uid, $message);

            return $ratingRequest;
        });

        return [
            'id' => $ratingRequest->id,
            'request_uuid' => $requestUuid,
        ];
    }

    private function ratingRequestAllowsDirection(RatingRequest $ratingRequest, string $raterUid, string $ratedUserUid): bool
    {
        return $ratingRequest->rater_firebase_uid === $raterUid
            && $ratingRequest->subject_rep_firebase_uid === $ratedUserUid;
    }

    private function assertRatingPair(User $requester, User $target): void
    {
        if ($requester->firebase_uid === $target->firebase_uid) {
            throw ApiException::badRequest('You cannot request a rating from yourself.');
        }

        $requester->loadMissing('roles');
        $target->loadMissing('roles');

        if (! $requester->hasRole('rep') || ! $target->hasRole('rater')) {
            throw ApiException::badRequest('Rating requests can only be sent by rep users to rater users.');
        }
    }

    private function pendingRequestForResponder(string $requestUuid, User $user): RatingRequest
    {
        $pendingStatusId = Status::idByName('pending')
            ?? throw new \LogicException('Required status seed is missing: pending.');

        $request = $this->ratings->findPendingRequestByUuid($requestUuid, $user->firebase_uid);

        if ($request) {
            return $request;
        }

        $request = $this->ratings->findRequestByUuid($requestUuid);

        if (
            ! $request
            || $request->status_id !== $pendingStatusId
            || ! $user->hasRole('manager_of_raters')
            || ! $user->manages($request->target_user_firebase_uid)
        ) {
            throw ApiException::notFound('Rating request not found.');
        }

        return $request;
    }

    private function pendingRequestForRequester(string $requestUuid, User $user): RatingRequest
    {
        $pendingStatusId = Status::idByName('pending')
            ?? throw new \LogicException('Required status seed is missing: pending.');

        $request = $this->ratings->findPendingRequestByUuidForRequester($requestUuid, $user->firebase_uid);

        if ($request) {
            return $request;
        }

        $request = $this->ratings->findRequestByUuid($requestUuid);

        if (
            ! $request
            || $request->status_id !== $pendingStatusId
            || ! $user->hasRole('manager_of_reps')
            || ! $user->manages($request->requester_firebase_uid)
        ) {
            throw ApiException::notFound('Rating request not found.');
        }

        return $request;
    }

    private function userOrFail(string $firebaseUid): User
    {
        return $this->users->findByFirebaseUid($firebaseUid)
            ?? throw ApiException::notFound('User not found.');
    }

    private function storeExternalUser(array $payload): ExternalUser
    {
        $attributes = [
            'first_name' => $payload['first_name'],
            'last_name' => $payload['last_name'] ?? null,
            'email' => $payload['email'] ?? null,
            'company_name' => $payload['company_name'] ?? null,
            'position' => $payload['position'] ?? null,
        ];

        if (! empty($payload['email'])) {
            $externalUser = ExternalUser::query()->where('email', $payload['email'])->first();

            if ($externalUser) {
                $externalUser->update($attributes);

                return $externalUser;
            }
        }

        return ExternalUser::create(array_merge($attributes, [
            'external_uuid' => $this->generateUniqueUuid(),
        ]));
    }

    private function notify(string $toUid, string $fromUid, string $message): void
    {
        $uid = $this->generateUniqueUuid();

        $this->notifications->create([
            'firebase_uuid' => $uid,
            'to_user_firebase_uid' => $toUid,
            'from_user_firebase_uid' => $fromUid,
            'message' => $message,
            'screen' => 'ratings',
            'tab_index' => 0,
            'is_read' => false,
            'sent_at' => now(),
            'created_by' => $fromUid,
            'updated_by' => $fromUid,
        ]);
    }

    public function generateUniqueUuid(int $length = 10): string
    {
        do {
            $requestUuid = Str::random($length);
        } while (
            RatingRequest::where('firebase_uuid', $requestUuid)->exists()
            || Rating::where('firebase_uuid', $requestUuid)->exists()
        );

        return $requestUuid;
    }
}
