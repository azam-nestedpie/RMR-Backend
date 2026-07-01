<?php

namespace Tests\Feature\Api\V1;

use App\Models\Rating;
use App\Models\RatingItem;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserRatingsEndpointTest extends V1TestCase
{
    public function test_returns_rating_items_by_rating_uuid(): void
    {
        $rater = $this->authAsRole(Role::RATER);
        $rep = $this->createUserWithRole(Role::REPRESENTATIVE);

        $ratingUuid = (string) Str::uuid();

        Rating::create([
            'firebase_uuid' => $ratingUuid,
            'rater_firebase_uid' => $rater->firebase_uid,
            'rep_firebase_uid' => $rep->firebase_uid,
            'average_score' => 4.5,
            'rated_at' => now(),
            'created_by' => $rater->firebase_uid,
            'updated_by' => $rater->firebase_uid,
        ]);

        $rating = Rating::where('firebase_uuid', $ratingUuid)->first();

        $questionIds = DB::table('rating_questions')
            ->whereIn('question_code', [10, 20])
            ->pluck('id');

        $questionIds->each(function (int $questionId) use ($rating, $rater) {
            RatingItem::create([
                'rating_id' => $rating->id,
                'question_id' => $questionId,
                'score' => 4.0,
                'created_by' => $rater->firebase_uid,
                'updated_by' => $rater->firebase_uid,
            ]);
        });

        $response = $this->getJson("/api/v1/ratings/{$ratingUuid}/items");

        $response->assertSuccessful()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Rating details fetched successfully.')
            ->assertJsonPath('data.is_editable', 1)
            ->assertJsonCount(2, 'data.rating_details')
            ->assertJsonStructure([
                'data' => [
                    'is_editable',
                    'rating_details' => [
                        '*' => ['id', 'title', 'rating'],
                    ],
                ],
            ]);
    }

    public function test_returns_404_for_nonexistent_rating_uuid(): void
    {
        $this->authAsRole(Role::RATER);

        $response = $this->getJson('/api/v1/ratings/nonexistent-uuid/items');

        $response->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Rating not found.');
    }

    public function test_returns_empty_data_when_rating_has_no_items(): void
    {
        $rater = $this->authAsRole(Role::RATER);
        $rep = $this->createUserWithRole(Role::REPRESENTATIVE);

        $ratingUuid = (string) Str::uuid();

        Rating::create([
            'firebase_uuid' => $ratingUuid,
            'rater_firebase_uid' => $rater->firebase_uid,
            'rep_firebase_uid' => $rep->firebase_uid,
            'average_score' => 4.0,
            'rated_at' => now(),
            'created_by' => $rater->firebase_uid,
            'updated_by' => $rater->firebase_uid,
        ]);

        $response = $this->getJson("/api/v1/ratings/{$ratingUuid}/items");

        $response->assertSuccessful()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_editable', 1)
            ->assertJsonCount(0, 'data.rating_details');
    }

    public function test_is_editable_0_when_rated_over_24_hours_ago(): void
    {
        $rater = $this->authAsRole(Role::RATER);
        $rep = $this->createUserWithRole(Role::REPRESENTATIVE);

        $ratingUuid = (string) Str::uuid();

        Rating::create([
            'firebase_uuid' => $ratingUuid,
            'rater_firebase_uid' => $rater->firebase_uid,
            'rep_firebase_uid' => $rep->firebase_uid,
            'average_score' => 3.0,
            'rated_at' => now()->subHours(25),
            'created_by' => $rater->firebase_uid,
            'updated_by' => $rater->firebase_uid,
        ]);

        $response = $this->getJson("/api/v1/ratings/{$ratingUuid}/items");

        $response->assertSuccessful()
            ->assertJsonPath('data.is_editable', 0);
    }

    public function test_is_editable_1_when_rated_just_under_24_hours_ago(): void
    {
        $rater = $this->authAsRole(Role::RATER);
        $rep = $this->createUserWithRole(Role::REPRESENTATIVE);

        $ratingUuid = (string) Str::uuid();

        Rating::create([
            'firebase_uuid' => $ratingUuid,
            'rater_firebase_uid' => $rater->firebase_uid,
            'rep_firebase_uid' => $rep->firebase_uid,
            'average_score' => 4.0,
            'rated_at' => now()->subHours(23)->subMinutes(59),
            'created_by' => $rater->firebase_uid,
            'updated_by' => $rater->firebase_uid,
        ]);

        $response = $this->getJson("/api/v1/ratings/{$ratingUuid}/items");

        $response->assertSuccessful()
            ->assertJsonPath('data.is_editable', 1);
    }
}
