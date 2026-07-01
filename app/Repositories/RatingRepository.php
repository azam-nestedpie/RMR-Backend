<?php

namespace App\Repositories;

use App\Models\Rating;
use App\Models\RatingEdit;
use App\Models\RatingItem;
use App\Models\RatingRequest;
use App\Models\SalesRepUser;
use App\Models\Status;
use App\Repositories\Contracts\RatingRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RatingRepository implements RatingRepositoryInterface
{
    public function create(array $attributes): Rating
    {
        return Rating::create($attributes);
    }

    public function createRequest(array $attributes): RatingRequest
    {
        return RatingRequest::create($attributes);
    }

    public function latestRequestForPair(string $repUid, string $raterUid): ?RatingRequest
    {
        return RatingRequest::query()
            ->where(function ($query) use ($repUid, $raterUid) {
                $query->where('subject_rep_firebase_uid', $repUid)
                    ->where('rater_firebase_uid', $raterUid);
            })
            ->orWhere(function ($query) use ($repUid, $raterUid) {
                $query->where('requester_firebase_uid', $repUid)
                    ->where('target_user_firebase_uid', $raterUid);
            })
            ->latest('requested_at')
            ->first();
    }

    public function pendingRequestBetweenParticipants(string $requesterUid, string $targetUid): ?RatingRequest
    {
        $pendingStatusId = Status::idByName('pending');

        return RatingRequest::query()
            ->where('requester_firebase_uid', $requesterUid)
            ->where('target_user_firebase_uid', $targetUid)
            ->when($pendingStatusId, fn ($query) => $query->where('status_id', $pendingStatusId))
            ->first();
    }

    public function recentRequestBetweenParticipants(string $requesterUid, string $targetUid, int $days): ?RatingRequest
    {
        return RatingRequest::query()
            ->where('requester_firebase_uid', $requesterUid)
            ->where('target_user_firebase_uid', $targetUid)
            ->where('requested_at', '>=', now()->subDays($days))
            ->latest('requested_at')
            ->first();
    }

    public function findSubmittableRequestForParticipant(int $ratingRequestId, string $participantUid): ?RatingRequest
    {
        $pendingStatusId = Status::idByName('pending');
        $acceptedStatusId = Status::idByName('accepted');
        $submittableStatusIds = array_filter([$pendingStatusId, $acceptedStatusId]);

        return RatingRequest::query()
            ->whereKey($ratingRequestId)
            ->where(function ($query) use ($participantUid) {
                $query->where('rater_firebase_uid', $participantUid)
                    ->orWhere('subject_rep_firebase_uid', $participantUid)
                    ->orWhere('requester_firebase_uid', $participantUid)
                    ->orWhere('target_user_firebase_uid', $participantUid);
            })
            ->when($submittableStatusIds !== [], fn ($query) => $query->whereIn('status_id', $submittableStatusIds))
            ->first();
    }

    public function findSubmittableRequestByUuid(string $requestUuid, string $participantUid): ?RatingRequest
    {
        $pendingStatusId = Status::idByName('pending');
        $acceptedStatusId = Status::idByName('accepted');
        $submittableStatusIds = array_filter([$pendingStatusId, $acceptedStatusId]);

        return RatingRequest::query()
            ->where('firebase_uuid', $requestUuid)
            ->where(function ($query) use ($participantUid) {
                $query->where('rater_firebase_uid', $participantUid)
                    ->orWhere('subject_rep_firebase_uid', $participantUid)
                    ->orWhere('requester_firebase_uid', $participantUid)
                    ->orWhere('target_user_firebase_uid', $participantUid);
            })
            ->when($submittableStatusIds !== [], fn ($query) => $query->whereIn('status_id', $submittableStatusIds))
            ->first();
    }

    public function findPendingRequestByUuid(string $requestUuid, string $targetUid): ?RatingRequest
    {
        $pendingStatusId = Status::idByName('pending');

        return RatingRequest::query()
            ->where('firebase_uuid', $requestUuid)
            ->where('target_user_firebase_uid', $targetUid)
            ->when($pendingStatusId, fn ($query) => $query->where('status_id', $pendingStatusId))
            ->with(['target.roles', 'manager.roles', 'behalfUser.roles', 'rater.roles', 'subjectRep.roles', 'status'])
            ->first();
    }

    public function findPendingRequestByUuidForRequester(string $requestUuid, string $requesterUid): ?RatingRequest
    {
        $pendingStatusId = Status::idByName('pending');

        return RatingRequest::query()
            ->where('firebase_uuid', $requestUuid)
            ->where('requester_firebase_uid', $requesterUid)
            ->when($pendingStatusId, fn ($query) => $query->where('status_id', $pendingStatusId))
            ->with(['target.roles', 'manager.roles', 'behalfUser.roles', 'rater.roles', 'subjectRep.roles', 'status'])
            ->first();
    }

    public function findRequestByUuid(string $requestUuid): ?RatingRequest
    {
        return RatingRequest::query()
            ->where('firebase_uuid', $requestUuid)
            ->with(['requester.roles', 'target.roles', 'manager.roles', 'behalfUser.roles', 'rater.roles', 'subjectRep.roles', 'status'])
            ->first();
    }

    public function updateRequestStatus(RatingRequest $request, int $statusId, string $updatedByUid): RatingRequest
    {
        $request->update([
            'status_id' => $statusId,
            'responded_at' => now(),
            'updated_by' => $updatedByUid,
        ]);

        return $request;
    }

    public function completeRequest(RatingRequest $request, int $completedStatusId, string $updatedByUid, \DateTimeInterface $completedAt): RatingRequest
    {
        $request->update([
            'status_id' => $completedStatusId,
            'responded_at' => $request->responded_at ?? $completedAt,
            'completed_at' => $completedAt,
            'updated_by' => $updatedByUid,
        ]);

        return $request;
    }

    public function pendingRequestsForUser(string $firebaseUid): Collection
    {
        $pendingStatusId = Status::idByName('pending');

        return RatingRequest::query()
            ->where('target_user_firebase_uid', $firebaseUid)
            ->when($pendingStatusId, fn ($query) => $query->where('status_id', $pendingStatusId))
            ->with(['requester.roles', 'target.roles', 'manager.roles', 'behalfUser.roles', 'rater.roles', 'subjectRep.roles', 'status'])
            ->orderByDesc('requested_at')
            ->get();
    }

    /**
     * @return array{received: Collection, sent: Collection}
     */
    public function requestsForUser(string $firebaseUid): array
    {
        $relations = ['requester.roles', 'target.roles', 'manager.roles', 'behalfUser.roles', 'rater.roles', 'subjectRep.roles', 'status'];
        $pendingStatusId = Status::idByName('pending');

        return [
            'received' => RatingRequest::query()
                ->where('target_user_firebase_uid', $firebaseUid)
                ->when($pendingStatusId, fn ($query) => $query->where('status_id', $pendingStatusId))
                ->with($relations)
                ->orderByDesc('requested_at')
                ->get(),
            'sent' => RatingRequest::query()
                ->where('requester_firebase_uid', $firebaseUid)
                ->when($pendingStatusId, fn ($query) => $query->where('status_id', $pendingStatusId))
                ->with($relations)
                ->orderByDesc('requested_at')
                ->get(),
        ];
    }

    public function teamRequests(array $teamMemberUids): Collection
    {
        return RatingRequest::query()
            ->where(function ($query) use ($teamMemberUids) {
                $query->whereIn('requester_firebase_uid', $teamMemberUids)
                    ->orWhereIn('target_user_firebase_uid', $teamMemberUids)
                    ->orWhereIn('behalf_firebase_uid', $teamMemberUids);
            })
            ->with(['requester.roles', 'target.roles', 'manager.roles', 'behalfUser.roles', 'rater.roles', 'subjectRep.roles', 'status'])
            ->orderByDesc('requested_at')
            ->get();
    }

    public function findByUuidForRater(string $ratingUuid, string $raterUid): ?Rating
    {
        return Rating::query()
            ->where('firebase_uuid', $ratingUuid)
            ->where('rater_firebase_uid', $raterUid)
            ->with(['items.question', 'rater.roles', 'externalUser', 'rep.roles'])
            ->first();
    }

    public function existsForPair(string $raterUid, string $repUid): bool
    {
        return Rating::query()
            ->where('rater_firebase_uid', $raterUid)
            ->where('rep_firebase_uid', $repUid)
            ->exists();
    }

    public function createItems(Rating $rating, array $items, ?string $createdByUid): void
    {
        foreach ($items as $item) {
            RatingItem::create([
                'rating_id' => $rating->id,
                'question_id' => $item['question_id'],
                'score' => $item['score'],
                'created_by' => $createdByUid,
                'updated_by' => $createdByUid,
            ]);
        }
    }

    public function createEdit(Rating $rating, array $attributes): void
    {
        RatingEdit::create(array_merge($attributes, [
            'rating_id' => $rating->id,
            'rater_firebase_uid' => $rating->rater_firebase_uid,
            'rep_firebase_uid' => $rating->rep_firebase_uid,
        ]));
    }

    public function replaceItems(Rating $rating, array $items, ?string $updatedByUid): void
    {
        $rating->items()->delete();
        $this->createItems($rating, $items, $updatedByUid);
    }

    public function byRaterAndRep(string $raterUid, string $repUid, int $perPage = 10): LengthAwarePaginator
    {
        return Rating::query()
            ->where('rater_firebase_uid', $raterUid)
            ->where('rep_firebase_uid', $repUid)
            ->with([
                'rater' => function ($query) {
                    $query->select(['firebase_uid', 'first_name', 'last_name', 'image_url', 'company_name', 'position']);
                },
            ])
            ->select(['firebase_uuid', 'average_score', 'rated_at', 'rater_firebase_uid', 'rep_firebase_uid'])
            ->orderByDesc('rated_at')
            ->paginate($perPage);
    }

    public function receivedFor(string $firebaseUid, array $filters = []): LengthAwarePaginator
    {
        $query = Rating::forRep($firebaseUid)->with(['items.question', 'rater.roles', 'externalUser', 'rep.roles'])->has('rater')->orderByDesc('rated_at');

        if (! empty($filters['date_from'])) {
            $query->where('rated_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('rated_at', '<=', $filters['date_to']);
        }

        return $query->paginate(20);
    }

    public function givenBy(string $firebaseUid): LengthAwarePaginator
    {
        return Rating::givenBy($firebaseUid)
            ->with(['items.question', 'rater.roles', 'externalUser', 'rep.roles'])
            ->has('rep')
            ->orderByDesc('rated_at')
            ->paginate(20);
    }

    public function averageByQuestion(string $firebaseUid, string $locale = 'en'): Collection
    {
        $titleColumn = in_array($locale, ['en', 'es', 'pt']) ? "title_{$locale}" : 'title_en';

        return DB::table('rating_items')
            ->join('ratings', 'rating_items.rating_id', '=', 'ratings.id')
            ->join('rating_questions', 'rating_items.question_id', '=', 'rating_questions.id')
            ->where('ratings.rep_firebase_uid', $firebaseUid)
            ->groupBy('rating_items.question_id', "rating_questions.{$titleColumn}", 'rating_questions.question_code')
            ->selectRaw("rating_items.question_id, rating_questions.question_code, rating_questions.{$titleColumn} as title, AVG(rating_items.score) as avg_score, COUNT(*) as response_count")
            ->get();
    }

    public function ratingItems(string $ratingUuid, string $locale = 'en'): Collection
    {
        $titleColumn = in_array($locale, ['en', 'es', 'pt']) ? "title_{$locale}" : 'title_en';

        $rating = Rating::where('firebase_uuid', $ratingUuid)->first();

        if (! $rating) {
            return collect();
        }

        return RatingItem::where('rating_id', $rating->id)
            ->whereHas('question')
            ->with('question')
            ->orderBy('question_id')
            ->get()
            ->map(fn (RatingItem $item) => [
                'id' => $item->question->id,
                'title' => $item->question->{$titleColumn},
                'rating' => (int) round($item->score),
            ]);
    }

    public function recalculateRepStats(string $repUid): void
    {
        $result = Rating::where('rep_firebase_uid', $repUid)
            ->selectRaw('AVG(average_score) as avg, COUNT(*) as total')
            ->first();

        SalesRepUser::updateOrCreate(
            ['user_firebase_uid' => $repUid],
            [
                'avg_rating' => $result ? round((float) $result->avg, 2) : 0.00,
                'ratings_count' => $result ? (int) $result->total : 0,
                'updated_by' => $repUid,
            ]
        );
    }
}
