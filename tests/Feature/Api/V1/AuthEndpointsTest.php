<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Services\V1\AuthService;
use Mockery;

class AuthEndpointsTest extends V1TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_login_returns_user_with_token(): void
    {
        $user = $this->makeUser([
            'firebase_uid' => 'uid-login',
            'email' => 'john@example.com',
        ]);

        $service = Mockery::mock(AuthService::class);
        $service->shouldReceive('login')
            ->once()
            ->with('john@example.com', 'secret')
            ->andReturn(['token' => 'token-1', 'user' => $user]);
        $this->instance(AuthService::class, $service);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'John@Example.com',
            'password' => 'secret',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonPath('data.token', 'token-1')
            ->assertJsonPath('data.firebase_uid', 'uid-login')
            ->assertJsonPath('data.email', 'john@example.com');
    }

    public function test_login_validates_payload(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'not-an-email',
        ])->assertStatus(422);
    }

    public function test_register_returns_created_payload(): void
    {
        $user = $this->makeUser([
            'firebase_uid' => 'uid-register',
            'email' => 'new@example.com',
        ]);

        $service = Mockery::mock(AuthService::class);
        $service->shouldReceive('register')
            ->once()
            ->with(Mockery::on(fn (array $data) => $data['email'] === 'new@example.com' && $data['role'] === 'rep'))
            ->andReturn(['token' => 'token-2', 'user' => $user]);
        $this->instance(AuthService::class, $service);

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'New',
            'last_name' => 'User',
            'email' => 'New@Example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'rep',
            'fcm_token' => 'fcm-1',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Account created successfully.')
            ->assertJsonPath('data.firebase_uid', 'uid-register')
            ->assertJsonPath('data.token', 'token-2');
    }

    public function test_forgot_password_placeholder_is_exposed(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'user@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Reset link sent to your email.');
    }

    public function test_reset_password_placeholder_is_exposed(): void
    {
        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Password has been reset.');
    }

    public function test_me_returns_profile_resource(): void
    {
        $authUser = $this->authAsRole('rater');
        $profile = $this->makeUser([
            'firebase_uid' => $authUser->firebase_uid,
            'email' => $authUser->email,
        ]);
        $profile->setRelation('roles', collect([]));

        $service = Mockery::mock(AuthService::class);
        $service->shouldReceive('me')->once()->with(Mockery::on(fn (User $user) => $user->firebase_uid === $authUser->firebase_uid))->andReturn($profile);
        $this->instance(AuthService::class, $service);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.firebase_uid', $authUser->firebase_uid);
    }

    public function test_logout_returns_success(): void
    {
        $authUser = $this->authAsRole('rater');

        $service = Mockery::mock(AuthService::class);
        $service->shouldReceive('logout')->once()->with(Mockery::on(fn (User $user) => $user->firebase_uid === $authUser->firebase_uid));
        $this->instance(AuthService::class, $service);

        $this->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Logged out successfully.');
    }

    public function test_set_password_uses_validated_payload(): void
    {
        $authUser = $this->authAsRole('rater', ['password' => null]);
        $updated = $this->makeUser([
            'firebase_uid' => $authUser->firebase_uid,
            'email' => $authUser->email,
        ]);

        $service = Mockery::mock(AuthService::class);
        $service->shouldReceive('setPassword')
            ->once()
            ->with(Mockery::on(fn (User $user) => $user->firebase_uid === $authUser->firebase_uid), 'password123')
            ->andReturn($updated);
        $this->instance(AuthService::class, $service);

        $this->postJson('/api/v1/auth/set-password', [
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Password set successfully.');
    }

    public function test_update_profile_uses_validated_payload(): void
    {
        $authUser = $this->authAsRole('rater');
        $updated = $this->makeUser([
            'firebase_uid' => $authUser->firebase_uid,
            'email' => $authUser->email,
            'first_name' => 'Changed',
        ]);

        $service = Mockery::mock(AuthService::class);
        $service->shouldReceive('updateProfile')
            ->once()
            ->with(Mockery::on(fn (User $user) => $user->firebase_uid === $authUser->firebase_uid), Mockery::subset([
                'first_name' => 'Changed',
                'bio' => 'Bio',
            ]))
            ->andReturn($updated);
        $this->instance(AuthService::class, $service);

        $this->putJson('/api/v1/auth/profile', [
            'first_name' => 'Changed',
            'bio' => 'Bio',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Profile updated successfully.');
    }
}
