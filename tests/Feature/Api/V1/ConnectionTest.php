<?php

namespace Tests\Feature\Api\V1;

use App\Models\Connection;
use App\Models\ConnectionRequest;
use App\Models\Status;
use Illuminate\Support\Str;

class ConnectionTest extends V1TestCase
{
    public function test_user_can_send_connection_request(): void
    {
        $sender = $this->authAsRole('rater');
        $recipient = $this->createUserWithRole('rep');

        $response = $this->postJson('/api/v1/connections/request', [
            'target_uid' => $recipient->firebase_uid,
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['request_uuid']]);

        $this->assertDatabaseHas('connection_requests', [
            'requester_firebase_uid' => $sender->firebase_uid,
            'target_user_firebase_uid' => $recipient->firebase_uid,
        ]);

        $this->assertDatabaseHas('notifications', [
            'from_user_firebase_uid' => $sender->firebase_uid,
            'to_user_firebase_uid' => $recipient->firebase_uid,
        ]);
    }

    public function test_user_cannot_send_duplicate_connection_request(): void
    {
        $sender = $this->authAsRole('rater');
        $recipient = $this->createUserWithRole('rep');

        $this->postJson('/api/v1/connections/request', [
            'target_uid' => $recipient->firebase_uid,
        ])->assertCreated();

        $this->postJson('/api/v1/connections/request', [
            'target_uid' => $recipient->firebase_uid,
        ])
            ->assertConflict()
            ->assertJsonPath('message', 'Connection request already pending.');
    }

    public function test_recipient_can_accept_connection_request(): void
    {
        $sender = $this->createUserWithRole('rater');
        $recipient = $this->authAsRole('rep');
        $requestUuid = (string) Str::uuid();
        $pendingStatusId = Status::idByName('pending');
        $acceptedStatusId = Status::idByName('accepted');

        $request = ConnectionRequest::create([
            'firebase_uuid' => $requestUuid,
            'requester_firebase_uid' => $sender->firebase_uid,
            'target_user_firebase_uid' => $recipient->firebase_uid,
            'status_id' => $pendingStatusId,
            'created_by' => $sender->firebase_uid,
            'updated_by' => $sender->firebase_uid,
        ]);

        $this->postJson('/api/v1/connection-requests/accept', [
            'request_uuid' => $requestUuid,
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('connection_requests', [
            'id' => $request->id,
            'status_id' => $acceptedStatusId,
        ]);

        $this->assertDatabaseHas('connections', [
            'user_a_firebase_uid' => $sender->firebase_uid,
            'user_b_firebase_uid' => $recipient->firebase_uid,
            'connected_by_uid' => $recipient->firebase_uid,
        ]);
    }

    public function test_sender_cannot_accept_request_not_addressed_to_them(): void
    {
        $sender = $this->authAsRole('rater');
        $recipient = $this->createUserWithRole('rep');
        $pendingStatusId = Status::idByName('pending');
        $requestUuid = (string) Str::uuid();

        ConnectionRequest::create([
            'firebase_uuid' => $requestUuid,
            'requester_firebase_uid' => $sender->firebase_uid,
            'target_user_firebase_uid' => $recipient->firebase_uid,
            'status_id' => $pendingStatusId,
            'created_by' => $sender->firebase_uid,
            'updated_by' => $sender->firebase_uid,
        ]);

        $this->postJson('/api/v1/connection-requests/accept', [
            'request_uuid' => $requestUuid,
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'You do not have permission to perform this action.');
    }

    public function test_connection_requests_endpoint_only_returns_pending_requests(): void
    {
        $sender = $this->authAsRole('rater');
        $recipient = $this->createUserWithRole('rep');

        foreach (['pending', 'accepted', 'rejected', 'cancelled'] as $status) {
            ConnectionRequest::create([
                'firebase_uuid' => (string) Str::uuid(),
                'requester_firebase_uid' => $sender->firebase_uid,
                'target_user_firebase_uid' => $recipient->firebase_uid,
                'status_id' => Status::idByName($status),
                'created_by' => $sender->firebase_uid,
                'updated_by' => $sender->firebase_uid,
            ]);
        }

        $this->getJson('/api/v1/connections/requests')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.direction', 'sent');

        $this->actingAs($recipient);

        $this->getJson('/api/v1/connections/requests')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.direction', 'received');
    }

    public function test_user_can_remove_connection(): void
    {
        $userA = $this->authAsRole('rater');
        $userB = $this->createUserWithRole('rep');

        $connection = Connection::create([
            'user_a_firebase_uid' => $userA->firebase_uid,
            'user_b_firebase_uid' => $userB->firebase_uid,
            'connected_by_uid' => $userA->firebase_uid,
            'connected_at' => now(),
            'is_active' => true,
            'created_by' => $userA->firebase_uid,
            'updated_by' => $userA->firebase_uid,
        ]);

        $this->deleteJson("/api/v1/connections/{$connection->firebase_uuid}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('connections', [
            'user_a_firebase_uid' => $userA->firebase_uid,
            'user_b_firebase_uid' => $userB->firebase_uid,
            'is_active' => false,
        ]);
    }
}
