<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Services\V1\UserService;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;

class UserEndpointsTest extends V1TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_profile_returns_current_user(): void
    {
        $authUser = $this->authAsRole('rater');
        $profile = $this->makeUser(['firebase_uid' => $authUser->firebase_uid, 'email' => $authUser->email]);
        $profile->setRelation('roles', collect([]));

        $service = Mockery::mock(UserService::class);
        $service->shouldReceive('profile')->once()->andReturn($profile);
        $this->instance(UserService::class, $service);

        $this->getJson('/api/v1/users/profile')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.firebase_uid', $authUser->firebase_uid);
    }

    public function test_update_profile_returns_updated_profile(): void
    {
        $authUser = $this->authAsRole('rater');
        $updated = $this->makeUser(['firebase_uid' => $authUser->firebase_uid, 'email' => $authUser->email, 'first_name' => 'Changed']);

        $service = Mockery::mock(UserService::class);
        $service->shouldReceive('updateProfile')
            ->once()
            ->with(Mockery::on(fn (User $user) => $user->firebase_uid === $authUser->firebase_uid), Mockery::subset([
                'first_name' => 'Changed',
                'bio' => 'Bio',
            ]), Mockery::subset([
                'city' => 'Ashgabat',
            ]))
            ->andReturn($updated);
        $this->instance(UserService::class, $service);

        $this->putJson('/api/v1/users/profile', [
            'first_name' => 'Changed',
            'bio' => 'Bio',
            'address' => 'Ashgabat',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Profile updated successfully.');
    }

    public function test_show_returns_not_found_when_user_missing(): void
    {
        $this->authAsRole('rater');

        $service = Mockery::mock(UserService::class);
        $service->shouldReceive('show')->once()->with('missing-uid', 'rater')->andReturn(null);
        $this->instance(UserService::class, $service);

        $this->getJson('/api/v1/users/missing-uid')
            ->assertStatus(404)
            ->assertJsonPath('message', 'No User Found According To Your Search');
    }

    public function test_show_returns_forbidden_for_blocked_users(): void
    {
        $this->authAsRole('rater');

        $blocked = $this->makeUser([
            'firebase_uid' => 'blocked-uid',
            'is_blocked' => true,
        ]);

        $service = Mockery::mock(UserService::class);
        $service->shouldReceive('show')->once()->with('blocked-uid', 'rater')->andReturn($blocked);
        $this->instance(UserService::class, $service);

        $this->getJson('/api/v1/users/blocked-uid')
            ->assertStatus(403)
            ->assertJsonPath('message', 'This account is unavailable.');
    }

    public function test_show_returns_user_resource(): void
    {
        $this->authAsRole('rater');
        $user = $this->makeUser(['firebase_uid' => 'shown-uid', 'email' => 'shown@example.com']);
        $user->setRelation('roles', collect([]));

        $service = Mockery::mock(UserService::class);
        $service->shouldReceive('show')->once()->with('shown-uid', 'rater')->andReturn($user);
        $this->instance(UserService::class, $service);

        $this->getJson('/api/v1/users/shown-uid')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.firebase_uid', 'shown-uid');
    }

    public function test_search_returns_matching_users(): void
    {
        $authUser = $this->authAsRole('rater');
        $result = $this->makeUser(['firebase_uid' => 'search-uid', 'email' => 'search@example.com']);

        $currentRole = $authUser->loadMissing('roles')->roles->first()?->name;

        $service = Mockery::mock(UserService::class);
        $service->shouldReceive('search')
            ->once()
            ->with(Mockery::subset(['first_name' => 'john', 'role' => 'rep']), $authUser->firebase_uid, $currentRole, Mockery::any())
            ->andReturn(new LengthAwarePaginator([$result], 1, 20, 1));
        $this->instance(UserService::class, $service);

        $this->postJson('/api/v1/users/search', ['first_name' => 'john', 'role' => 'rep'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.firebase_uid', 'search-uid');
    }

    public function test_search_returns_not_found_when_no_users_match(): void
    {
        $authUser = $this->authAsRole('rater');
        $currentRole = $authUser->loadMissing('roles')->roles->first()?->name;

        $service = Mockery::mock(UserService::class);
        $service->shouldReceive('search')
            ->once()
            ->with(Mockery::subset(['first_name' => 'missing']), $authUser->firebase_uid, $currentRole, Mockery::any())
            ->andReturn(new LengthAwarePaginator([], 0, 20, 1));
        $this->instance(UserService::class, $service);

        $this->postJson('/api/v1/users/search', ['first_name' => 'missing'])
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No User Found According To Your Search');
    }

    public function test_search_returns_no_user_found_for_unknown_fields(): void
    {
        $this->authAsRole('rater');

        $this->postJson('/api/v1/users/search', ['unknown_field' => 'value'])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No User Found According To Your Search')
            ->assertJsonPath('errors.search.0', 'No User Found According To Your Search');
    }

    public function test_search_returns_bad_request_for_invalid_json(): void
    {
        $this->authAsRole('rater');

        $this->call('POST', '/api/v1/users/search', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], '{"first_name":')
            ->assertBadRequest()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid JSON format.');
    }

    public function test_my_connections_returns_collection(): void
    {
        $authUser = $this->authAsRole('rater');
        $connected = $this->makeUser(['firebase_uid' => 'connected-uid', 'email' => 'connected@example.com']);

        $service = Mockery::mock(UserService::class);
        $service->shouldReceive('myConnections')->once()->with($authUser->firebase_uid)->andReturn(collect([$connected]));
        $this->instance(UserService::class, $service);

        $this->getJson('/api/v1/users/me/connections')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.firebase_uid', 'connected-uid');
    }

    public function test_destroy_marks_user_deleted(): void
    {
        $authUser = $this->authAsRole('rater');

        $service = Mockery::mock(UserService::class);
        $service->shouldReceive('destroy')->once()->with(Mockery::on(fn (User $user) => $user->firebase_uid === $authUser->firebase_uid));
        $this->instance(UserService::class, $service);

        $this->deleteJson('/api/v1/users/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Account deleted successfully.');
    }
}
