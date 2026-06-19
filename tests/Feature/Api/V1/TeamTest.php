<?php

namespace Tests\Feature\Api\V1;

use App\Models\Status;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

class TeamTest extends V1TestCase
{
    public function test_manager_can_invite_multiple_team_members(): void
    {
        $manager = $this->authAsRole('manager_of_reps');
        $firstRep = $this->createUserWithRole('rep');
        $secondRep = $this->createUserWithRole('rep');

        $response = $this->postJson('/api/v1/team', [
            'target_uids' => [$firstRep->firebase_uid, $secondRep->firebase_uid],
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Team invitations sent.');

        $this->assertCount(2, $response->json('data.teams'));

        foreach ([$firstRep, $secondRep] as $rep) {
            $this->assertDatabaseHas('team_requests', [
                'manager_firebase_uid' => $manager->firebase_uid,
                'target_user_firebase_uid' => $rep->firebase_uid,
                'status_id' => Status::idByName('pending'),
                'created_by' => $manager->firebase_uid,
            ]);
        }

        $this->assertDatabaseMissing('connection_requests', [
            'requester_firebase_uid' => $manager->firebase_uid,
        ]);
    }

    public function test_team_member_can_accept_own_team_invitation(): void
    {
        $manager = $this->authAsRole('manager_of_reps');
        $rep = $this->createUserWithRole('rep');

        $response = $this->postJson('/api/v1/team', [
            'target_uid' => $rep->firebase_uid,
        ])->assertCreated();

        Sanctum::actingAs($rep);

        $this->postJson('/api/v1/team-requests/accept', [
            'request_uuid' => $response->json('data.teams.0.team_uuid'),
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Team invitation accepted.');

        $this->assertDatabaseHas('team_requests', [
            'firebase_uuid' => $response->json('data.teams.0.team_uuid'),
            'status_id' => Status::idByName('accepted'),
            'updated_by' => $rep->firebase_uid,
        ]);

        $this->assertDatabaseHas('manager_team_members', [
            'manager_firebase_uid' => $manager->firebase_uid,
            'member_firebase_uid' => $rep->firebase_uid,
            'active' => true,
        ]);
    }

    public function test_team_member_can_reject_own_team_invitation(): void
    {
        $manager = $this->authAsRole('manager_of_reps');
        $rep = $this->createUserWithRole('rep');

        $response = $this->postJson('/api/v1/team', [
            'target_uid' => $rep->firebase_uid,
        ])->assertCreated();

        Sanctum::actingAs($rep);

        $this->postJson('/api/v1/team-requests/reject', [
            'request_uuid' => $response->json('data.teams.0.team_uuid'),
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Team invitation rejected.');

        $this->assertDatabaseHas('team_requests', [
            'firebase_uuid' => $response->json('data.teams.0.team_uuid'),
            'status_id' => Status::idByName('rejected'),
            'updated_by' => $rep->firebase_uid,
        ]);

        $this->assertDatabaseMissing('manager_team_members', [
            'manager_firebase_uid' => $manager->firebase_uid,
            'member_firebase_uid' => $rep->firebase_uid,
        ]);
    }

    public function test_manager_can_cancel_own_team_invitation(): void
    {
        $manager = $this->authAsRole('manager_of_reps');
        $rep = $this->createUserWithRole('rep');

        $response = $this->postJson('/api/v1/team', [
            'target_uid' => $rep->firebase_uid,
        ])->assertCreated();

        $this->postJson('/api/v1/team-requests/cancel', [
            'request_uuid' => $response->json('data.teams.0.team_uuid'),
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Team invitation cancelled.');

        $this->assertDatabaseHas('team_requests', [
            'firebase_uuid' => $response->json('data.teams.0.team_uuid'),
            'manager_firebase_uid' => $manager->firebase_uid,
            'status_id' => Status::idByName('cancelled'),
        ]);
    }

    public function test_only_target_user_can_accept_team_invitation(): void
    {
        $this->authAsRole('manager_of_reps');
        $rep = $this->createUserWithRole('rep');
        $otherRep = $this->createUserWithRole('rep');

        $response = $this->postJson('/api/v1/team', [
            'target_uid' => $rep->firebase_uid,
        ])->assertCreated();

        Sanctum::actingAs($otherRep);

        $this->postJson('/api/v1/team-requests/accept', [
            'request_uuid' => $response->json('data.teams.0.team_uuid'),
        ])
            ->assertNotFound()
            ->assertJsonPath('message', 'Team invitation not found.');
    }

    public function test_only_manager_can_cancel_team_invitation(): void
    {
        $this->authAsRole('manager_of_reps');
        $rep = $this->createUserWithRole('rep');

        $response = $this->postJson('/api/v1/team', [
            'target_uid' => $rep->firebase_uid,
        ])->assertCreated();

        Sanctum::actingAs($rep);

        $this->postJson('/api/v1/team-requests/cancel', [
            'request_uuid' => $response->json('data.teams.0.team_uuid'),
        ])
            ->assertNotFound()
            ->assertJsonPath('message', 'Team invitation not found.');
    }

    public function test_user_can_view_pending_team_invitations(): void
    {
        $manager = $this->authAsRole('manager_of_reps');
        $rep = $this->createUserWithRole('rep');

        $response = $this->postJson('/api/v1/team', [
            'target_uid' => $rep->firebase_uid,
        ])->assertCreated();

        $this->getJson('/api/v1/team/pending')
            ->assertOk()
            ->assertJsonPath('data.sent.0.firebase_uuid', $response->json('data.teams.0.team_uuid'));

        Sanctum::actingAs($rep);

        $this->getJson('/api/v1/team/pending')
            ->assertOk()
            ->assertJsonPath('data.received.0.manager_firebase_uid', $manager->firebase_uid);
    }

    public function test_user_can_view_pending_team_requests(): void
    {
        $manager = $this->authAsRole('manager_of_reps');
        $rep = $this->createUserWithRole('rep');

        $response = $this->postJson('/api/v1/team', [
            'target_uid' => $rep->firebase_uid,
        ])->assertCreated();

        foreach (['accepted', 'rejected', 'cancelled'] as $status) {
            DB::table('team_requests')->insert([
                'firebase_uuid' => (string) \Str::uuid(),
                'manager_firebase_uid' => $manager->firebase_uid,
                'target_user_firebase_uid' => $rep->firebase_uid,
                'manager_type_role_id' => $manager->roles()->value('roles.id'),
                'status_id' => Status::idByName($status),
                'created_by' => $manager->firebase_uid,
                'updated_by' => $manager->firebase_uid,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->getJson('/api/v1/team/requests')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.request_uuid', $response->json('data.teams.0.team_uuid'));

        Sanctum::actingAs($rep);

        $this->getJson('/api/v1/team/requests')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.request_uuid', $response->json('data.teams.0.team_uuid'))
            ->assertJsonPath('data.0.firebase_uid', $manager->firebase_uid);
    }

    public function test_manager_can_get_and_destroy_team_member(): void
    {
        $manager = $this->authAsRole('manager_of_reps');
        $rep = $this->createUserWithRole('rep');

        DB::table('manager_team_members')->insert([
            'manager_firebase_uid' => $manager->firebase_uid,
            'member_firebase_uid' => $rep->firebase_uid,
            'manager_type_role_id' => $manager->roles()->value('roles.id'),
            'active' => true,
            'joined_at' => now(),
            'created_by' => $manager->firebase_uid,
            'updated_by' => $manager->firebase_uid,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/v1/team')
            ->assertOk()
            ->assertJsonPath('data.0.id', $rep->firebase_uid);

        $this->deleteJson('/api/v1/team/'.$rep->firebase_uid)
            ->assertOk()
            ->assertJsonPath('message', 'Team member removed.');

        $this->assertDatabaseHas('manager_team_members', [
            'manager_firebase_uid' => $manager->firebase_uid,
            'member_firebase_uid' => $rep->firebase_uid,
            'active' => false,
        ]);
    }

    public function test_member_can_leave_team(): void
    {
        $manager = $this->createUserWithRole('manager_of_reps');
        $rep = $this->authAsRole('rep');

        DB::table('manager_team_members')->insert([
            'manager_firebase_uid' => $manager->firebase_uid,
            'member_firebase_uid' => $rep->firebase_uid,
            'manager_type_role_id' => $manager->roles()->value('roles.id'),
            'active' => true,
            'joined_at' => now(),
            'created_by' => $manager->firebase_uid,
            'updated_by' => $manager->firebase_uid,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->deleteJson('/api/v1/team/leave/'.$manager->firebase_uid)
            ->assertOk()
            ->assertJsonPath('message', 'You left the team.');

        $this->assertDatabaseHas('manager_team_members', [
            'manager_firebase_uid' => $manager->firebase_uid,
            'member_firebase_uid' => $rep->firebase_uid,
            'active' => false,
            'updated_by' => $rep->firebase_uid,
        ]);
    }

    public function test_member_cannot_leave_team_they_do_not_belong_to(): void
    {
        $manager = $this->createUserWithRole('manager_of_reps');
        $this->authAsRole('rep');

        $this->deleteJson('/api/v1/team/leave/'.$manager->firebase_uid)
            ->assertNotFound()
            ->assertJsonPath('message', 'Team membership not found.');
    }
}
