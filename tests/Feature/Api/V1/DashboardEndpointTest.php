<?php

use App\Mail\DashboardReportMail;
use App\Models\Connection;
use App\Models\Rating;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->seed(DatabaseSeeder::class);
});

test('manager dashboard endpoint returns summary metrics and team activity', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00'));

    $manager = authAsDashboardRole(Role::MANAGER_OF_REPRESENTATIVES, [
        'first_name' => 'Maya',
        'last_name' => 'Manager',
    ]);
    $firstRep = createDashboardUserWithRole(Role::REPRESENTATIVE, [
        'first_name' => 'Ali',
        'last_name' => 'Khan',
        'image_url' => 'https://example.com/ali.jpg',
        'company_name' => 'Rep Co',
        'position' => 'Account Executive',
    ]);
    $secondRep = createDashboardUserWithRole(Role::REPRESENTATIVE, [
        'first_name' => 'Sara',
        'last_name' => 'Ahmed',
    ]);
    $rater = createDashboardUserWithRole(Role::RATER, [
        'first_name' => 'Nora',
        'last_name' => 'Client',
    ]);

    attachDashboardTeamMember($manager->firebase_uid, $firstRep->firebase_uid);
    attachDashboardTeamMember($manager->firebase_uid, $secondRep->firebase_uid);
    createDashboardConnection($firstRep->firebase_uid, $rater->firebase_uid);
    createDashboardConnection($secondRep->firebase_uid, $rater->firebase_uid);

    $professionalQuestionId = DB::table('rating_questions')->where('question_code', 10)->value('id');
    $listeningQuestionId = DB::table('rating_questions')->where('question_code', 20)->value('id');

    $firstCurrentRating = createDashboardRating($rater->firebase_uid, $firstRep->firebase_uid, 4, now()->subDay());
    createDashboardRatingItem($firstCurrentRating->id, $professionalQuestionId, 4, $rater->firebase_uid);
    createDashboardRatingItem($firstCurrentRating->id, $listeningQuestionId, 5, $rater->firebase_uid);

    $firstLastMonthRating = createDashboardRating($rater->firebase_uid, $firstRep->firebase_uid, 3, now()->subMonthNoOverflow());
    createDashboardRatingItem($firstLastMonthRating->id, $professionalQuestionId, 3, $rater->firebase_uid);

    $secondCurrentRating = createDashboardRating($rater->firebase_uid, $secondRep->firebase_uid, 5, now());
    createDashboardRatingItem($secondCurrentRating->id, $professionalQuestionId, 5, $rater->firebase_uid);

    createDashboardRatingRequest($manager->firebase_uid, $rater->firebase_uid, $firstRep->firebase_uid, 'completed', true);
    createDashboardRatingRequest($manager->firebase_uid, $rater->firebase_uid, $secondRep->firebase_uid, 'accepted', false);

    $this->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.avg_team_resolution_rate', 0)
        ->assertJsonPath('data.team_members.0.firebase_uid', $firstRep->firebase_uid)
        ->assertJsonPath('data.team_members.0.name', 'Ali Khan')
        ->assertJsonPath('data.team_members.0.resolution_rate', 0)
        ->assertJsonPath('data.summary.avg_team_rating', 4)
        ->assertJsonPath('data.summary.trend.value', 1.5)
        ->assertJsonPath('data.summary.trend.is_positive', true)
        ->assertJsonPath('data.summary.ratings_count.current_month', 2)
        ->assertJsonPath('data.summary.ratings_count.last_month', 1)
        ->assertJsonCount(12, 'data.monthly_average_team_rating')
        ->assertJsonPath('data.monthly_average_team_rating.10.month', 'May')
        ->assertJsonPath('data.monthly_average_team_rating.10.rating', 3)
        ->assertJsonPath('data.monthly_average_team_rating.11.month', 'Jun')
        ->assertJsonPath('data.monthly_average_team_rating.11.rating', 4.5)
        ->assertJsonPath('data.rating_by_question.average_score', 4.25)
        ->assertJsonPath('data.rating_by_question.questions.0.question_id', $professionalQuestionId)
        ->assertJsonPath('data.rating_by_question.questions.0.question', 'Is Professional')
        ->assertJsonPath('data.rating_by_question.questions.0.score', 4)
        ->assertJsonPath('data.team_snapshot.0.firebase_uid', $firstRep->firebase_uid)
        ->assertJsonPath('data.team_snapshot.0.first_name', 'Ali')
        ->assertJsonPath('data.team_snapshot.0.last_name', 'Khan')
        ->assertJsonPath('data.team_snapshot.0.image_url', 'https://example.com/ali.jpg')
        ->assertJsonPath('data.team_snapshot.0.company_name', 'Rep Co')
        ->assertJsonPath('data.team_snapshot.0.position', 'Account Executive')
        ->assertJsonPath('data.team_snapshot.0.average_rating', 3.5)
        ->assertJsonPath('data.team_snapshot.0.ratings_count', 2)
        ->assertJsonPath('data.team_snapshot.0.trend.value', 33.33)
        ->assertJsonPath('data.team_snapshot.0.trend.is_positive', true)
        ->assertJsonPath('data.engagement_metrics.overall_rate', 100)
        ->assertJsonPath('data.engagement_metrics.submitted_ratings', 2)
        ->assertJsonPath('data.engagement_metrics.total_requests', 2)
        ->assertJsonPath('data.engagement_metrics.members.0.firebase_uid', $firstRep->firebase_uid)
        ->assertJsonPath('data.engagement_metrics.members.0.name', 'Ali Khan')
        ->assertJsonPath('data.engagement_metrics.members.0.submitted_ratings', 1)
        ->assertJsonPath('data.engagement_metrics.members.0.total_requests', 1)
        ->assertJsonPath('data.engagement_metrics.members.0.rate', 100)
        ->assertJsonPath('data.resolution_metrics.overall_rate', 0)
        ->assertJsonPath('data.resolution_metrics.resolved_ratings', 0)
        ->assertJsonPath('data.resolution_metrics.low_ratings', 0)
        ->assertJsonPath('data.resolution_metrics.members.0.firebase_uid', $firstRep->firebase_uid)
        ->assertJsonPath('data.resolution_metrics.members.0.resolved_ratings', 0)
        ->assertJsonPath('data.resolution_metrics.members.0.low_ratings', 0)
        ->assertJsonPath('data.resolution_metrics.members.0.rate', 0)
        ->assertJsonPath('data.recent_feedback.0.from.firebase_uid', $rater->firebase_uid)
        ->assertJsonPath('data.recent_feedback.0.from.first_name', 'Nora')
        ->assertJsonPath('data.recent_feedback.0.to.firebase_uid', $secondRep->firebase_uid)
        ->assertJsonPath('data.recent_feedback.0.to.first_name', 'Sara')
        ->assertJsonPath('data.recent_feedback.0.rating', 5)
        ->assertJsonPath('data.recent_feedback.0.created_at', '2026-06-15T12:00:00.000000Z')
        ->assertJsonPath('data.export.email_enabled', true);

    expect(Cache::has("dashboard_{$manager->firebase_uid}"))->toBeTrue();

    Carbon::setTestNow();
});

