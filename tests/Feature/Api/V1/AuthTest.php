<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Support\Facades\Hash;

class AuthTest extends V1TestCase
{
    public function test_user_can_login_with_local_password(): void
    {
        $user = $this->createUserWithRole('rater', [
            'email' => 'test@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'TEST@example.com',
            'password' => 'secret123',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.firebase_uid', $user->firebase_uid)
            ->assertJsonPath('data.email', 'test@example.com')
            ->assertJsonPath('data.role.name', 'rater')
            ->assertJsonPath('data.first_name', $user->first_name)
            ->assertJsonPath('data.token', fn ($v) => is_string($v) && strlen($v) > 0);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->createUserWithRole('rater', [
            'email' => 'test@example.com',
            'password' => Hash::make('correctpass'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpass',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_blocked_user_cannot_login(): void
    {
        $this->createUserWithRole('rater', [
            'email' => 'blocked@example.com',
            'password' => Hash::make('password'),
            'is_blocked' => true,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'blocked@example.com',
            'password' => 'password',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Account blocked.');
    }

    public function test_deleted_user_cannot_login(): void
    {
        $this->createUserWithRole('rater', [
            'email' => 'deleted@example.com',
            'password' => Hash::make('password'),
            'is_deleted' => true,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'deleted@example.com',
            'password' => 'password',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Account deleted.');
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['email', 'password']]);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = $this->authAsRole('rater');

        $this->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(0, $user->tokens()->get());
    }

    public function test_migrated_user_can_set_password(): void
    {
        $user = $this->authAsRole('rater', ['password' => null]);

        $this->postJson('/api/v1/auth/set-password', [
            'password' => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ])->assertOk();

        expect(Hash::check('NewPass123!', $user->fresh()->password))->toBeTrue();
    }

    public function test_user_cannot_set_password_twice(): void
    {
        $this->authAsRole('rater', ['password' => Hash::make('existing-password')]);

        $this->postJson('/api/v1/auth/set-password', [
            'password' => 'AnotherPass123!',
            'password_confirmation' => 'AnotherPass123!',
        ])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Password already set.');
    }

    public function test_user_can_change_password(): void
    {
        $user = $this->authAsRole('rater', ['password' => Hash::make('current-password')]);

        $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'current-password',
            'new_password' => 'NewStr0ng!Pass',
            'new_password_confirmation' => 'NewStr0ng!Pass',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Password changed successfully.');

        expect(Hash::check('NewStr0ng!Pass', $user->fresh()->password))->toBeTrue();
    }

    public function test_change_password_fails_with_wrong_current_password(): void
    {
        $this->authAsRole('rater', ['password' => Hash::make('current-password')]);

        $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'wrong-password',
            'new_password' => 'NewStr0ng!Pass',
            'new_password_confirmation' => 'NewStr0ng!Pass',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.current_password.0', 'The current password is incorrect.');
    }

    public function test_change_password_fails_when_new_password_same_as_current(): void
    {
        $this->authAsRole('rater', ['password' => Hash::make('Str0ng!Pass')]);

        $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'Str0ng!Pass',
            'new_password' => 'Str0ng!Pass',
            'new_password_confirmation' => 'Str0ng!Pass',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.new_password.0', 'The new password must be different from the current password.');
    }

    public function test_change_password_fails_without_confirmation(): void
    {
        $this->authAsRole('rater', ['password' => Hash::make('current-password')]);

        $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'current-password',
            'new_password' => 'NewStr0ng!Pass',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.new_password.0', 'The new password field confirmation does not match.');
    }

    public function test_change_password_fails_for_unauthenticated_user(): void
    {
        $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'current-password',
            'new_password' => 'NewStr0ng!Pass',
            'new_password_confirmation' => 'NewStr0ng!Pass',
        ])
            ->assertUnauthorized();
    }

    public function test_change_password_requires_strong_password(): void
    {
        $this->authAsRole('rater', ['password' => Hash::make('current-password')]);

        $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'current-password',
            'new_password' => 'weak',
            'new_password_confirmation' => 'weak',
        ])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['new_password']]);
    }
}
