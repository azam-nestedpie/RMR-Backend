<?php

namespace Tests\Unit\Repositories;

use App\Models\Connection;
use App\Models\Role;
use App\Models\User;
use App\Repositories\UserRepository;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private UserRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->repo = new UserRepository;
    }

    public function test_find_by_firebase_uid_returns_user(): void
    {
        $user = User::factory()->create();

        $found = $this->repo->findByFirebaseUid($user->firebase_uid);

        $this->assertNotNull($found);
        $this->assertSame($user->firebase_uid, $found->firebase_uid);
    }

    public function test_find_by_firebase_uid_returns_null_for_missing(): void
    {
        $this->assertNull($this->repo->findByFirebaseUid('nonexistent_uid'));
    }

    public function test_find_by_email_is_case_insensitive(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $found = $this->repo->findByEmail('TEST@EXAMPLE.COM');

        $this->assertNotNull($found);
        $this->assertSame('test@example.com', $found->email);
    }

    public function test_search_returns_matching_users(): void
    {
        $me = User::factory()->create(['first_name' => 'Current']);
        $john = User::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
        $jane = User::factory()->create(['first_name' => 'Jane', 'last_name' => 'Smith']);
        $other = User::factory()->create(['first_name' => 'Alice']);

        $repRoleId = Role::idByName('rep');
        $john->roles()->attach($repRoleId, ['created_at' => now(), 'updated_at' => now()]);
        $jane->roles()->attach($repRoleId, ['created_at' => now(), 'updated_at' => now()]);

        $meRole = $me->loadMissing('roles')->roles->first()?->name;

        $results = $this->repo->search([
            'first_name' => 'jo',
            'role' => 'rep',
        ], $me->firebase_uid, $meRole);

        $uids = collect($results->items())->pluck('firebase_uid')->all();

        $this->assertContains($john->firebase_uid, $uids);
        $this->assertNotContains($other->firebase_uid, $uids);
    }

    public function test_connected_users_returns_correct_users(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userC = User::factory()->create();

        Connection::create([
            'user_a_firebase_uid' => $userA->firebase_uid,
            'user_b_firebase_uid' => $userB->firebase_uid,
            'connected_by_uid' => $userA->firebase_uid,
            'connected_at' => now(),
            'is_active' => true,
            'created_by' => $userA->firebase_uid,
            'updated_by' => $userA->firebase_uid,
        ]);

        $connected = $this->repo->connectedUsers($userA->firebase_uid);

        $uids = $connected->pluck('firebase_uid')->all();

        $this->assertContains($userB->firebase_uid, $uids);
        $this->assertNotContains($userC->firebase_uid, $uids);
    }

    public function test_update_returns_true_on_success(): void
    {
        $user = User::factory()->create(['first_name' => 'OldName']);

        $result = $this->repo->update($user, ['first_name' => 'NewName']);

        $this->assertTrue($result);
        $this->assertSame('NewName', $user->fresh()->first_name);
    }
}