test('manager dashboard calculates engagement and resolution from rating formulas', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00'));

    $manager = authAsDashboardRole(Role::MANAGER_OF_REPRESENTATIVES);
    $rep = createDashboardUserWithRole(Role::REPRESENTATIVE, [
        'first_name' => 'Ali',
        'last_name' => 'Khan',
    ]);
    $secondRep = createDashboardUserWithRole(Role::REPRESENTATIVE, [
        'first_name' => 'Sara',
        'last_name' => 'Ahmed',
    ]);
    $rater = createDashboardUserWithRole(Role::RATER);
    $otherRater = createDashboardUserWithRole(Role::RATER);

    attachDashboardTeamMember($manager->firebase_uid, $rep->firebase_uid);
    attachDashboardTeamMember($manager->firebase_uid, $secondRep->firebase_uid);

    createDashboardRating($rater->firebase_uid, $rep->firebase_uid, 2, now()->subDays(5));
    createDashboardRating($rater->firebase_uid, $rep->firebase_uid, 4, now()->subDays(2));
    createDashboardRating($otherRater->firebase_uid, $rep->firebase_uid, 2, now()->subDay());
    createDashboardRating($rater->firebase_uid, $secondRep->firebase_uid, 5, now());

    createDashboardRatingRequest($manager->firebase_uid, $rater->firebase_uid, $rep->firebase_uid, 'completed', true);
    createDashboardRatingRequest($manager->firebase_uid, $otherRater->firebase_uid, $rep->firebase_uid, 'pending', false);
    createDashboardRatingRequest($manager->firebase_uid, $rater->firebase_uid, $secondRep->firebase_uid, 'pending', false);
    createDashboardRatingRequest($manager->firebase_uid, $otherRater->firebase_uid, $secondRep->firebase_uid, 'pending', false);

    $this->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.engagement_metrics.overall_rate', 50)
        ->assertJsonPath('data.engagement_metrics.submitted_ratings', 2)
        ->assertJsonPath('data.engagement_metrics.total_requests', 4)
        ->assertJsonPath('data.engagement_metrics.members.0.firebase_uid', $rep->firebase_uid)
        ->assertJsonPath('data.engagement_metrics.members.0.submitted_ratings', 1)
        ->assertJsonPath('data.engagement_metrics.members.0.total_requests', 2)
        ->assertJsonPath('data.engagement_metrics.members.0.rate', 50)
        ->assertJsonPath('data.engagement_metrics.members.1.firebase_uid', $secondRep->firebase_uid)
        ->assertJsonPath('data.engagement_metrics.members.1.submitted_ratings', 1)
        ->assertJsonPath('data.engagement_metrics.members.1.total_requests', 2)
        ->assertJsonPath('data.engagement_metrics.members.1.rate', 50)
        ->assertJsonPath('data.resolution_metrics.overall_rate', 50)
        ->assertJsonPath('data.resolution_metrics.resolved_ratings', 1)
        ->assertJsonPath('data.resolution_metrics.low_ratings', 2)
        ->assertJsonPath('data.avg_team_resolution_rate', 50)
        ->assertJsonPath('data.team_members.0.firebase_uid', $rep->firebase_uid)
        ->assertJsonPath('data.team_members.0.name', 'Ali Khan')
        ->assertJsonPath('data.team_members.0.resolution_rate', 50)
        ->assertJsonPath('data.team_members.1.firebase_uid', $secondRep->firebase_uid)
        ->assertJsonPath('data.team_members.1.name', 'Sara Ahmed')
        ->assertJsonPath('data.team_members.1.resolution_rate', 0)
        ->assertJsonPath('data.resolution_metrics.members.0.firebase_uid', $rep->firebase_uid)
        ->assertJsonPath('data.resolution_metrics.members.0.resolved_ratings', 1)
        ->assertJsonPath('data.resolution_metrics.members.0.low_ratings', 2)
        ->assertJsonPath('data.resolution_metrics.members.0.rate', 50)
        ->assertJsonPath('data.resolution_metrics.members.1.firebase_uid', $secondRep->firebase_uid)
        ->assertJsonPath('data.resolution_metrics.members.1.rate', 0);

    Carbon::setTestNow();
});

