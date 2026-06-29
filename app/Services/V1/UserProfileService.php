<?php

namespace App\Services\V1;

use App\Exceptions\ApiException;
use App\Models\Connection;
use App\Models\ConnectionRequest;
use App\Models\Rating;
use App\Models\RatingRequest;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use App\Repositories\Contracts\RatingRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserProfileService
{
    private const RATINGS_PER_PAGE = 10;

    public function __construct(
        private readonly RatingRepositoryInterface $ratingRepository,
    ) {}

    public function show(User $currentUser, string $targetUid): array
    {
        $targetUser = User::query()
            ->select(['firebase_uid', 'first_name', 'last_name', 'company_name', 'position', 'bio', 'email', 'image_url'])
            ->where('firebase_uid', $targetUid)
            ->first();

        if (! $targetUser) {
            throw ApiException::notFound('User not found.');
        }

        $targetUser->loadMissing('roles');
        $targetRole = $targetUser->roles->first()?->id;

        $currentUser->loadMissing('roles');
        $currentRole = $currentUser->roles->first()?->id;

        if (! $this->canViewProfile($currentRole, $targetRole)) {
            throw ApiException::forbidden('You do not have permission to view this profile.');
        }

        $connectionStatus = $this->determineConnectionStatus($currentUser->firebase_uid, $targetUid, $targetRole);

        $ratings = $this->getRatings($currentUser, $targetUid, $targetRole);

        $averageRating = $targetRole === Role::REPRESENTATIVE
            ? $this->getAverageRating($targetUid)
            : null;

        return [
            'user' => $targetUser,
            'connection_status' => $connectionStatus,
            'ratings' => $ratings,
            'average_rating' => $averageRating,
        ];
    }

    private function canViewProfile(?int $currentRole, ?int $targetRole): bool
    {
        if (! $targetRole) {
            return true;
        }

        $allowedRoles = [
            Role::RATER => [Role::REPRESENTATIVE],
            Role::REPRESENTATIVE => [Role::RATER],
            Role::MANAGER_OF_REPRESENTATIVES => [Role::RATER, Role::REPRESENTATIVE],
            Role::MANAGER_OF_RATERS => [Role::RATER, Role::REPRESENTATIVE],
        ];

        if (! isset($allowedRoles[$currentRole])) {
            return true;
        }

        return in_array($targetRole, $allowedRoles[$currentRole]);
    }

    private function determineConnectionStatus(string $viewerUid, string $targetUid, ?int $targetRole): string
    {
        $activeConnectionExists = Connection::between($viewerUid, $targetUid)->active()->exists();

        if ($activeConnectionExists) {
            if ($targetRole === Role::RATER) {
                $pendingStatusId = Status::idByName('pending');

                $pendingRatingRequest = RatingRequest::query()
                    ->where('requester_firebase_uid', $viewerUid)
                    ->where('target_user_firebase_uid', $targetUid)
                    ->when($pendingStatusId, fn ($q) => $q->where('status_id', $pendingStatusId))
                    ->exists();

                if ($pendingRatingRequest) {
                    return 'request_sent';
                }

                return 'rating_request';
            }

            return 'connected';
        }

        $pendingStatusId = Status::idByName('pending');

        $viewerSentRequest = ConnectionRequest::query()
            ->where('requester_firebase_uid', $viewerUid)
            ->where('target_user_firebase_uid', $targetUid)
            ->when($pendingStatusId, fn ($q) => $q->where('status_id', $pendingStatusId))
            ->exists();

        if ($viewerSentRequest) {
            return 'request_sent';
        }

        $rejectedStatusId = Status::idByName('rejected');

        $viewerRequestRejected = ConnectionRequest::query()
            ->where('requester_firebase_uid', $viewerUid)
            ->where('target_user_firebase_uid', $targetUid)
            ->when($rejectedStatusId, fn ($q) => $q->where('status_id', $rejectedStatusId))
            ->exists();

        if ($viewerRequestRejected) {
            return 'connect';
        }

        return 'connect';
    }

    private function getRatings(User $currentUser, string $targetUid, ?int $targetRole): ?LengthAwarePaginator
    {
        if ($targetRole === Role::RATER) {
            $repUid = $currentUser->roles->first()?->id === Role::REPRESENTATIVE
                ? $currentUser->firebase_uid
                : null;

            if (! $repUid) {
                return null;
            }

            return $this->ratingRepository->byRaterAndRep($targetUid, $repUid);
        }

        if ($targetRole === Role::REPRESENTATIVE) {
            return Rating::forRep($targetUid)
                ->with([
                    'rater' => function ($query) {
                        $query->select(['firebase_uid', 'first_name', 'last_name', 'image_url', 'company_name', 'position']);
                    },
                ])
                ->select(['firebase_uuid', 'average_score', 'rated_at', 'rater_firebase_uid', 'rep_firebase_uid'])
                ->orderByDesc('rated_at')
                ->paginate(self::RATINGS_PER_PAGE);
        }

        return null;
    }

    private function getAverageRating(string $repUid): ?float
    {
        $average = Rating::forRep($repUid)->avg('average_score');

        return $average !== null ? round((float) $average, 2) : null;
    }
}
