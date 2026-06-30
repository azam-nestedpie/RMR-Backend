<?php

namespace Tests\Feature\Api\V1;

use App\Models\Connection;
use App\Models\Rating;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserProfileEndpointTest extends V1TestCase
{
    public function test_rater_can_view_rep_profile(): void
    {
        $rater = $this->authAsRole(Role::RATER, [
            'first_name' => 'Nora',
            'last_name' => 'Client',
        ]);
        $rep = $this->createUserWithRole(Role::REPRESENTATIVE, [
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'company_name' => 'Rep Co',
            'position' => 'Sales Lead',
            'email' => 'ali@example.com',
            'image_url' => 'https://example.com/rep.jpg',
            'bio' => 'Top rep',
        ]);

        Connection::create([
            'firebase_uuid' => (string) Str::uuid(),
            'user_a_firebase_uid' => $rep->firebase_uid,
            'user_b_firebase_uid' => $rater->firebase_uid,
            'connected_by_uid' => $rater->firebase_uid,
            'connected_at' => now(),
            'is_active' => true,
            'created_by' => $rater->firebase_uid,
            'updated_by' => $rater->firebase_uid,
        ]);

        $this->createRating($rater->firebase_uid, $rep->firebase_uid, 4);
        $this->createRating($rater->firebase_uid, $rep->firebase_uid, 5);

        $this->getJson('/api/v1/users/'.$rep->firebase_uid.'/profile')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.firebase_uid', $rep->firebase_uid)
            ->assertJsonPath('data.full_name', 'Ali Khan')
            ->assertJsonPath('data.company_name', 'Rep Co')
            ->assertJsonPath('data.position', 'Sales Lead')
            ->assertJsonPath('data.bio', 'Top rep')
            ->assertJsonPath('data.email', 'ali@example.com')
            ->assertJsonPath('data.image_url', 'https://example.com/rep.jpg')
            ->assertJsonPath('data.first_name', 'Ali')
            ->assertJsonPath('data.last_name', 'Khan')
            ->assertJsonPath('data.connection_status', 'connected')
            ->assertJsonPath('data.avg_rating', 4.5)
            ->assertJsonPath('data.rating_count', 2)
            ->assertJsonIsArray('data.ratings.data')
            ->assertJsonPath('data.ratings.total', 2)
            ->assertJsonPath('data.role.id', Role::REPRESENTATIVE)
            ->assertJsonPath('data.role.name', 'Representative');
    }

    public function test_rep_can_view_rater_profile(): void
    {
        $rep = $this->authAsRole(Role::REPRESENTATIVE, [
            'first_name' => 'Ali',
            'last_name' => 'Khan',
        ]);
        $rater = $this->createUserWithRole(Role::RATER, [
            'first_name' => 'Nora',
            'last_name' => 'Client',
            'company_name' => 'Client Co',
            'position' => 'Buyer',
            'email' => 'nora@example.com',
            'image_url' => 'https://example.com/rater.jpg',
            'bio' => 'Veteran rater',
        ]);

        Connection::create([
            'firebase_uuid' => (string) Str::uuid(),
            'user_a_firebase_uid' => $rep->firebase_uid,
            'user_b_firebase_uid' => $rater->firebase_uid,
            'connected_by_uid' => $rep->firebase_uid,
            'connected_at' => now(),
            'is_active' => true,
            'created_by' => $rep->firebase_uid,
            'updated_by' => $rep->firebase_uid,
        ]);

        $this->createRating($rater->firebase_uid, $rep->firebase_uid, 4);

        $this->getJson('/api/v1/users/'.$rater->firebase_uid.'/profile')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.firebase_uid', $rater->firebase_uid)
            ->assertJsonPath('data.full_name', 'Nora Client')
            ->assertJsonPath('data.company_name', 'Client Co')
            ->assertJsonPath('data.position', 'Buyer')
            ->assertJsonPath('data.bio', 'Veteran rater')
            ->assertJsonPath('data.first_name', 'Nora')
            ->assertJsonPath('data.last_name', 'Client')
            ->assertJsonPath('data.email', 'nora@example.com')
            ->assertJsonPath('data.image_url', 'https://example.com/rater.jpg')
            ->assertJsonPath('data.connection_status', 'rating_request')
            ->assertJsonMissingPath('data.avg_rating')
            ->assertJsonMissingPath('data.rating_count')
            ->assertJsonIsArray('data.ratings.data')
            ->assertJsonPath('data.ratings.total', 1)
            ->assertJsonPath('data.role.id', Role::RATER)
            ->assertJsonPath('data.role.name', 'Rater');
    }

    public function test_ratings_include_is_from_me_flag(): void
    {
        $rater = $this->authAsRole(Role::RATER, [
            'first_name' => 'Nora',
            'last_name' => 'Client',
        ]);
        $rep = $this->createUserWithRole(Role::REPRESENTATIVE, [
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'company_name' => 'Rep Co',
        ]);
        $otherRater = $this->createUserWithRole(Role::RATER);

        $this->createRating($rater->firebase_uid, $rep->firebase_uid, 4);
        $this->createRating($otherRater->firebase_uid, $rep->firebase_uid, 3);

        $response = $this->getJson('/api/v1/users/'.$rep->firebase_uid.'/profile')
            ->assertOk()
            ->assertJsonCount(2, 'data.ratings.data');

        $ratings = $response['data']['ratings']['data'];

        $fromMe = collect($ratings)->firstWhere('is_from_me', true);
        expect($fromMe)->not->toBeNull();
        expect($fromMe['avg_rating'])->toBe(4);

        $notFromMe = collect($ratings)->firstWhere('is_from_me', false);
        expect($notFromMe)->not->toBeNull();
        expect($notFromMe['avg_rating'])->toBe(3);
    }

    public function test_returns_connect_when_not_connected(): void
    {
        $rater = $this->authAsRole(Role::RATER);
        $rep = $this->createUserWithRole(Role::REPRESENTATIVE);

        $this->getJson('/api/v1/users/'.$rep->firebase_uid.'/profile')
            ->assertOk()
            ->assertJsonPath('data.connection_status', 'connect');
    }

    public function test_returns_request_sent_when_pending_connection_request(): void
    {
        $rater = $this->authAsRole(Role::RATER);
        $rep = $this->createUserWithRole(Role::REPRESENTATIVE);

        $this->createConnectionRequest($rater->firebase_uid, $rep->firebase_uid);

        $this->getJson('/api/v1/users/'.$rep->firebase_uid.'/profile')
            ->assertOk()
            ->assertJsonPath('data.connection_status', 'request_sent');
    }

    public function test_returns_404_when_user_not_found(): void
    {
        $this->authAsRole(Role::REPRESENTATIVE);

        $this->getJson('/api/v1/users/nonexistent/profile')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'User not found.');
    }

    public function test_manager_can_view_rater_profile(): void
    {
        $manager = $this->authAsRole(Role::MANAGER_OF_RATERS);
        $rater = $this->createUserWithRole(Role::RATER, [
            'first_name' => 'Nora',
            'last_name' => 'Client',
        ]);

        $this->getJson('/api/v1/users/'.$rater->firebase_uid.'/profile')
            ->assertOk()
            ->assertJsonPath('data.firebase_uid', $rater->firebase_uid)
            ->assertJsonPath('data.role.id', Role::RATER)
            ->assertJsonPath('data.role.name', 'Rater');
    }

    public function test_manager_can_view_rep_profile(): void
    {
        $manager = $this->authAsRole(Role::MANAGER_OF_REPRESENTATIVES);
        $rep = $this->createUserWithRole(Role::REPRESENTATIVE, [
            'first_name' => 'Ali',
            'last_name' => 'Khan',
        ]);

        $this->getJson('/api/v1/users/'.$rep->firebase_uid.'/profile')
            ->assertOk()
            ->assertJsonPath('data.firebase_uid', $rep->firebase_uid)
            ->assertJsonPath('data.role.id', Role::REPRESENTATIVE)
            ->assertJsonPath('data.role.name', 'Representative');
    }

    public function test_rep_profile_shows_request_sent_when_pending_rating_request(): void
    {
        $rep = $this->authAsRole(Role::REPRESENTATIVE);
        $rater = $this->createUserWithRole(Role::RATER);

        $this->createConnection($rep->firebase_uid, $rater->firebase_uid);
        $this->createRatingRequest($rep->firebase_uid, $rater->firebase_uid);

        $this->getJson('/api/v1/users/'.$rater->firebase_uid.'/profile')
            ->assertOk()
            ->assertJsonPath('data.connection_status', 'request_sent');
    }

    public function test_rep_cannot_view_another_rep_profile(): void
    {
        $rep = $this->authAsRole(Role::REPRESENTATIVE);
        $otherRep = $this->createUserWithRole(Role::REPRESENTATIVE);

        $this->getJson('/api/v1/users/'.$otherRep->firebase_uid.'/profile')
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'You do not have permission to view this profile.');
    }

    public function test_rater_cannot_view_another_rater_profile(): void
    {
        $rater = $this->authAsRole(Role::RATER);
        $otherRater = $this->createUserWithRole(Role::RATER);

        $this->getJson('/api/v1/users/'.$otherRater->firebase_uid.'/profile')
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'You do not have permission to view this profile.');
    }

    public function test_rater_connected_to_rep_shows_connected(): void
    {
        $rater = $this->authAsRole(Role::RATER);
        $rep = $this->createUserWithRole(Role::REPRESENTATIVE);

        $this->createConnection($rep->firebase_uid, $rater->firebase_uid);

        $this->getJson('/api/v1/users/'.$rep->firebase_uid.'/profile')
            ->assertOk()
            ->assertJsonPath('data.connection_status', 'connected');
    }

    private function createRating(string $raterUid, string $repUid, float $score): void
    {
        Rating::create([
            'firebase_uuid' => (string) Str::uuid(),
            'rater_firebase_uid' => $raterUid,
            'rep_firebase_uid' => $repUid,
            'average_score' => $score,
            'rated_at' => now(),
            'created_by' => $raterUid,
            'updated_by' => $raterUid,
        ]);
    }

    private function createConnection(string $userAUid, string $userBUid): void
    {
        Connection::create([
            'firebase_uuid' => (string) Str::uuid(),
            'user_a_firebase_uid' => $userAUid,
            'user_b_firebase_uid' => $userBUid,
            'connected_by_uid' => $userAUid,
            'connected_at' => now(),
            'is_active' => true,
            'created_by' => $userAUid,
            'updated_by' => $userAUid,
        ]);
    }

    private function createConnectionRequest(string $requesterUid, string $targetUid): void
    {
        DB::table('connection_requests')->insert([
            'firebase_uuid' => (string) Str::uuid(),
            'requester_firebase_uid' => $requesterUid,
            'target_user_firebase_uid' => $targetUid,
            'status_id' => DB::table('statuses')->where('name', 'pending')->value('id'),
            'created_by' => $requesterUid,
            'updated_by' => $requesterUid,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createRatingRequest(string $requesterUid, string $raterUid): void
    {
        DB::table('rating_requests')->insert([
            'firebase_uuid' => (string) Str::uuid(),
            'requester_firebase_uid' => $requesterUid,
            'target_user_firebase_uid' => $raterUid,
            'rater_firebase_uid' => $raterUid,
            'subject_rep_firebase_uid' => $requesterUid,
            'requested_by_role_id' => Role::REPRESENTATIVE,
            'status_id' => DB::table('statuses')->where('name', 'pending')->value('id'),
            'requested_at' => now(),
            'created_by' => $requesterUid,
            'updated_by' => $requesterUid,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
