<?php

namespace Tests\Feature\Api\V1;

use App\Models\Connection;
use App\Models\ConnectionRequest;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use App\Services\V1\ConnectionService;
use Mockery;

class ConnectionEndpointsTest extends V1TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_send_request_returns_created_payload(): void
    {
        $authUser = $this->authAsRole(Role::RATER);
        $target = $this->createUserWithRole(Role::REPRESENTATIVE);

        $service = Mockery::mock(ConnectionService::class);
        $service->shouldReceive('sendRequest')
            ->once()
            ->with(Mockery::on(fn (User $user) => $user->firebase_uid === $authUser->firebase_uid), $target->firebase_uid)
            ->andReturn(['request_uuid' => 'request-1']);
        $this->instance(ConnectionService::class, $service);

        $this->postJson('/api/v1/connections/request', [
            'target_uid' => $target->firebase_uid,
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Connection request sent.')
            ->assertJsonPath('data.request_uuid', 'request-1');
    }

    public function test_manager_cannot_send_team_request_through_connection_endpoint(): void
    {
        $this->authAsRole(Role::MANAGER_OF_REPRESENTATIVES);
        $rep = $this->createUserWithRole(Role::REPRESENTATIVE);

        $this->postJson('/api/v1/connections/request', [
            'target_uid' => $rep->firebase_uid,
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'You do not have permission to perform this action.');
    }

    public function test_send_request_requires_target_uid_or_target_uids(): void
    {
        $this->authAsRole(Role::REPRESENTATIVE);

        $this->postJson('/api/v1/connections/request', [])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['target_uids']);
    }

    public function test_send_request_rejects_duplicate_targets(): void
    {
        $this->authAsRole(Role::REPRESENTATIVE);
        $rater = $this->createUserWithRole(Role::RATER);

        $this->postJson('/api/v1/connections/request', [
            'target_uids' => [$rater->firebase_uid, $rater->firebase_uid],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['target_uids.0', 'target_uids.1']);
    }

    public function test_accept_request_returns_success(): void
    {
        $authUser = $this->authAsRole(Role::RATER);

        $service = Mockery::mock(ConnectionService::class);
        $connectionRequest = new ConnectionRequest([
            'firebase_uuid' => 'request-1',
            'requester_firebase_uid' => 'requester-1',
            'target_user_firebase_uid' => $authUser->firebase_uid,
        ]);
        $service->shouldReceive('findRequestForAuthorization')->once()->with('request-1')->andReturn($connectionRequest);
        $service->shouldReceive('acceptRequest')
            ->once()
            ->with(Mockery::on(fn (User $user) => $user->firebase_uid === $authUser->firebase_uid), 'request-1');
        $this->instance(ConnectionService::class, $service);

        $this->postJson('/api/v1/connection-requests/accept', [
            'request_uuid' => 'request-1',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Connection request accepted.');
    }

    public function test_reject_request_returns_success(): void
    {
        $authUser = $this->authAsRole(Role::RATER);

        $service = Mockery::mock(ConnectionService::class);
        $connectionRequest = new ConnectionRequest([
            'firebase_uuid' => 'request-1',
            'requester_firebase_uid' => 'requester-1',
            'target_user_firebase_uid' => $authUser->firebase_uid,
        ]);
        $service->shouldReceive('findRequestForAuthorization')->once()->with('request-1')->andReturn($connectionRequest);
        $service->shouldReceive('rejectRequest')
            ->once()
            ->with(Mockery::on(fn (User $user) => $user->firebase_uid === $authUser->firebase_uid), 'request-1');
        $this->instance(ConnectionService::class, $service);

        $this->postJson('/api/v1/connection-requests/reject', [
            'request_uuid' => 'request-1',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Connection request rejected.');
    }

    public function test_index_returns_connections_collection(): void
    {
        $authUser = $this->authAsRole(Role::RATER);
        $role = Role::find(Role::RATER);
        $connectedUser = $this->makeUser(['firebase_uid' => 'connected-1', 'email' => 'connected@example.com']);
        $connectedUser->setRelation('roles', collect([$role]));

        $service = Mockery::mock(ConnectionService::class);
        $service->shouldReceive('connectedUserList')->once()->with(Mockery::on(fn (User $user) => $user->firebase_uid === $authUser->firebase_uid))->andReturn(collect([$connectedUser]));
        $this->instance(ConnectionService::class, $service);

        $this->getJson('/api/v1/connections')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.firebase_uid', 'connected-1');
    }

    public function test_pending_requests_returns_collection(): void
    {
        $authUser = $this->authAsRole(Role::RATER);
        $request = new ConnectionRequest([
            'firebase_uuid' => 'pending-request-1',
            'created_at' => now(),
        ]);
        $request->setRelation('requester', $this->makeUser(['firebase_uid' => 'pending-1', 'email' => 'pending@example.com']));
        $request->setRelation('target', $this->makeUser(['firebase_uid' => $authUser->firebase_uid, 'email' => $authUser->email]));
        $request->setRelation('status', new Status(['name' => 'pending']));

        $service = Mockery::mock(ConnectionService::class);
        $service->shouldReceive('pendingRequests')->once()->with($authUser->firebase_uid)->andReturn(collect([$request]));
        $this->instance(ConnectionService::class, $service);

        $this->getJson('/api/v1/connections/pending')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.requester.firebase_uid', 'pending-1');
    }

    public function test_requests_returns_sent_and_received_collections(): void
    {
        $authUser = $this->authAsRole(Role::RATER);
        $repRole = Role::find(Role::REPRESENTATIVE);

        $requester = $this->makeUser(['firebase_uid' => 'rep-1', 'email' => 'rep1@test.com']);
        $requester->setRelation('roles', collect([$repRole]));

        $target = $this->makeUser(['firebase_uid' => 'rep-2', 'email' => 'rep2@test.com']);
        $target->setRelation('roles', collect([$repRole]));

        $received = new ConnectionRequest([
            'firebase_uuid' => 'received-request-1',
            'created_at' => now(),
        ]);
        $received->setRelation('requester', $requester);
        $received->setRelation('target', $this->makeUser(['firebase_uid' => $authUser->firebase_uid]));
        $received->setRelation('status', new Status(['name' => 'pending']));

        $sent = new ConnectionRequest([
            'firebase_uuid' => 'sent-request-1',
            'created_at' => now(),
        ]);
        $sent->setRelation('requester', $this->makeUser(['firebase_uid' => $authUser->firebase_uid]));
        $sent->setRelation('target', $target);
        $sent->setRelation('status', new Status(['name' => 'accepted']));

        $service = Mockery::mock(ConnectionService::class);
        $service->shouldReceive('requests')->once()->with(Mockery::on(fn (User $user) => $user->firebase_uid === $authUser->firebase_uid))->andReturn([
            'received' => collect([$received]),
            'sent' => collect([$sent]),
        ]);
        $this->instance(ConnectionService::class, $service);

        $this->getJson('/api/v1/connections/requests')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'requests.received')
            ->assertJsonCount(1, 'requests.sent')
            ->assertJsonPath('requests.received.0.firebase_uid', 'rep-1')
            ->assertJsonPath('requests.received.0.request_uuid', 'received-request-1')
            ->assertJsonPath('requests.received.0.status', 'pending')
            ->assertJsonPath('requests.sent.0.firebase_uid', 'rep-2')
            ->assertJsonPath('requests.sent.0.request_uuid', 'sent-request-1')
            ->assertJsonPath('requests.sent.0.status', 'accepted');
    }

    public function test_destroy_returns_success(): void
    {
        $authUser = $this->authAsRole(Role::RATER);

        $service = Mockery::mock(ConnectionService::class);
        $connection = new Connection([
            'firebase_uuid' => 'connection-1',
            'user_a_firebase_uid' => $authUser->firebase_uid,
            'user_b_firebase_uid' => 'user-b',
            'is_active' => true,
        ]);
        $service->shouldReceive('findConnectionForAuthorization')->once()->with('connection-1')->andReturn($connection);
        $service->shouldReceive('removeConnection')
            ->once()
            ->with(Mockery::on(fn (User $user) => $user->firebase_uid === $authUser->firebase_uid), 'connection-1');
        $this->instance(ConnectionService::class, $service);

        $this->deleteJson('/api/v1/connections/connection-1')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Connection removed.');
    }

    public function test_send_bulk_returns_created_payload(): void
    {
        $authUser = $this->authAsRole(Role::RATER);
        $firstTarget = $this->createUserWithRole(Role::REPRESENTATIVE);
        $secondTarget = $this->createUserWithRole(Role::REPRESENTATIVE);

        $service = Mockery::mock(ConnectionService::class);
        $service->shouldReceive('sendBulkRequest')
            ->once()
            ->with(
                Mockery::on(fn (User $user) => $user->firebase_uid === $authUser->firebase_uid),
                Mockery::on(fn (array $uids) => in_array($firstTarget->firebase_uid, $uids) && in_array($secondTarget->firebase_uid, $uids))
            )
            ->andReturn(['request_uuids' => ['uuid-1', 'uuid-2']]);
        $this->instance(ConnectionService::class, $service);

        $this->postJson('/api/v1/connections/request/bulk', [
            'target_uids' => [$firstTarget->firebase_uid, $secondTarget->firebase_uid],
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Connection requests sent.')
            ->assertJsonPath('data.request_uuids', ['uuid-1', 'uuid-2']);
    }

    public function test_send_bulk_requires_target_uids(): void
    {
        $this->authAsRole(Role::REPRESENTATIVE);

        $this->postJson('/api/v1/connections/request/bulk', [])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['target_uids']);
    }
}