test('manager dashboard calculates resolution from edited low ratings', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00'));

    $manager = authAsDashboardRole(Role::MANAGER_OF_REPRESENTATIVES);
    $rep = createDashboardUserWithRole(Role::REPRESENTATIVE, [
        'first_name' => 'Ali',
        'last_name' => 'Khan',
    ]);
    $rater = createDashboardUserWithRole(Role::RATER);

    attachDashboardTeamMember($manager->firebase_uid, $rep->firebase_uid);

    $rating = createDashboardRating($rater->firebase_uid, $rep->firebase_uid, 4, now()->subDay());
    createDashboardRatingEdit($rating->id, $rater->firebase_uid, $rep->firebase_uid, 2, 4, now());

    $this->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.avg_team_resolution_rate', 100)
        ->assertJsonPath('data.team_members.0.firebase_uid', $rep->firebase_uid)
        ->assertJsonPath('data.team_members.0.name', 'Ali Khan')
        ->assertJsonPath('data.team_members.0.resolution_rate', 100)
        ->assertJsonPath('data.resolution_metrics.overall_rate', 100)
        ->assertJsonPath('data.resolution_metrics.resolved_ratings', 1)
        ->assertJsonPath('data.resolution_metrics.low_ratings', 1);

    Carbon::setTestNow();
});

test('manager dashboard does not resolve an edited low rating with an older high rating', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00'));

    $manager = authAsDashboardRole(Role::MANAGER_OF_REPRESENTATIVES);
    $rep = createDashboardUserWithRole(Role::REPRESENTATIVE, [
        'first_name' => 'Ali',
        'last_name' => 'Khan',
    ]);
    $rater = createDashboardUserWithRole(Role::RATER);

    attachDashboardTeamMember($manager->firebase_uid, $rep->firebase_uid);

    createDashboardRating($rater->firebase_uid, $rep->firebase_uid, 4, now()->subHour());
    $editedRating = createDashboardRating($rater->firebase_uid, $rep->firebase_uid, 2, now()->subDay());
    createDashboardRatingEdit($editedRating->id, $rater->firebase_uid, $rep->firebase_uid, 4, 2, now());

    $this->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.avg_team_resolution_rate', 0)
        ->assertJsonPath('data.team_members.0.firebase_uid', $rep->firebase_uid)
        ->assertJsonPath('data.team_members.0.resolution_rate', 0)
        ->assertJsonPath('data.resolution_metrics.overall_rate', 0)
        ->assertJsonPath('data.resolution_metrics.resolved_ratings', 0)
        ->assertJsonPath('data.resolution_metrics.low_ratings', 1);

    Carbon::setTestNow();
});

