<?php

namespace App\Services\V1;

use App\Exceptions\ApiException;
use App\Mail\ExternalRatingInvitationMail;
use App\Models\ExternalRatingRequest;
use App\Models\ExternalUser;
use App\Models\RatingQuestion;
use App\Models\User;
use App\Repositories\Contracts\RatingRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ExternalRatingRequestService
{
    private const QUESTIONS_COUNT = 10;

    private const INVITATION_TTL_DAYS = 7;

    public function __construct(
        private readonly RatingRepositoryInterface $ratings,
    ) {}

    public function sendInvitation(User $salesRep, array $payload): ExternalRatingRequest
    {
        $token = 'token_'.Str::random(32);

        Log::info('External rating invitation requested', [
            'sales_rep_uid' => $salesRep->firebase_uid,
            'email' => $payload['email'],
        ]);

        $invitation = ExternalRatingRequest::create([
            'invite_uuid' => (string) Str::uuid(),
            'rep_id' => $salesRep->firebase_uid,
            'email' => $payload['email'],
            'token' => $token,
            'status' => 'pending',
            'expires_at' => now()->addDays(self::INVITATION_TTL_DAYS),
            'created_by' => $salesRep->firebase_uid,
            'updated_by' => $salesRep->firebase_uid,
        ]);

        Mail::to($invitation->email)->send(
            new ExternalRatingInvitationMail($invitation, $this->publicUrl($invitation->invite_uuid))
        );

        Log::info('External rating invitation sent', [
            'sales_rep_uid' => $salesRep->firebase_uid,
            'email' => $invitation->email,
            'invite_uuid' => $invitation->invite_uuid,
        ]);

        return $invitation;
    }

    /**
     * @return array{sales_rep: User, rating_questions: Collection<int, RatingQuestion>}
     */
    public function show(ExternalRatingRequest $invitation): array
    {
        $invitation = $this->markExpiredIfNeeded($invitation);
        $this->ensureDisplayable($invitation);

        return [
            'sales_rep' => $invitation->salesRep()->with('salesRepProfile')->firstOrFail(),
            'rating_questions' => $this->ratingQuestions(),
        ];
    }

    /**
     * @param array{
     *     first_name: string,
     *     last_name: string,
     *     email: string,
     *     company_name: string,
     *     position: string,
     *     ratings: array<int, array{question_id: int, score: int|float|string}>
     * } $payload
     * @return array{submission_status: string}
     */
    public function submit(ExternalRatingRequest $invitation, array $payload): array
    {
        $invitation = $this->markExpiredIfNeeded($invitation);
        $this->ensureDisplayable($invitation);

        return DB::transaction(function () use ($invitation, $payload): array {
            $lockedInvitation = ExternalRatingRequest::query()
                ->whereKey($invitation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureDisplayable($lockedInvitation);

            if ($lockedInvitation->email !== $payload['email']) {
                throw ApiException::conflict('This invitation is tied to a different email address.');
            }

            $rep = User::where('firebase_uid', $lockedInvitation->rep_id)->firstOrFail();

            $externalUser = ExternalUser::query()->firstOrNew(['email' => $payload['email']]);
            if (! $externalUser->exists) {
                $externalUser->external_uuid = (string) Str::uuid();
            }

            $externalUser->fill([
                'first_name' => $payload['first_name'],
                'last_name' => $payload['last_name'],
                'company_name' => $payload['company_name'],
                'position' => $payload['position'],
            ]);
            $externalUser->save();

            $rating = $this->ratings->create([
                'firebase_uuid' => (string) Str::uuid(),
                'rater_firebase_uid' => null,
                'external_user_id' => $externalUser->id,
                'rep_firebase_uid' => $rep->firebase_uid,
                'rating_request_id' => null,
                'from_external_link' => true,
                'average_score' => round((float) collect($payload['ratings'])->avg('score'), 2),
                'rated_at' => now(),
                'created_by' => null,
                'updated_by' => null,
            ]);

            $this->ratings->createItems($rating, $payload['ratings'], null);
            $this->ratings->recalculateRepStats($rep->firebase_uid);

            $lockedInvitation->update([
                'status' => 'completed',
                'used_at' => now(),
                'updated_by' => $rep->firebase_uid,
            ]);

            Log::info('External rating submitted', [
                'invitation_uuid' => $lockedInvitation->invite_uuid,
                'rep_id' => $lockedInvitation->rep_id,
                'external_user_id' => $externalUser->id,
                'rating_uuid' => $rating->firebase_uuid,
            ]);

            return [
                'submission_status' => $lockedInvitation->status,
            ];
        });
    }

    private function ratingQuestions(): Collection
    {
        return RatingQuestion::query()
            ->where('is_active', true)
            ->orderBy('question_code')
            ->limit(self::QUESTIONS_COUNT)
            ->get(['id', 'question_code', 'title_en', 'title_es', 'title_pt', 'is_active']);
    }

    private function ensureDisplayable(ExternalRatingRequest $invitation): void
    {
        if ($invitation->status === 'completed') {
            throw ApiException::conflict('This invitation has already been completed.');
        }

        if ($invitation->status === 'expired' || $invitation->isExpired()) {
            throw ApiException::gone('This invitation has expired.');
        }

        if ($invitation->status !== 'pending') {
            throw ApiException::conflict('This invitation is not available.');
        }
    }

    private function markExpiredIfNeeded(ExternalRatingRequest $invitation): ExternalRatingRequest
    {
        if ($invitation->status === 'pending' && $invitation->isExpired()) {
            $invitation->status = 'expired';
            $invitation->save();
        }

        return $invitation;
    }

    private function publicUrl(string $inviteUuid): string
    {
        return url("/external-rating/{$inviteUuid}");
    }
}
