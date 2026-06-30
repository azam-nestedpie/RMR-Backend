<?php

namespace Tests\Feature\Api\V1;

use App\Models\Role;
use App\Models\User;
use App\Notifications\ResetPassword as ResetPasswordNotification;
use App\Services\V1\AuthService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
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
            ->with(Mockery::subset(['email' => 'john@example.com', 'password' => 'secret']))
            ->andReturn(['token' => 'token-1', 'token_expires_at' => now()->addDays(30), 'user' => $user]);
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
            ->with(Mockery::on(fn (array $data) => $data['email'] === 'new@example.com' && $data['role'] === Role::REPRESENTATIVE))
            ->andReturn(['token' => 'token-2', 'token_expires_at' => now()->addDays(30), 'user' => $user]);
        $this->instance(AuthService::class, $service);

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'New',
            'last_name' => 'User',
            'email' => 'New@Example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => Role::REPRESENTATIVE,
            'fcm_token' => 'fcm-1',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Account created successfully.')
            ->assertJsonPath('data.firebase_uid', 'uid-register')
            ->assertJsonPath('data.token', 'token-2');
    }

    public function test_forgot_password_sends_reset_link(): void
    {
        Notification::fake();

        $user = $this->createUserWithRole();

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $user->email,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Reset link sent to your email.');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_forgot_password_validates_email_exists(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nonexistent@example.com',
        ])->assertStatus(422);
    }

    public function test_forgot_password_validates_email_format(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'not-an-email',
        ])->assertStatus(422);
    }

    public function test_reset_password_with_valid_token(): void
    {
        $user = $this->createUserWithRole(Role::RATER, ['password' => bcrypt('oldpassword')]);

        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Password has been reset.');

        $user->refresh();
        $this->assertTrue(password_verify('newpassword123', $user->password));
    }

    public function test_reset_password_fails_with_invalid_token(): void
    {
        $user = $this->createUserWithRole(Role::RATER, ['password' => bcrypt('oldpassword')]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => 'invalid-token',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])
            ->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_reset_password_validates_payload(): void
    {
        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'not-an-email',
        ])->assertStatus(422);
    }

    public function test_logout_returns_success(): void
    {
        $authUser = $this->authAsRole(Role::RATER);

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
        $authUser = $this->authAsRole(Role::RATER, ['password' => null]);
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
}
