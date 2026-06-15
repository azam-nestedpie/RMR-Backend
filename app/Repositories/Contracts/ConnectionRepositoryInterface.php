<?php

namespace App\Repositories\Contracts;

use App\Models\Connection;
use App\Models\ConnectionRequest;
use Illuminate\Support\Collection;

interface ConnectionRepositoryInterface
{
    public function existsActiveBetween(string $userAUid, string $userBUid): bool;

    public function findConnectionBetween(string $userAUid, string $userBUid): ?Connection;

    public function activeConnectionsForUser(string $firebaseUid): Collection;

    public function activeConnectionsForTeam(array $teamMemberUids): Collection;

    public function pendingRequestsForUser(string $firebaseUid): Collection;

    /**
     * @return array{received: Collection, sent: Collection}
     */
    public function requestsForUser(string $firebaseUid, ?string $role = null): array;

    public function findPendingRequestBetweenParticipants(string $userAUid, string $userBUid): ?ConnectionRequest;

    public function findPendingRequestByUuid(string $requestUuid, string $targetUid): ?ConnectionRequest;

    public function findRequestByUuid(string $requestUuid): ?ConnectionRequest;

    public function createRequest(array $attributes): ConnectionRequest;

    public function acceptRequest(ConnectionRequest $request, string $updatedByUid, int $acceptedStatusId): Connection;

    public function rejectRequest(ConnectionRequest $request, string $updatedByUid, int $rejectedStatusId): ConnectionRequest;

    public function cancelRequest(ConnectionRequest $request, string $updatedByUid, int $cancelledStatusId): ConnectionRequest;

    public function deactivateConnection(string $userAUid, string $userBUid, string $updatedByUid): int;

    public function findByUuid(string $connectionUuid): ?Connection;

    public function generateUniqueUuid(int $length = 10): string;
}
