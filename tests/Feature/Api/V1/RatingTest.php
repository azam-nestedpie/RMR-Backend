<?php

namespace Tests\Feature\Api\V1;

use App\Models\Rating;
use App\Models\SalesRepUser;
use Illuminate\Support\Facades\DB;

class RatingTest extends V1TestCase
{
    public function test_user_can_submit_rating(): void
    {
        $sender = $this->authAsRole('rater');
        $this->assignIndustry($sender, 'Marketing');
        $recipient = $this->createUserWithRole('rep', ['password' => 'secret']);
        $questionId = (int) \DB::table('rating_questions')->where('question_code', 30)->value('id');

        $this->postJson('/api/v1/ratings', [
            'rep_uid' => $recipient->firebase_uid,
            'items' => [
                ['question_id' => $questionId, 'score' => 5],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.average_score', 5);

        $this->assertDatabaseHas('ratings', [
            'rater_firebase_uid' => $sender->firebase_uid,
            'rep_firebase_uid' => $recipient->firebase_uid,
        ]);
    }

    public function test_rater_can_submit_multiple_ratings_for_same_rep(): void
    {
        $rater = $this->authAsRole('rater');
        $this->assignIndustry($rater, 'Marketing');
        $rep = $this->createUserWithRole('rep', ['password' => 'secret']);
        $questionId = (int) \DB::table('rating_questions')->where('question_code', 30)->value('id');
        $payload = [
            'rep_uid' => $rep->firebase_uid,
            'items' => [
                ['question_id' => $questionId, 'score' => 5],
            ],
        ];

        $this->postJson('/api/v1/ratings', $payload)->assertCreated();
        $this->postJson('/api/v1/ratings', $payload)->assertCreated();

        $this->assertSame(2, Rating::query()
            ->where('rater_firebase_uid', $rater->firebase_uid)
            ->where('rep_firebase_uid', $rep->firebase_uid)
            ->count());
    }

    public function test_rep_cannot_submit_rating_for_rater_with_rater_uid(): void
    {
        $sender = $this->authAsRole('rep');
        $recipient = $this->createUserWithRole('rater', ['password' => 'secret']);
        $questionId = (int) \DB::table('rating_questions')->where('question_code', 30)->value('id');

        $this->postJson('/api/v1/ratings', [
            'rater_uid' => $recipient->firebase_uid,
            'items' => [
                ['question_id' => $questionId, 'score' => 5],
            ],
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'You do not have permission to perform this action.');

        $this->assertDatabaseMissing('ratings', [
            'rater_firebase_uid' => $sender->firebase_uid,
            'rep_firebase_uid' => $recipient->firebase_uid,
        ]);
    }

    public function test_user_cannot_rate_themselves(): void
    {
        $user = $this->authAsRole('rater');
        $this->assignIndustry($user, 'Marketing');
        $questionId = (int) \DB::table('rating_questions')->where('question_code', 30)->value('id');

        $this->postJson('/api/v1/ratings', [
            'rep_uid' => $user->firebase_uid,
            'items' => [
                ['question_id' => $questionId, 'score' => 5],
            ],
        ])
            ->assertStatus(400)
            ->assertJsonPath('message', 'You cannot rate yourself.');
    }

    public function test_rating_recalculates_rep_profile_average(): void
    {
        $sender = $this->authAsRole('rater');
        $this->assignIndustry($sender, 'Marketing');
        $recipient = $this->createUserWithRole('rep', ['password' => 'secret']);
        $questionThirtyId = (int) \DB::table('rating_questions')->where('question_code', 30)->value('id');
        $questionFortyId = (int) \DB::table('rating_questions')->where('question_code', 40)->value('id');

        $this->postJson('/api/v1/ratings', [
            'rep_uid' => $recipient->firebase_uid,
            'items' => [
                ['question_id' => $questionThirtyId, 'score' => 4],
                ['question_id' => $questionFortyId, 'score' => 4],
            ],
        ])->assertCreated();

        $profile = SalesRepUser::findOrFail($recipient->firebase_uid);

        $this->assertEquals(4.0, $profile->avg_rating);
        $this->assertSame(1, $profile->ratings_count);
    }

    public function test_rep_can_view_received_ratings(): void
    {
        $rep = $this->authAsRole('rep');
        $rater = $this->createUserWithRole('rater');

        Rating::create([
            'firebase_uuid' => (string) \Str::uuid(),
            'rater_firebase_uid' => $rater->firebase_uid,
            'rep_firebase_uid' => $rep->firebase_uid,
            'average_score' => 4.0,
            'rated_at' => now(),
            'created_by' => $rater->firebase_uid,
            'updated_by' => $rater->firebase_uid,
        ]);

        $this->getJson('/api/v1/ratings')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.firebase_uid', $rater->firebase_uid)
            ->assertJsonPath('data.0.first_name', $rater->first_name);
    }

    public function test_rater_can_view_given_ratings_from_combined_index(): void
    {
        $rater = $this->authAsRole('rater');
        $rep = $this->createUserWithRole('rep');

        Rating::create([
            'firebase_uuid' => (string) \Str::uuid(),
            'rater_firebase_uid' => $rater->firebase_uid,
            'rep_firebase_uid' => $rep->firebase_uid,
            'average_score' => 5.0,
            'rated_at' => now(),
            'created_by' => $rater->firebase_uid,
            'updated_by' => $rater->firebase_uid,
        ]);

        $this->getJson('/api/v1/ratings')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.firebase_uid', $rep->firebase_uid)
            ->assertJsonPath('data.0.first_name', $rep->first_name);
    }

    public function test_rating_requires_valid_question_ids(): void
    {
        $recipient = $this->createUserWithRole('rep', ['password' => 'secret']);
        $this->authAsRole('rater');

        $this->postJson('/api/v1/ratings', [
            'rep_uid' => $recipient->firebase_uid,
            'items' => [
                ['question_id' => 9999, 'score' => 5],
            ],
        ])->assertUnprocessable();
    }

    public function test_rating_rejects_questions_that_do_not_belong_to_selected_industry(): void
    {
        $recipient = $this->createUserWithRole('rep', ['password' => 'secret']);
        $rater = $this->authAsRole('rater');
        $this->assignIndustry($rater, 'Marketing');
        $contractorOnlyQuestionId = (int) DB::table('rating_questions')->where('question_code', 110)->value('id');

        $this->postJson('/api/v1/ratings', [
            'rep_uid' => $recipient->firebase_uid,
            'items' => [
                ['question_id' => $contractorOnlyQuestionId, 'score' => 5],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');
    }

    public function test_rater_can_edit_rating_within_twenty_four_hours(): void
    {
        $rater = $this->authAsRole('rater');
        $this->assignIndustry($rater, 'Marketing');
        $rep = $this->createUserWithRole('rep');
        $questionThirtyId = (int) DB::table('rating_questions')->where('question_code', 30)->value('id');
        $questionFortyId = (int) DB::table('rating_questions')->where('question_code', 40)->value('id');

        $rating = Rating::create([
            'firebase_uuid' => (string) \Str::uuid(),
            'rater_firebase_uid' => $rater->firebase_uid,
            'rep_firebase_uid' => $rep->firebase_uid,
            'average_score' => 5.0,
            'rated_at' => now()->subHours(23),
            'created_by' => $rater->firebase_uid,
            'updated_by' => $rater->firebase_uid,
        ]);

        DB::table('rating_items')->insert([
            'rating_id' => $rating->id,
            'question_id' => $questionThirtyId,
            'score' => 5,
            'created_by' => $rater->firebase_uid,
            'updated_by' => $rater->firebase_uid,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->putJson('/api/v1/ratings/'.$rating->firebase_uuid, [
            'industry_id' => (int) DB::table('industries')->where('name', 'Marketing')->value('id'),
            'items' => [
                ['question_id' => $questionThirtyId, 'score' => 2],
                ['question_id' => $questionFortyId, 'score' => 4],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Rating updated successfully.')
            ->assertJsonPath('data.average_score', 3);

        $this->assertDatabaseHas('ratings', [
            'id' => $rating->id,
            'average_score' => 3.0,
            'updated_by' => $rater->firebase_uid,
        ]);

        $this->assertDatabaseHas('rating_edits', [
            'rating_id' => $rating->id,
            'rater_firebase_uid' => $rater->firebase_uid,
            'rep_firebase_uid' => $rep->firebase_uid,
            'previous_average_score' => 5.0,
            'new_average_score' => 3.0,
            'created_by' => $rater->firebase_uid,
            'updated_by' => $rater->firebase_uid,
        ]);

        $this->assertDatabaseHas('rating_items', [
            'rating_id' => $rating->id,
            'question_id' => $questionThirtyId,
            'score' => 2,
        ]);

        $this->assertDatabaseHas('rating_items', [
            'rating_id' => $rating->id,
            'question_id' => $questionFortyId,
            'score' => 4,
        ]);
    }

    public function test_rater_cannot_edit_rating_after_twenty_four_hours(): void
    {
        $rater = $this->authAsRole('rater');
        $this->assignIndustry($rater, 'Marketing');
        $rep = $this->createUserWithRole('rep');
        $questionId = (int) DB::table('rating_questions')->where('question_code', 30)->value('id');

        $rating = Rating::create([
            'firebase_uuid' => (string) \Str::uuid(),
            'rater_firebase_uid' => $rater->firebase_uid,
            'rep_firebase_uid' => $rep->firebase_uid,
            'average_score' => 5.0,
            'rated_at' => now()->subHours(25),
            'created_by' => $rater->firebase_uid,
            'updated_by' => $rater->firebase_uid,
        ]);

        $this->putJson('/api/v1/ratings/'.$rating->firebase_uuid, [
            'industry_id' => (int) DB::table('industries')->where('name', 'Marketing')->value('id'),
            'items' => [
                ['question_id' => $questionId, 'score' => 1],
            ],
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Ratings can only be edited within 24 hours.');
    }
}
