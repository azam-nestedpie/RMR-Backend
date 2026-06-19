<?php

namespace App\Services\V1;

use App\Exceptions\ApiException;
use App\Models\Connection;
use App\Models\ConnectionRequest;
use App\Models\RatingRequest;
use App\Models\Status;
use App\Models\User;
use App\Repositories\Contracts\RatingRepositoryInterface;
use Illuminate\Support\Facades\Log;

class RaterProfileService
{
    public function __construct(
        private readonly RatingRepositoryInterface $ratings,
    ) {}

    public function show(User $rep, string $raterUid): array
    {
        Log::info('Rater profile requested', ['rep_uid' => $rep->firebase_uid, 'rater_uid' => $raterUid]);

        $rater = User::query()
            ->select(['firebase_uid', 'first_name', 'last_name', 'company_name', 'position', 'bio', 'email', 'image_url'])
            ->where('firebase_uid', $raterUid)
            ->first();

        if (! $rater) {
            throw ApiException::notFound('Rater not found.');
        }

        $connectionStatus = $this->determineConnectionStatus($rep->firebase_uid, $raterUid);

        $ratings = $this->ratings->byRaterAndRep($raterUid, $rep->firebase_uid);

        return [
            'rater' => $rater,
            'connection_status' => $connectionStatus,
            'ratings' => $ratings,
        ];
    }

    private function determineConnectionStatus(string $repUid, string $raterUid): string
    {
        $activeConnectionExists = Connection::between($repUid, $raterUid)->active()->exists();

        if ($activeConnectionExists) {
            $pendingStatusId = Status::idByName('pending');

            $pendingRatingRequest = RatingRequest::query()
                ->where('requester_firebase_uid', $repUid)
                ->where('target_user_firebase_uid', $raterUid)
                ->when($pendingStatusId, fn ($q) => $q->where('status_id', $pendingStatusId))
                ->exists();

            if ($pendingRatingRequest) {
                return 'request_sent';
            }

            return 'rating_request';
        }

        $pendingStatusId = Status::idByName('pending');

        $repSentRequest = ConnectionRequest::query()
            ->where('requester_firebase_uid', $repUid)
            ->where('target_user_firebase_uid', $raterUid)
            ->when($pendingStatusId, fn ($q) => $q->where('status_id', $pendingStatusId))
            ->exists();

        if ($repSentRequest) {
            return 'request_sent';
        }

        $rejectedStatusId = Status::idByName('rejected');

        $repRequestRejected = ConnectionRequest::query()
            ->where('requester_firebase_uid', $repUid)
            ->where('target_user_firebase_uid', $raterUid)
            ->when($rejectedStatusId, fn ($q) => $q->where('status_id', $rejectedStatusId))
            ->exists();

        if ($repRequestRejected) {
            return 'connect';
        }

        return 'connect';
    }
}
