<?php

namespace Tests\Feature\Api\V1;

use App\Models\Rating;
use App\Models\Role;

class RaterProfileTest extends V1TestCase
{
    public function test_rep_can_view_rater_profile(): void
    {
        $rep = $this->authAsRole(Role::REPRESENTATIVE);
        $rater = $this->createUserWithRole(Role::RATER, [
            'first_name' => 'David',
            'last_name' => 'Smith',
            'company_name' => 'XYZ Pharma',
            'position' => 'Senior Rater',
            'bio' => 'Experienced rater',
            'email' => 'david@example.com',
        ]);

        $this->getJson('/api/v1/raters/'.$rater->firebase_uid)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.firebase_uuid', $rater->firebase_uid)
            ->assertJsonPath('data.full_name', 'David Smith')
            ->assertJsonPath('data.company_name', 'XYZ Pharma')
            ->assertJsonPath('data.position', 'Senior Rater')
            ->assertJsonPath('data.bio', 'Experienced rater')
            ->assertJsonPath('data.email', 'david@example.com')
            ->assertJsonStructure([
                'data' => [
                    'firebase_uuid',
                    'full_name',
                    'company_name',
                    'position',
                    'bio',
                    'email',
                    'connection_status',
                    'ratings' => [
                        'data',
                        'current_page',
                        'last_page',
                        'per_page',
                        'total',
                    ],
                ],
            ]);
    }

    public function test_rep_gets_403_if_not_rep(): void
    {
        $rater = $this->authAsRole(Role::RATER);
        $targetRater = $this->createUserWithRole(Role::RATER);

        $this->getJson('/api/v1/raters/'.$targetRater->firebase_uid)
            ->assertForbidden();
    }

    public function test_returns_404_if_rater_not_found(): void
    {
        $this->authAsRole(Role::REPRESENTATIVE);

        $this->getJson('/api/v1/raters/non-existent-uid')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Rater not found.');
    }

    public function test_returns_only_ratings_from_this_rater_to_logged_in_rep(): void
    {
        $rep = $this->authAsRole(Role::REPRESENTATIVE);
        $rater = $this->createUserWithRole(Role::RATER);
        $otherRater = $this->createUserWithRole(Role::RATER);

        $ratingFromThisRater = Rating::create([
            'firebase_uuid' => 'rating-from-this-rater',
            'rater_firebase_uid' => $rater->firebase_uid,
            'rep_firebase_uid' => $rep->firebase_uid,
            'average_score' => 4.5,
            'rated_at' => now(),
            'created_by' => $rater->firebase_uid,
            'updated_by' => $rater->firebase_uid,
        ]);

        Rating::create([
            'firebase_uuid' => 'rating-from-other-rater',
            'rater_firebase_uid' => $otherRater->firebase_uid,
            'rep_firebase_uid' => $rep->firebase_uid,
            'average_score' => 3.0,
            'rated_at' => now(),
            'created_by' => $otherRater->firebase_uid,
            'updated_by' => $otherRater->firebase_uid,
        ]);

        $response = $this->getJson('/api/v1/raters/'.$rater->firebase_uid);

        $response->assertOk();
        $response->assertJsonPath('data.ratings.total', 1);
        $response->assertJsonPath('data.ratings.data.0.rating_id', $ratingFromThisRater->firebase_uuid);
        $response->assertJsonPath('data.ratings.data.0.rating', 4.5);
    }

    public function test_ratings_include_me_label(): void
    {
        $rep = $this->authAsRole(Role::REPRESENTATIVE);
        $rater = $this->createUserWithRole(Role::RATER, [
            'first_name' => 'David',
            'last_name' => 'Smith',
        ]);

        Rating::create([
            'firebase_uuid' => 'rating-uuid-1',
            'rater_firebase_uid' => $rater->firebase_uid,
            'rep_firebase_uid' => $rep->firebase_uid,
            'average_score' => 5.0,
            'rated_at' => now(),
            'created_by' => $rater->firebase_uid,
            'updated_by' => $rater->firebase_uid,
        ]);

        $this->getJson('/api/v1/raters/'.$rater->firebase_uid)
            ->assertOk()
            ->assertJsonPath('data.ratings.data.0.full_name', 'David Smith (Me)')
            ->assertJsonPath('data.ratings.data.0.rating', 5)
            ->assertJsonStructure(['data' => ['ratings' => ['data' => [0 => ['rating_id', 'rating', 'rated_at', 'image_url', 'full_name']]]]])
            ->assertJsonMissingPath('data.ratings.data.0.comment')
            ->assertJsonMissingPath('data.ratings.data.0.id');
    }

    public function test_ratings_ordered_by_latest_first(): void
    {
        $rep = $this->authAsRole(Role::REPRESENTATIVE);
        $rater = $this->createUserWithRole(Role::RATER);

        $oldRating = Rating::create([
            'firebase_uuid' => 'old-rating-uuid',
            'rater_firebase_uid' => $rater->firebase_uid,
            'rep_firebase_uid' => $rep->firebase_uid,
            'average_score' => 3.0,
            'rated_at' => now()->subDays(2),
            'created_by' => $rater->firebase_uid,
            'updated_by' => $rater->firebase_uid,
        ]);

        $newRating = Rating::create([
            'firebase_uuid' => 'new-rating-uuid',
            'rater_firebase_uid' => $rater->firebase_uid,
            'rep_firebase_uid' => $rep->firebase_uid,
            'average_score' => 5.0,
            'rated_at' => now()->subDay(),
            'created_by' => $rater->firebase_uid,
            'updated_by' => $rater->firebase_uid,
        ]);

        $this->getJson('/api/v1/raters/'.$rater->firebase_uid)
            ->assertOk()
            ->assertJsonPath('data.ratings.data.0.rating_id', $newRating->firebase_uuid)
            ->assertJsonPath('data.ratings.data.1.rating_id', $oldRating->firebase_uuid);
    }

    public function test_returns_empty_ratings_when_no_ratings_from_this_rater(): void
    {
        $rep = $this->authAsRole(Role::REPRESENTATIVE);
        $rater = $this->createUserWithRole(Role::RATER);

        $this->getJson('/api/v1/raters/'.$rater->firebase_uid)
            ->assertOk()
            ->assertJsonPath('data.ratings.total', 0)
            ->assertJsonPath('data.ratings.data', []);
    }
}