test('manager can queue dashboard export email', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00'));
    Mail::fake();

    $manager = authAsDashboardRole(Role::MANAGER_OF_REPRESENTATIVES, [
        'email' => 'manager@example.com',
    ]);

    $this->postJson('/api/v1/dashboard/export-email')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Dashboard export email queued.')
        ->assertJsonPath('data.status', 'sent');

    $this->assertDatabaseHas('dashboard_exports', [
        'requested_by_firebase_uid' => $manager->firebase_uid,
        'scope_type' => 'team',
        'scope_user_firebase_uid' => $manager->firebase_uid,
        'status_id' => DB::table('statuses')->where('name', 'completed')->value('id'),
    ]);
    Mail::assertSent(DashboardReportMail::class, fn (DashboardReportMail $mail): bool => $mail->hasTo('manager@example.com'));

    Carbon::setTestNow();
});

function createDashboardUserWithRole(int $role, array $overrides = []): User
{
    $user = User::factory()->create(array_merge([
        'password' => bcrypt('password'),
        'is_blocked' => false,
        'is_deleted' => false,
    ], $overrides));

    $user->roles()->attach($role, [
        'created_by' => $user->firebase_uid,
        'updated_by' => $user->firebase_uid,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $user->fresh()->load('roles');
}

function authAsDashboardRole(int|string $role, array $overrides = []): User
{
    $user = createDashboardUserWithRole($role, $overrides);
    Sanctum::actingAs($user);

    return $user;
}

function attachDashboardTeamMember(string $managerUid, string $memberUid): void
{
    DB::table('manager_team_members')->insert([
        'manager_firebase_uid' => $managerUid,
        'member_firebase_uid' => $memberUid,
        'manager_type_role_id' => Role::MANAGER_OF_REPRESENTATIVES,
        'active' => true,
        'joined_at' => now(),
        'created_by' => $managerUid,
        'updated_by' => $managerUid,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function createDashboardConnection(string $repUid, string $raterUid): void
{
    Connection::create([
        'firebase_uuid' => (string) Str::uuid(),
        'user_a_firebase_uid' => $repUid,
        'user_b_firebase_uid' => $raterUid,
        'connected_by_uid' => $raterUid,
        'connected_at' => now(),
        'is_active' => true,
        'created_by' => $raterUid,
        'updated_by' => $raterUid,
    ]);
}

function createDashboardRating(string $raterUid, string $repUid, float $score, DateTimeInterface $ratedAt): Rating
{
    return Rating::create([
        'firebase_uuid' => (string) Str::uuid(),
        'rater_firebase_uid' => $raterUid,
        'rep_firebase_uid' => $repUid,
        'average_score' => $score,
        'rated_at' => $ratedAt,
        'created_by' => $raterUid,
        'updated_by' => $raterUid,
    ]);
}

function createDashboardRatingItem(int $ratingId, int $questionId, float $score, string $createdBy): void
{
    DB::table('rating_items')->insert([
        'rating_id' => $ratingId,
        'question_id' => $questionId,
        'score' => $score,
        'created_by' => $createdBy,
        'updated_by' => $createdBy,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function createDashboardRatingEdit(int $ratingId, string $raterUid, string $repUid, float $previousScore, float $newScore, DateTimeInterface $editedAt): void
{
    DB::table('rating_edits')->insert([
        'rating_id' => $ratingId,
        'rater_firebase_uid' => $raterUid,
        'rep_firebase_uid' => $repUid,
        'previous_average_score' => $previousScore,
        'new_average_score' => $newScore,
        'edited_at' => $editedAt,
        'created_by' => $raterUid,
        'updated_by' => $raterUid,
        'created_at' => $editedAt,
        'updated_at' => $editedAt,
    ]);
}

function createDashboardRatingRequest(string $requesterUid, string $raterUid, string $repUid, string $statusName, bool $completed): void
{
    DB::table('rating_requests')->insert([
        'firebase_uuid' => (string) Str::uuid(),
        'requester_firebase_uid' => $requesterUid,
        'target_user_firebase_uid' => $raterUid,
        'rater_firebase_uid' => $raterUid,
        'subject_rep_firebase_uid' => $repUid,
        'requested_by_role_id' => Role::MANAGER_OF_REPRESENTATIVES,
        'status_id' => DB::table('statuses')->where('name', $statusName)->value('id'),
        'requested_at' => now(),
        'completed_at' => $completed ? now() : null,
        'created_by' => $requesterUid,
        'updated_by' => $requesterUid,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}
