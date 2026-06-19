<?php

namespace App\Services\V1;

use App\Exceptions\ApiException;
use App\Models\Connection;
use App\Models\ConnectionRequest;
use App\Models\Rating;
use App\Models\Status;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class RepProfileService
{
    private const RATINGS_PER_PAGE = 10;

    public function show(User $rater, string $repUid): array
    {
        Log::info('Rep profile requested', ['rater_uid' => $rater->firebase_uid, 'rep_uid' => $repUid]);

        $rep = User::query()
            ->select(['firebase_uid', 'first_name', 'last_name', 'company_name', 'position', 'bio', 'email', 'image_url'])
            ->where('firebase_uid', $repUid)
            ->first();

        if (! $rep) {
            throw ApiException::notFound('Rep not found.');
        }

        $connectionStatus = $this->determineConnectionStatus($rater->firebase_uid, $repUid);

        $ratings = $this->getRatingsForRep($repUid);

        $averageRating = $this->getAverageRating($repUid);

        return [
            'rep' => $rep,
            'average_rating' => $averageRating,
            'connection_status' => $connectionStatus,
            'ratings' => $ratings,
        ];
    }

    private function determineConnectionStatus(string $viewerUid, string $repUid): string
    {
        $activeConnectionExists = Connection::between($viewerUid, $repUid)->active()->exists();

        if ($activeConnectionExists) {
            return 'connected';
        }

        $pendingStatusId = Status::idByName('pending');

        $viewerSentRequest = ConnectionRequest::query()
            ->where('requester_firebase_uid', $viewerUid)
            ->where('target_user_firebase_uid', $repUid)
            ->when($pendingStatusId, fn ($q) => $q->where('status_id', $pendingStatusId))
            ->exists();

        if ($viewerSentRequest) {
            return 'request_sent';
        }

        $rejectedStatusId = Status::idByName('rejected');

        $viewerRequestRejected = ConnectionRequest::query()
            ->where('requester_firebase_uid', $viewerUid)
            ->where('target_user_firebase_uid', $repUid)
            ->when($rejectedStatusId, fn ($q) => $q->where('status_id', $rejectedStatusId))
            ->exists();

        if ($viewerRequestRejected) {
            return 'connect';
        }

        return 'connect';
    }

    private function getRatingsForRep(string $repUid): LengthAwarePaginator
    {
        return Rating::forRep($repUid)
            ->with([
                'rater' => function ($query) {
                    $query->select(['firebase_uid', 'first_name', 'last_name', 'image_url', 'company_name', 'position']);
                },
            ])
            ->select(['firebase_uuid', 'average_score', 'rated_at', 'rater_firebase_uid', 'rep_firebase_uid'])
            ->orderByDesc('rated_at')
            ->paginate(self::RATINGS_PER_PAGE);
    }

    private function getAverageRating(string $repUid): ?float
    {
        $average = Rating::forRep($repUid)->avg('average_score');

        return $average !== null ? round((float) $average, 2) : null;
    }
}
