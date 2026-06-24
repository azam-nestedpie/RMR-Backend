<?php

namespace App\Services\V1;

use App\Exceptions\ApiException;
use App\Models\Connection;
use App\Models\ConnectionRequest;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use App\Repositories\Contracts\ConnectionRepositoryInterface;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConnectionService
{
    public function __construct(
        private readonly ConnectionRepositoryInterface $connections,
        private readonly NotificationRepositoryInterface $notifications,
        private readonly UserRepositoryInterface $users,
    ) {}

    public function sendRequest(User $sender, string $targetUid): array
    {
        $target = $this->userOrFail($targetUid);

        return $this->createConnectionRequest($sender, $sender, $target, null);
    }

    public function sendBulkRequest(User $sender, array $targetUids): array
    {
        $targets = User::whereIn('firebase_uid', $targetUids)->get()->keyBy('firebase_uid');

        foreach ($targetUids as $uid) {
            $target = $targets->get($uid);
            if (! $target) {
                throw ApiException::notFound("User not found: {$uid}");
            }
            $this->assertConnectablePair($sender, $target);

            if ($this->connections->existsActiveBetween($sender->firebase_uid, $uid)) {
                throw ApiException::conflict("Already connected with user: {$uid}");
            }

            if ($this->connections->findPendingRequestBetweenParticipants($sender->firebase_uid, $uid)) {
                throw ApiException::conflict("Connection request already pending for user: {$uid}");
            }
        }

        $requestUuids = [];

        DB::transaction(function () use ($sender, $targetUids, $targets, &$requestUuids): void {
            foreach ($targetUids as $uid) {
                $target = $targets->get($uid);
                $requestUuid = $this->connections->generateUniqueUuid();
                $requestUuids[] = $requestUuid;

                $pendingStatusId = Status::idByName('pending')
                    ?? throw new \LogicException('Required status seed is missing: pending.');

                $this->connections->createRequest([
                    'firebase_uuid' => $requestUuid,
                    'requester_firebase_uid' => $sender->firebase_uid,
                    'target_user_firebase_uid' => $uid,
                    'manager_firebase_uid' => null,
                    'behalf_firebase_uid' => null,
                    'status_id' => $pendingStatusId,
                    'created_by' => $sender->firebase_uid,
                    'updated_by' => $sender->firebase_uid,
                ]);

                $this->notify($uid, $sender->firebase_uid, $sender->first_name.' sent you a connection request', 'connections');
            }
        });

        return ['request_uuids' => $requestUuids];
    }

    public function sendRequestOnBehalf(User $manager, string $behalfUid, string $targetUid): array
    {
        $behalfUser = $this->userOrFail($behalfUid);
        $target = $this->userOrFail($targetUid);

        return $this->createConnectionRequest($manager, $behalfUser, $target, $manager);
    }

    public function acceptRequest(User $user, string $requestUuid): void
    {
        Log::info('Connection request acceptance started', [
            'user_uid' => $user->firebase_uid,
            'request_uuid' => $requestUuid,
        ]);

        $acceptedStatusId = Status::idByName('accepted')
            ?? throw new \LogicException('Required status seed is missing: accepted.');

        $request = $this->pendingRequestForResponder($requestUuid, $user);

        $this->assertConnectablePair($request->requester, $request->target);

        if ($this->connections->existsActiveBetween($request->requester_firebase_uid, $request->target_user_firebase_uid)) {
            throw ApiException::conflict('Already connected with this user.');
        }

        DB::transaction(function () use ($request, $user, $acceptedStatusId): void {
            $this->connections->acceptRequest($request, $user->firebase_uid, $acceptedStatusId);

            $this->notify(
                $request->manager_firebase_uid ?: $request->requester_firebase_uid,
                $user->firebase_uid,
                $user->first_name.' accepted your connection request',
                'connections'
            );
        });

        Log::info('Connection request acceptance completed', [
            'user_uid' => $user->firebase_uid,
            'request_uuid' => $requestUuid,
        ]);
    }

    public function rejectRequest(User $user, string $requestUuid): void
    {
        $rejectedStatusId = Status::idByName('rejected')
            ?? throw new \LogicException('Required status seed is missing: rejected.');

        $request = $this->pendingRequestForResponder($requestUuid, $user);

        DB::transaction(function () use ($request, $user, $rejectedStatusId): void {
            $this->connections->rejectRequest($request, $user->firebase_uid, $rejectedStatusId);

            $this->notify(
                $request->manager_firebase_uid ?: $request->requester_firebase_uid,
                $user->firebase_uid,
                $user->first_name.' rejected your connection request',
                'connections'
            );
        });
    }

    public function listConnections(string $firebaseUid): Collection
    {
        return $this->connections->activeConnectionsForUser($firebaseUid);
    }

    public function connectedUserList(User $user): Collection
    {
        $user->loadMissing('roles');
        $roleId = $user->roles->first()?->id;

        return $this->users->connectedUsers($user->firebase_uid, $roleId);
    }

    public function connectableUsers(User $user): Collection
    {
        $user->loadMissing('roles');
        $roleId = $user->roles->first()?->id;

        if (! $roleId || ! in_array($roleId, [Role::RATER, Role::REPRESENTATIVE], true)) {
            return collect();
        }

        return $this->users->connectableUsers($user->firebase_uid, $roleId);
    }

    public function pendingRequests(string $firebaseUid): Collection
    {
        return $this->connections->pendingRequestsForUser($firebaseUid);
    }

    /**
     * @return array{received: Collection, sent: Collection}
     */
    public function requests(User $user): array
    {
        $user->loadMissing('roles');
        $role = $user->roles->first()?->id;

        return $this->connections->requestsForUser($user->firebase_uid, $role);
    }

    public function cancelRequest(User $user, string $requestUuid): void
    {
        $cancelledStatusId = Status::idByName('cancelled')
            ?? throw new \LogicException('Required status seed is missing: cancelled.');

        $pendingStatusId = Status::idByName('pending')
            ?? throw new \LogicException('Required status seed is missing: pending.');

        $request = $this->connections->findRequestByUuid($requestUuid);
        if (! $request || $request->status_id !== $pendingStatusId) {
            throw ApiException::notFound('Connection request not found.');
        }

        if ($request->requester_firebase_uid !== $user->firebase_uid) {
            throw ApiException::forbidden('Only the request sender can cancel this connection request.');
        }

        $this->connections->cancelRequest($request, $user->firebase_uid, $cancelledStatusId);
    }

    public function findRequestForAuthorization(string $requestUuid): ConnectionRequest
    {
        return $this->connections->findRequestByUuid($requestUuid)
            ?? throw ApiException::notFound('Connection request not found.');
    }

    public function findConnectionForAuthorization(string $connectionUuid): Connection
    {
        return $this->connections->findByUuid($connectionUuid)
            ?? throw ApiException::notFound('Connection not found.');
    }

    public function removeConnection(User $user, string $connectionUuid): void
    {
        $connection = $this->connections->findByUuid($connectionUuid);
        if (! $connection) {
            throw ApiException::notFound('Connection not found.');
        }

        $this->connections->deactivateConnection(
            $connection->user_a_firebase_uid,
            $connection->user_b_firebase_uid,
            $user->firebase_uid
        );
    }

    private function createConnectionRequest(User $actor, User $requester, User $target, ?User $manager): array
    {
        Log::info('Connection request started', [
            'actor_uid' => $actor->firebase_uid,
            'requester_uid' => $requester->firebase_uid,
            'target_uid' => $target->firebase_uid,
            'manager_uid' => $manager?->firebase_uid,
        ]);

        $pendingStatusId = Status::idByName('pending')
            ?? throw new \LogicException('Required status seed is missing: pending.');

        $this->assertConnectablePair($requester, $target);

        if ($this->connections->existsActiveBetween($requester->firebase_uid, $target->firebase_uid)) {
            throw ApiException::conflict('Already connected with this user.');
        }

        if ($this->connections->findPendingRequestBetweenParticipants($requester->firebase_uid, $target->firebase_uid)) {
            throw ApiException::conflict('Connection request already pending.');
        }

        $requestUuid = $this->connections->generateUniqueUuid();

        DB::transaction(function () use ($actor, $requester, $target, $manager, $pendingStatusId, $requestUuid): void {
            $this->connections->createRequest([
                'firebase_uuid' => $requestUuid,
                'requester_firebase_uid' => $requester->firebase_uid,
                'target_user_firebase_uid' => $target->firebase_uid,
                'manager_firebase_uid' => $manager?->firebase_uid,
                'behalf_firebase_uid' => $manager ? $requester->firebase_uid : null,
                'status_id' => $pendingStatusId,
                'created_by' => $actor->firebase_uid,
                'updated_by' => $actor->firebase_uid,
            ]);

            $message = $manager
                ? $manager->first_name.' sent a connection request on behalf of '.$requester->first_name
                : $requester->first_name.' sent you a connection request';

            $this->notify($target->firebase_uid, $actor->firebase_uid, $message, 'connections');
        });

        return ['request_uuid' => $requestUuid];
    }

    private function assertConnectablePair(User $requester, User $target): void
    {
        if ($requester->firebase_uid === $target->firebase_uid) {
            throw ApiException::badRequest('You cannot connect with yourself.');
        }

        $requester->loadMissing('roles');
        $target->loadMissing('roles');

        $isRepToRater = $requester->hasRole(Role::REPRESENTATIVE) && $target->hasRole(Role::RATER);
        $isRaterToRep = $requester->hasRole(Role::RATER) && $target->hasRole(Role::REPRESENTATIVE);

        if (! $isRepToRater && ! $isRaterToRep) {
            throw ApiException::badRequest('Connections are only allowed between reps and raters.');
        }
    }

    private function pendingRequestForResponder(string $requestUuid, User $user): ConnectionRequest
    {
        $pendingStatusId = Status::idByName('pending')
            ?? throw new \LogicException('Required status seed is missing: pending.');

        $request = $this->connections->findPendingRequestByUuid($requestUuid, $user->firebase_uid);

        if ($request) {
            return $request;
        }

        $request = $this->connections->findRequestByUuid($requestUuid);

        if (
            ! $request
            || $request->status_id !== $pendingStatusId
            || ! $user->hasRole([Role::MANAGER_OF_REPRESENTATIVES, Role::MANAGER_OF_RATERS])
            || ! $user->manages($request->target_user_firebase_uid)
        ) {
            throw ApiException::notFound('Connection request not found.');
        }

        return $request;
    }

    private function userOrFail(string $firebaseUid): User
    {
        return $this->users->findByFirebaseUid($firebaseUid)
            ?? throw ApiException::notFound('User not found.');
    }

    private function notify(string $toUid, string $fromUid, string $message, string $screen): void
    {
        $notificationUuid = $this->connections->generateUniqueUuid();
        $this->notifications->create([
            'firebase_uuid' => $notificationUuid,
            'to_user_firebase_uid' => $toUid,
            'from_user_firebase_uid' => $fromUid,
            'message' => $message,
            'screen' => $screen,
            'tab_index' => 0,
            'is_read' => false,
            'sent_at' => now(),
            'created_by' => $fromUid,
            'updated_by' => $fromUid,
        ]);
    }
}
