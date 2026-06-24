<?php

namespace Tests\Feature\Api\V1;

use App\Mail\ExternalRatingInvitationMail;
use App\Models\ExternalRatingRequest;
use App\Models\ExternalUser;
use App\Models\Rating;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ExternalRatingEndpointsTest extends V1TestCase
{
    public function test_sales_rep_can_send_an_external_rating_invitation(): void
    {
        Mail::fake();

        $salesRep = $this->authAsRole(Role::REPRESENTATIVE);

        $this->postJson('/api/v1/external-rating-requests', [
            'first_name' => 'External',
            'last_name' => 'User',
            'email' => 'external@example.com',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Invitation sent successfully.')
            ->assertJsonPath('data.email', 'external@example.com')
            ->assertJsonPath('data.status', 'pending');

        $invitation = ExternalRatingRequest::query()
            ->where('email', 'external@example.com')
            ->firstOrFail();

        $this->assertSame($salesRep->firebase_uid, $invitation->rep_id);
        $this->assertNotEmpty($invitation->invite_uuid);
        $this->assertNotEmpty($invitation->token);
        $this->assertNotNull($invitation->expires_at);

        Mail::assertSent(ExternalRatingInvitationMail::class, function (ExternalRatingInvitationMail $mail) use ($invitation): bool {
            return $mail->invitation->is($invitation)
                && $mail->url === url("/external-rating/{$invitation->invite_uuid}");
        });
    }

    public function test_public_user_can_open_the_external_rating_form(): void
    {
        $salesRep = $this->createUserWithRole(Role::REPRESENTATIVE);
        $invitation = $this->createInvitation($salesRep);

        $this->getJson("/api/v1/external-rating-requests/{$invitation->invite_uuid}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sales_rep.firebase_uid', $salesRep->firebase_uid)
            ->assertJsonPath('data.sales_rep.first_name', $salesRep->first_name)
            ->assertJsonCount(10, 'data.rating_questions')
            ->assertJsonPath('data.rating_questions.0.question_code', '10');
    }

    public function test_expired_external_rating_requests_are_blocked(): void
    {
        $salesRep = $this->createUserWithRole(Role::REPRESENTATIVE);
        $invitation = $this->createInvitation($salesRep, [
            'expires_at' => now()->subMinute(),
        ]);

        $this->getJson("/api/v1/external-rating-requests/{$invitation->invite_uuid}")
            ->assertGone()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This invitation has expired.');

        $this->assertDatabaseHas('external_rating_requests', [
            'invite_uuid' => $invitation->invite_uuid,
            'status' => 'expired',
        ]);
    }

    public function test_public_user_can_submit_an_external_rating_once(): void
    {
        $salesRep = $this->createUserWithRole(Role::REPRESENTATIVE);
        $invitation = $this->createInvitation($salesRep);

        $this->postJson("/api/v1/external-rating-requests/{$invitation->invite_uuid}/submit", $this->submissionPayload($invitation))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'External rating submitted successfully.')
            ->assertJsonPath('data.submission_status', 'completed');

        $externalUser = ExternalUser::query()
            ->where('email', $invitation->email)
            ->firstOrFail();

        $rating = Rating::query()
            ->where('external_user_id', $externalUser->id)
            ->where('rep_firebase_uid', $salesRep->firebase_uid)
            ->firstOrFail();

        $this->assertTrue((bool) $rating->from_external_link);
        $this->assertNull($rating->rater_firebase_uid);
        $this->assertSame(10, $rating->items()->count());
        $this->assertDatabaseHas('external_rating_requests', [
            'invite_uuid' => $invitation->invite_uuid,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('sales_rep_users', [
            'user_firebase_uid' => $salesRep->firebase_uid,
        ]);
    }

    public function test_an_external_rating_request_cannot_be_submitted_twice(): void
    {
        $salesRep = $this->createUserWithRole(Role::REPRESENTATIVE);
        $invitation = $this->createInvitation($salesRep);
        $payload = $this->submissionPayload($invitation);

        $this->postJson("/api/v1/external-rating-requests/{$invitation->invite_uuid}/submit", $payload)
            ->assertOk();

        $this->postJson("/api/v1/external-rating-requests/{$invitation->invite_uuid}/submit", $payload)
            ->assertConflict()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This invitation has already been completed.');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createInvitation(User $salesRep, array $overrides = []): ExternalRatingRequest
    {
        return ExternalRatingRequest::query()->create(array_merge([
            'invite_uuid' => fake()->uuid(),
            'rep_id' => $salesRep->firebase_uid,
            'email' => 'external@example.com',
            'token' => 'token_'.fake()->uuid(),
            'status' => 'pending',
            'expires_at' => now()->addDay(),
            'created_by' => $salesRep->firebase_uid,
            'updated_by' => $salesRep->firebase_uid,
        ], $overrides));
    }

    /**
     * @return array{
     *     first_name: string,
     *     last_name: string,
     *     email: string,
     *     company_name: string,
     *     position: string,
     *     ratings: array<int, array{question_id: int, score: int}>
     * }
     */
    private function submissionPayload(ExternalRatingRequest $invitation): array
    {
        return [
            'first_name' => 'External',
            'last_name' => 'User',
            'email' => $invitation->email,
            'company_name' => 'Website Co',
            'position' => 'Buyer',
            'ratings' => array_map(
                static fn (int $questionId): array => [
                    'question_id' => $questionId,
                    'score' => 5,
                ],
                $this->externalQuestionIds()
            ),
        ];
    }

    /**
     * @return array<int, int>
     */
    private function externalQuestionIds(): array
    {
        return DB::table('rating_questions')
            ->where('is_active', true)
            ->orderBy('question_code')
            ->limit(10)
            ->pluck('id')
            ->map(static fn (mixed $questionId): int => (int) $questionId)
            ->all();
    }
}
