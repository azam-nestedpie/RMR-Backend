<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsurePasswordSet;
use App\Http\Middleware\RoleMiddleware;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AuthorizationMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_role_middleware_rejects_unauthenticated_requests(): void
    {
        $response = (new RoleMiddleware)->handle($this->request(), fn () => response()->json(['passed' => true]), 'rater');

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('UNAUTHENTICATED', $response->getData(true)['error']);
    }

    public function test_role_middleware_rejects_wrong_role(): void
    {
        $user = User::factory()->create();
        $this->attachRole($user, 'rep');

        $response = (new RoleMiddleware)->handle($this->request($user), fn () => response()->json(['passed' => true]), 'rater');

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('FORBIDDEN', $response->getData(true)['error']);
    }

    public function test_role_middleware_allows_valid_role(): void
    {
        $user = User::factory()->create();
        $this->attachRole($user, 'rater');

        $response = (new RoleMiddleware)->handle($this->request($user), fn () => response()->json(['passed' => true]), 'rater');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['passed']);
    }

    public function test_permission_middleware_rejects_unauthenticated_requests(): void
    {
        $response = (new CheckPermission)->handle($this->request(), fn () => response()->json(['passed' => true]), 'ratings.submit');

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('UNAUTHENTICATED', $response->getData(true)['error']);
    }

    public function test_permission_middleware_rejects_missing_permission(): void
    {
        $user = User::factory()->create();
        $this->attachRole($user, 'rep');

        $response = (new CheckPermission)->handle($this->request($user), fn () => response()->json(['passed' => true]), 'ratings.submit');

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('FORBIDDEN', $response->getData(true)['error']);
    }

    public function test_permission_middleware_allows_permission(): void
    {
        $user = User::factory()->create();
        $this->attachRole($user, 'rater');

        $response = (new CheckPermission)->handle($this->request($user), fn () => response()->json(['passed' => true]), 'ratings.submit');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['passed']);
    }

    public function test_password_gate_rejects_unauthenticated_requests(): void
    {
        $response = (new EnsurePasswordSet)->handle($this->request(), fn () => response()->json(['passed' => true]));

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_password_gate_rejects_users_without_password(): void
    {
        $user = User::factory()->create(['password' => null]);

        $response = (new EnsurePasswordSet)->handle($this->request($user), fn () => response()->json(['passed' => true]));

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('set_password', $response->getData(true)['requires_action']);
    }

    public function test_password_gate_allows_users_with_password(): void
    {
        $user = User::factory()->create();

        $response = (new EnsurePasswordSet)->handle($this->request($user), fn () => response()->json(['passed' => true]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['passed']);
    }

    private function attachRole(User $user, string $roleName): void
    {
        $roleId = Role::idByName($roleName);
        $user->roles()->attach($roleId, [
            'created_by' => $user->firebase_uid,
            'updated_by' => $user->firebase_uid,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function request(?User $user = null): Request
    {
        $request = Request::create('/api/v1/test', 'GET');
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}
