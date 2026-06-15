<?php

namespace App\Repositories;

use App\Models\Connection;
use App\Models\ConnectionRequest;
use App\Models\Status;
use App\Repositories\Contracts\ConnectionRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ConnectionRepository implements ConnectionRepositoryInterface
{
    public function existsActiveBetween(string $userAUid, string $userBUid): bool
    {
        return Connection::between($userAUid, $userBUid)->active()->exists();
    }

    public function findConnectionBetween(string $userAUid, string $userBUid): ?Connection
    {
        return Connection::between($userAUid, $userBUid)->first();
    }

    public function activeConnectionsForUser(string $firebaseUid): Collection
    {
        return Connection::forUser($firebaseUid)
            ->active()
            ->with(['userA.roles', 'userA.address', 'userA.industries', 'userB.roles', 'userB.address', 'userB.industries'])
            ->get();
    }

    public function activeConnectionsForTeam(array $teamMemberUids): Collection
    {
        return Connection::query()
            ->active()
            ->forTeamMembers($teamMemberUids)
            ->with(['userA.roles', 'userA.address', 'userA.industries', 'userB.roles', 'userB.address', 'userB.industries'])
            ->orderByDesc('connected_at')
            ->get();
    }

    public function pendingRequestsForUser(string $firebaseUid): Collection
    {
        $pendingStatusId = Status::idByName('pending');

        return ConnectionRequest::forTarget($firebaseUid)
            ->when($pendingStatusId, fn ($query) => $query->where('status_id', $pendingStatusId))
            ->with(['requester.roles', 'target.roles', 'manager.roles', 'behalfUser.roles', 'status'])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @return array{received: Collection, sent: Collection}
     */
    public function requestsForUser(string $firebaseUid, ?string $role = null): array
    {
        $targetRole = match ($role) {
            'rater' => 'rep',
            'rep' => 'rater',
            default => null,
        };

        $relations = ['requester.roles', 'target.roles', 'manager.roles', 'behalfUser.roles', 'status'];
        $pendingStatusId = Status::idByName('pending');

        return [
            'received' => ConnectionRequest::query()
                ->forTarget($firebaseUid)
                ->when($pendingStatusId, fn ($query) => $query->where('status_id', $pendingStatusId))
                ->when($targetRole, fn ($query) => $query->whereHas('requester', fn ($q) => $q->whereHas('roles', fn ($rq) => $rq->where('name', $targetRole))))
                ->with($relations)
                ->orderByDesc('created_at')
                ->get(),
            'sent' => ConnectionRequest::query()
                ->forRequester($firebaseUid)
                ->when($pendingStatusId, fn ($query) => $query->where('status_id', $pendingStatusId))
                ->when($targetRole, fn ($query) => $query->whereHas('target', fn ($q) => $q->whereHas('roles', fn ($rq) => $rq->where('name', $targetRole))))
                ->with($relations)
                ->orderByDesc('created_at')
                ->get(),
        ];
    }

    public function findPendingRequestBetweenParticipants(string $userAUid, string $userBUid): ?ConnectionRequest
    {
        $pendingStatusId = Status::idByName('pending');

        return ConnectionRequest::betweenParticipants($userAUid, $userBUid)
            ->when($pendingStatusId, fn ($query) => $query->where('status_id', $pendingStatusId))
            ->first();
    }

    public function findPendingRequestByUuid(string $requestUuid, string $targetUid): ?ConnectionRequest
    {
        $pendingStatusId = Status::idByName('pending');

        return ConnectionRequest::where('firebase_uuid', $requestUuid)
            ->where('target_user_firebase_uid', $targetUid)
            ->when($pendingStatusId, fn ($query) => $query->where('status_id', $pendingStatusId))
            ->with(['requester.roles', 'target.roles', 'manager.roles', 'behalfUser.roles', 'status'])
            ->first();
    }

    public function findRequestByUuid(string $requestUuid): ?ConnectionRequest
    {
        return ConnectionRequest::query()
            ->where('firebase_uuid', $requestUuid)
            ->with(['requester.roles', 'target.roles', 'manager.roles', 'behalfUser.roles', 'status'])
            ->first();
    }

    public function createRequest(array $attributes): ConnectionRequest
    {
        return ConnectionRequest::create($attributes);
    }

    public function acceptRequest(ConnectionRequest $request, string $updatedByUid, int $acceptedStatusId): Connection
    {
        $request->update([
            'status_id' => $acceptedStatusId,
            'updated_by' => $updatedByUid,
        ]);
        $uid = $this->generateUniqueUuid();

        return Connection::create([
            'firebase_uuid' => $uid,
            'user_a_firebase_uid' => $request->requester_firebase_uid,
            'user_b_firebase_uid' => $request->target_user_firebase_uid,
            'connected_by_uid' => $updatedByUid,
            'source_request_id' => $request->id,
            'connected_at' => now(),
            'is_active' => true,
            'created_by' => $updatedByUid,
            'updated_by' => $updatedByUid,
        ]);
    }

    public function rejectRequest(ConnectionRequest $request, string $updatedByUid, int $rejectedStatusId): ConnectionRequest
    {
        $request->update([
            'status_id' => $rejectedStatusId,
            'updated_by' => $updatedByUid,
        ]);

        return $request;
    }

    public function cancelRequest(ConnectionRequest $request, string $updatedByUid, int $cancelledStatusId): ConnectionRequest
    {
        $request->update([
            'status_id' => $cancelledStatusId,
            'updated_by' => $updatedByUid,
        ]);

        return $request;
    }

    public function deactivateConnection(string $userAUid, string $userBUid, string $updatedByUid): int
    {
        return Connection::between($userAUid, $userBUid)->update([
            'is_active' => false,
            'disconnected_at' => now(),
            'disconnect_reason' => 'User requested removal',
            'updated_by' => $updatedByUid,
            'updated_at' => now(),
        ]);
    }

    public function findByUuid(string $connectionUuid): ?Connection
    {
        return Connection::query()
            ->where('firebase_uuid', $connectionUuid)
            ->with(['userA.roles', 'userB.roles'])
            ->first();
    }

    public function generateUniqueUuid(int $length = 10): string
    {
        do {
            $requestUuid = Str::random($length);
        } while (
            ConnectionRequest::where('firebase_uuid', $requestUuid)->exists()
            || Connection::where('firebase_uuid', $requestUuid)->exists()
        );

        return $requestUuid;
    }
}
