<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

test('register creates a local user with the requested role', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'first_name' => 'Azam',
        'last_name' => 'Tester',
        'email' => 'newuser@example.com',
        'password' => 'SecretPass123!',
        'password_confirmation' => 'SecretPass123!',
        'role' => 'rep',
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.email', 'newuser@example.com')
        ->assertJsonPath('data.role.name', 'rep');

    $user = User::where('email', 'newuser@example.com')->firstOrFail();

    expect($user->first_name)->toBe('Azam')
        ->and(Hash::check('SecretPass123!', $user->password))->toBeTrue()
        ->and($user->roles()->pluck('name')->all())->toBe(['rep']);

    $this->assertDatabaseHas('sales_rep_users', [
        'user_firebase_uid' => $user->firebase_uid,
        'ratings_count' => 0,
    ]);
});

test('register rejects an email that already exists locally', function () {
    User::factory()->create([
        'email' => 'existing@example.com',
    ]);

    $this->postJson('/api/v1/auth/register', [
        'first_name' => 'Azam',
        'email' => 'existing@example.com',
        'password' => 'SecretPass123!',
        'password_confirmation' => 'SecretPass123!',
        'role' => 'rater',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['errors' => ['email']]);
});
