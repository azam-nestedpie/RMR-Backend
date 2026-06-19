<?php

namespace Tests\Feature\Api\V1;

use App\Models\Connection;
use App\Models\ConnectionRequest;
use App\Models\Rating;
use App\Models\Status;
use Illuminate\Support\Facades\DB;

class RepProfileTest extends V1TestCase
{
    public function test_rater_can_view_rep_profile(): void
    {
        $rater = $this->authAsRole('rater');
        $rep = $this->createUserWithRole('rep', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'company_name' => 'ABC Pharma',
            'position' => 'Medical Representative',
            'bio' => 'Experienced Medical Rep',
            'email' => 'john@example.com',
        ]);

        $repProfile = DB::table('sales_rep_users')->insert([
            'user_firebase_uid' => $rep->firebase_uid,
            'avg_rating' => 4.5,
            'ratings_count' => 5,
            'created_by' => $rep->firebase_uid,
            'updated_by' => $rep->firebase_uid,
        ]);

        $this->getJson('/api/v1/reps/'.$rep->firebase_uid)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.firebase_uuid', $rep->firebase_uid)
            ->assertJsonPath('data.full_name', 'John Doe')
            ->assertJsonPath('data.company_name', 'ABC Pharma')
            ->assertJsonPath('data.position', 'Medical Representative')
            ->assertJsonPath('data.bio', 'Experienced Medical Rep')
            ->assertJsonPath('data.email', 'john@example.com')
            ->assertJsonPath('data.connection_status', 'connect')
            ->assertJsonStructure([
                'data' => [
                    'firebase_uuid',
                    'full_name',
                    'company_name',
                    'position',
                    'average_rating',
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

    public function test_rater_gets_403_if_not_rater(): void
    {
        $rep = $this->authAsRole('rep');
        $targetRep = $this->createUserWithRole('rep');

        $this->getJson('/api/v1/reps/'.$targetRep->firebase_uid)
            ->assertForbidden();
    }

    public function test_returns_404_if_rep_not_found(): void
    {
        $this->authAsRole('rater');

        $this->getJson('/api/v1/reps/non-existent-uid')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Rep not found.');
    }

    public function test_returns_connection_status_connected(): void
    {
        $rater = $this->authAsRole('rater');
        $rep = $this->createUserWithRole('rep');

        Connection::create([
            'user_a_firebase_uid' => $rater->firebase_uid,
            'user_b_firebase_uid' => $rep->firebase_uid,
            'connected_by_uid' => $rater->firebase_uid,
            'is_active' => true,
            'connected_at' => now(),
            'created_by' => $rater->firebase_uid,
            'updated_by' => $rater->firebase_uid,
        ]);

        $this->getJson('/api/v1/reps/'.$rep->firebase_uid)
            ->assertOk()
            ->assertJsonPath('data.connection_status', 'connected');
    }

    public function test_returns_connection_status_pending(): void
    {
        $rater = $this->authAsRole('rater');
        $rep = $this->createUserWithRole('rep');
        $pendingStatusId = Status::idByName('pending');

        ConnectionRequest::create([
            'firebase_uuid' => 'pending-request-uuid',
            'requester_firebase_uid' => $rater->firebase_uid,
            'target_user_firebase_uid' => $rep->firebase_uid,
            'status_id' => $pendingStatusId,
            'created_by' => $rater->firebase_uid,
            'updated_by' => $rater->firebase_uid,
        ]);

        $this->getJson('/api/v1/reps/'.$rep->firebase_uid)
            ->assertOk()
            ->assertJsonPath('data.connection_status', 'request_sent');
    }

    public function test_returns_connection_status_rejected(): void
    {
        $rater = $this->authAsRole('rater');
        $rep = $this->createUserWithRole('rep');
        $rejectedStatusId = Status::idByName('rejected');

        ConnectionRequest::create([
            'firebase_uuid' => 'rejected-request-uuid',
            'requester_firebase_uid' => $rater->firebase_uid,
            'target_user_firebase_uid' => $rep->firebase_uid,
            'status_id' => $rejectedStatusId,
            'created_by' => $rater->firebase_uid,
            'updated_by' => $rater->firebase_uid,
        ]);

        $this->getJson('/api/v1/reps/'.$rep->firebase_uid)
            ->assertOk()
            ->assertJsonPath('data.connection_status', 'connect');
    }

    public function test_ratings_are_ordered_by_latest_first(): void
    {
        $rater = $this->authAsRole('rater');
        $rep = $this->createUserWithRole('rep');

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

        $this->getJson('/api/v1/reps/'.$rep->firebase_uid)
            ->assertOk()
            ->assertJsonPath('data.ratings.data.0.uuid', $newRating->firebase_uuid)
            ->assertJsonPath('data.ratings.data.1.uuid', $oldRating->firebase_uuid);
    }

    public function test_rating_includes_rater_information(): void
    {
        $rater = $this->authAsRole('rater');
        $rep = $this->createUserWithRole('rep');

        $rating = Rating::create([
            'firebase_uuid' => 'rating-uuid-1',
            'rater_firebase_uid' => $rater->firebase_uid,
            'rep_firebase_uid' => $rep->firebase_uid,
            'average_score' => 4.0,
            'rated_at' => now(),
            'created_by' => $rater->firebase_uid,
            'updated_by' => $rater->firebase_uid,
        ]);

        $this->getJson('/api/v1/reps/'.$rep->firebase_uid)
            ->assertOk()
            ->assertJsonPath('data.ratings.data.0.uuid', $rating->firebase_uuid)
            ->assertJsonPath('data.ratings.data.0.rating', 4)
            ->assertJsonPath('data.ratings.data.0.full_name', $rater->first_name.' '.$rater->last_name)
            ->assertJsonPath('data.ratings.data.0.company_name', $rater->company_name)
            ->assertJsonPath('data.ratings.data.0.position', $rater->position)
            ->assertJsonStructure(['data' => ['ratings' => ['data' => [0 => ['rated_at', 'image_url']]]]])
            ->assertJsonMissingPath('data.ratings.data.0.comment')
            ->assertJsonMissingPath('data.ratings.data.0.id');
    }

    public function test_average_rating_is_null_when_no_ratings(): void
    {
        $rater = $this->authAsRole('rater');
        $rep = $this->createUserWithRole('rep');

        $this->getJson('/api/v1/reps/'.$rep->firebase_uid)
            ->assertOk()
            ->assertJsonPath('data.average_rating', null);
    }
}
