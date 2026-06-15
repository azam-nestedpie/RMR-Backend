<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

abstract class V1TestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    protected function createUserWithRole(string $role = 'rater', array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'password' => bcrypt('password'),
            'is_blocked' => false,
            'is_deleted' => false,
        ], $overrides));

        $roleId = DB::table('roles')->where('name', $role)->value('id');
        $user->roles()->attach($roleId, [
            'created_by' => $user->firebase_uid,
            'updated_by' => $user->firebase_uid,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user->fresh()->load('roles');
    }

    protected function authAsRole(string $role = 'rater', array $overrides = []): User
    {
        $user = $this->createUserWithRole($role, $overrides);
        Sanctum::actingAs($user);

        return $user;
    }

    protected function assignIndustry(User $user, string $industryName = 'Marketing'): void
    {
        $industryId = DB::table('industries')->where('name', $industryName)->value('id');
        DB::table('user_industries')->insert([
            'user_firebase_uid' => $user->firebase_uid,
            'industry_id' => $industryId,
            'is_primary' => true,
            'created_by' => $user->firebase_uid,
            'updated_by' => $user->firebase_uid,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function makeUser(array $attributes = []): User
    {
        return new User(array_merge([
            'firebase_uid' => 'uid_'.uniqid(),
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'is_blocked' => false,
            'is_deleted' => false,
        ], $attributes));
    }
}
