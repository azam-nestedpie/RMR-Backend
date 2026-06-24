<?php

namespace Tests\Feature\Api\V1;

use App\Models\Connection;
use App\Models\Rating;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DashboardEndpointsTest extends V1TestCase
{
    public function test_manager_of_reps_home_returns_team_dashboard_payload(): void
    {
        $manager = $this->authAsRole(Role::MANAGER_OF_REPRESENTATIVES, [
            'first_name' => 'Maya',
            'last_name' => 'Manager',
        ]);
        $rep = $this->createUserWithRole(Role::REPRESENTATIVE, [
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'image_url' => 'https://example.com/avatar1.jpg',
        ]);
        $secondRep = $this->createUserWithRole(Role::REPRESENTATIVE, [
            'first_name' => 'Sara',
            'last_name' => 'Ahmed',
        ]);
        $rater = $this->createUserWithRole(Role::RATER);

        $this->attachTeamMember($manager->firebase_uid, $rep->firebase_uid, 4);
        $this->attachTeamMember($manager->firebase_uid, $secondRep->firebase_uid, 4);

        $this->createRating($rater->firebase_uid, $rep->firebase_uid, 4);
        $this->createRating($rater->firebase_uid, $secondRep->firebase_uid, 5);
        $this->createRatingRequest($manager->firebase_uid, $rater->firebase_uid, $rep->firebase_uid);
        $this->createRatingRequest($manager->firebase_uid, $rater->firebase_uid, $secondRep->firebase_uid);
        $this->createNotification($manager->firebase_uid, false);
        $this->createNotification($manager->firebase_uid, true);

        $this->getJson('/api/v1/dashboard/home')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.team.id', $manager->firebase_uid)
            ->assertJsonPath('data.team.name', 'Maya Manager Team')
            ->assertJsonPath('data.team.team_size', 2)
            ->assertJsonPath('data.team.avg_rating', 4.5)
            ->assertJsonPath('data.quick_stats.ratings_this_month', 2)
            ->assertJsonPath('data.quick_stats.requests_sent', 2)
            ->assertJsonPath('data.quick_stats.engagement', 100)
            ->assertJsonPath('data.notifications.unread_count', 1)
            ->assertJsonPath('data.team_members.0.id', $rep->firebase_uid)
            ->assertJsonPath('data.team_members.0.name', 'Ali Khan')
            ->assertJsonPath('data.team_members.0.image_url', 'https://example.com/avatar1.jpg')
            ->assertJsonPath('data.team_members.0.rating', 4)
            ->assertJsonPath('data.team_members.0.ratings_count', 1)
            ->assertJsonPath('data.team_members.0.status', 'Active');
    }

    public function test_manager_of_raters_home_returns_given_rating_dashboard_payload(): void
    {
        $manager = $this->authAsRole(Role::MANAGER_OF_RATERS, [
            'first_name' => 'Rater',
            'last_name' => 'Lead',
        ]);
        $rater = $this->createUserWithRole(Role::RATER, [
            'first_name' => 'Nora',
            'last_name' => 'Rater',
        ]);
        $rep = $this->createUserWithRole(Role::REPRESENTATIVE);
        $otherRep = $this->createUserWithRole(Role::REPRESENTATIVE);
        $outsideRater = $this->createUserWithRole(Role::RATER);

        $this->attachTeamMember($manager->firebase_uid, $rater->firebase_uid, 3);

        $this->createRating($rater->firebase_uid, $rep->firebase_uid, 3);
        $this->createRating($rater->firebase_uid, $otherRep->firebase_uid, 5);
        $this->createRating($outsideRater->firebase_uid, $rep->firebase_uid, 1);
        $this->createRatingRequest($rep->firebase_uid, $rater->firebase_uid, $rep->firebase_uid);

        $this->getJson('/api/v1/dashboard/home')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.team.id', $manager->firebase_uid)
            ->assertJsonPath('data.team.name', 'Rater Lead Team')
            ->assertJsonPath('data.team.team_size', 1)
            ->assertJsonPath('data.team.avg_rating', 4)
            ->assertJsonPath('data.quick_stats.ratings_this_month', 2)
            ->assertJsonPath('data.quick_stats.requests_sent', 1)
            ->assertJsonPath('data.quick_stats.engagement', 100)
            ->assertJsonPath('data.notifications.unread_count', 0)
            ->assertJsonPath('data.team_members.0.id', $rater->firebase_uid)
            ->assertJsonPath('data.team_members.0.name', 'Nora Rater')
            ->assertJsonPath('data.team_members.0.rating', 4)
            ->assertJsonPath('data.team_members.0.ratings_count', 2)
            ->assertJsonPath('data.team_members.0.status', 'Active');
    }

    public function test_rater_home_returns_profile_recent_connections_and_recent_ratings(): void
    {
        $rater = $this->authAsRole(Role::RATER, [
            'first_name' => 'Nora',
            'last_name' => 'Client',
            'email' => 'nora@example.com',
            'company_name' => 'Client Co',
            'position' => 'Buyer',
            'image_url' => 'https://example.com/rater.jpg',
        ]);
        $rep = $this->createUserWithRole(Role::REPRESENTATIVE, [
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'email' => 'ali@example.com',
            'company_name' => 'Rep Co',
            'position' => 'Sales Lead',
            'image_url' => 'https://example.com/rep.jpg',
        ]);
        $olderRep = $this->createUserWithRole(Role::REPRESENTATIVE, [
            'first_name' => 'Sara',
            'last_name' => 'Ahmed',
            'company_name' => 'Older Co',
            'position' => 'Account Executive',
            'image_url' => 'https://example.com/older-rep.jpg',
        ]);
        $otherRater = $this->createUserWithRole(Role::RATER);

        $this->createConnection($rep->firebase_uid, $rater->firebase_uid, now());
        $this->createConnection($olderRep->firebase_uid, $rater->firebase_uid, now()->subDay());

        $this->createRating($rater->firebase_uid, $rep->firebase_uid, 4);
        $this->createRating($otherRater->firebase_uid, $rep->firebase_uid, 2);
        $this->createRating($rater->firebase_uid, $olderRep->firebase_uid, 5);

        $this->getJson('/api/v1/dashboard/rater-home')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.profile.image_url', 'https://example.com/rater.jpg')
            ->assertJsonPath('data.profile.first_name', 'Nora')
            ->assertJsonPath('data.profile.last_name', 'Client')
            ->assertJsonPath('data.profile.company', 'Client Co')
            ->assertJsonPath('data.profile.position', 'Buyer')
            ->assertJsonPath('data.profile.email', 'nora@example.com')
            ->assertJsonPath('data.recent_connections.0.image_url', 'https://example.com/rep.jpg')
            ->assertJsonPath('data.recent_connections.0.first_name', 'Ali')
            ->assertJsonPath('data.recent_connections.0.last_name', 'Khan')
            ->assertJsonPath('data.recent_connections.0.company', 'Rep Co')
            ->assertJsonPath('data.recent_connections.0.position', 'Sales Lead')
            ->assertJsonPath('data.recent_connections.0.average_rating', 3)
            ->assertJsonPath('data.recent_connections.0.ratings_count', 2)
            ->assertJsonPath('data.recent_ratings.0.image_url', 'https://example.com/older-rep.jpg')
            ->assertJsonPath('data.recent_ratings.0.first_name', 'Sara')
            ->assertJsonPath('data.recent_ratings.0.last_name', 'Ahmed')
            ->assertJsonPath('data.recent_ratings.0.position', 'Account Executive')
            ->assertJsonPath('data.recent_ratings.0.company', 'Older Co')
            ->assertJsonPath('data.recent_ratings.0.rating', 5);
    }

    public function test_rep_home_returns_profile_rating_stats_and_recent_ratings(): void
    {
        $rep = $this->authAsRole(Role::REPRESENTATIVE, [
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'email' => 'ali@example.com',
            'company_name' => 'Rep Co',
            'position' => 'Sales Lead',
            'image_url' => 'https://example.com/rep.jpg',
        ]);
        $rater = $this->createUserWithRole(Role::RATER, [
            'first_name' => 'Nora',
            'last_name' => 'Client',
            'company_name' => 'Client Co',
            'position' => 'Buyer',
            'image_url' => 'https://example.com/rater.jpg',
        ]);
        $newestRater = $this->createUserWithRole(Role::RATER, [
            'first_name' => 'Omar',
            'last_name' => 'Buyer',
            'company_name' => 'Newest Co',
            'position' => 'Director',
            'image_url' => 'https://example.com/newest-rater.jpg',
        ]);
        $otherRep = $this->createUserWithRole(Role::REPRESENTATIVE);

        $this->createRating($rater->firebase_uid, $rep->firebase_uid, 3, now()->subDay());
        $this->createRating($newestRater->firebase_uid, $rep->firebase_uid, 5, now());
        $this->createRating($rater->firebase_uid, $otherRep->firebase_uid, 1, now());

        $this->getJson('/api/v1/dashboard/rep-home')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.profile.image_url', 'https://example.com/rep.jpg')
            ->assertJsonPath('data.profile.first_name', 'Ali')
            ->assertJsonPath('data.profile.last_name', 'Khan')
            ->assertJsonPath('data.profile.company', 'Rep Co')
            ->assertJsonPath('data.profile.position', 'Sales Lead')
            ->assertJsonPath('data.profile.email', 'ali@example.com')
            ->assertJsonPath('data.profile.average_rating', 4)
            ->assertJsonPath('data.profile.ratings_count', 2)
            ->assertJsonPath('data.recent_ratings.0.image_url', 'https://example.com/newest-rater.jpg')
            ->assertJsonPath('data.recent_ratings.0.first_name', 'Omar')
            ->assertJsonPath('data.recent_ratings.0.last_name', 'Buyer')
            ->assertJsonPath('data.recent_ratings.0.company', 'Newest Co')
            ->assertJsonPath('data.recent_ratings.0.position', 'Director')
            ->assertJsonPath('data.recent_ratings.0.rating', 5);
    }

    private function attachTeamMember(string $managerUid, string $memberUid, int $managerRoleId): void
    {
        DB::table('manager_team_members')->insert([
            'manager_firebase_uid' => $managerUid,
            'member_firebase_uid' => $memberUid,
            'manager_type_role_id' => $managerRoleId,
            'active' => true,
            'joined_at' => now(),
            'created_by' => $managerUid,
            'updated_by' => $managerUid,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createRating(string $raterUid, string $repUid, float $score, ?\DateTimeInterface $ratedAt = null): void
    {
        Rating::create([
            'firebase_uuid' => (string) Str::uuid(),
            'rater_firebase_uid' => $raterUid,
            'rep_firebase_uid' => $repUid,
            'average_score' => $score,
            'rated_at' => $ratedAt ?? now(),
            'created_by' => $raterUid,
            'updated_by' => $raterUid,
        ]);
    }

    private function createRatingRequest(string $requesterUid, string $raterUid, string $repUid): void
    {
        DB::table('rating_requests')->insert([
            'firebase_uuid' => (string) Str::uuid(),
            'requester_firebase_uid' => $requesterUid,
            'target_user_firebase_uid' => $raterUid,
            'rater_firebase_uid' => $raterUid,
            'subject_rep_firebase_uid' => $repUid,
            'requested_by_role_id' => 4,
            'status_id' => DB::table('statuses')->where('name', 'pending')->value('id'),
            'requested_at' => now(),
            'created_by' => $requesterUid,
            'updated_by' => $requesterUid,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createConnection(string $repUid, string $raterUid, \DateTimeInterface $connectedAt): void
    {
        Connection::create([
            'firebase_uuid' => (string) Str::uuid(),
            'user_a_firebase_uid' => $repUid,
            'user_b_firebase_uid' => $raterUid,
            'connected_by_uid' => $raterUid,
            'connected_at' => $connectedAt,
            'is_active' => true,
            'created_by' => $raterUid,
            'updated_by' => $raterUid,
        ]);
    }

    private function createNotification(string $recipientUid, bool $isRead): void
    {
        DB::table('notifications')->insert([
            'firebase_uuid' => (string) Str::uuid(),
            'to_user_firebase_uid' => $recipientUid,
            'message' => 'Dashboard notification',
            'is_read' => $isRead,
            'read_at' => $isRead ? now() : null,
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
