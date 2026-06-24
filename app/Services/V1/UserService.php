<?php

namespace App\Services\V1;

use App\Models\Connection;
use App\Models\ConnectionRequest;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * PROFILE
     */
    public function profile(User $user): User
    {
        Log::info('Profile requested', [
            'uid' => $user->firebase_uid,
        ]);

        // ✅ clear old loaded relations
        $user->setRelations([]);

        $user->load([
            'roles',
            'address',
            'industries',
        ]);

        $role = $user->roles->pluck('id')->first();

        if ($role === Role::REPRESENTATIVE) {
            $user->load('salesRepProfile');
        }

        return $user;
    }

    /**
     * UPDATE PROFILE
     */
    public function updateProfile(
        User $user,
        array $userFields,
        array $addressFields,
    ): User {

        Log::info('Update profile started', [
            'uid' => $user->firebase_uid,
        ]);

        DB::transaction(function () use (
            $user,
            $userFields,
            $addressFields,
        ) {

            // 🏭 Update industry
            if (isset($userFields['industry'])) {

                $user->industries()->sync([$userFields['industry']]);

                unset($userFields['industry']);
            }

            // 👤 Update user fields
            if (! empty($userFields)) {
                $userFields['updated_by'] = $user->firebase_uid;
                $this->users->update($user, $userFields);
            }

            // 📍 Update or create address
            if (! empty(array_filter($addressFields))) {
                $user->address()->updateOrCreate(
                    ['user_firebase_uid' => $user->firebase_uid],
                    array_merge($addressFields, [
                        'updated_by' => $user->firebase_uid,
                        'created_by' => $user->firebase_uid,
                    ])
                );
            }

        });

        Log::info('Update profile completed', [
            'uid' => $user->firebase_uid,
        ]);

        $user = $user->fresh()->setRelations([]);

        $user->load([
            'roles',
            'address',
            'industries',
        ]);

        $roleId = $user->roles->first()?->id;

        if ($roleId === Role::REPRESENTATIVE) {
            $user->load('salesRepProfile');
        }

        return $user;
    }

    /**
     * SHOW USER
     */
    public function show(string $userUid, ?int $currentUserRole): ?User
    {
        $user = User::where('firebase_uid', $userUid)
            ->with([
                'roles',
                'address',
                'industries',
            ])
            ->first();

        if (! $user) {
            return null;
        }

        $targetRole = $user->roles->first()?->id;

        if (! $this->canViewRole($currentUserRole, $targetRole)) {
            return null;
        }

        if ($targetRole === Role::REPRESENTATIVE) {
            $user->load('salesRepProfile');
        }

        return $user;
    }

    private function canViewRole(?int $currentUserRole, ?int $targetUserRole): bool
    {
        if (! $targetUserRole) {
            return true;
        }

        $allowedRoles = [
            Role::RATER => [Role::REPRESENTATIVE],
            Role::REPRESENTATIVE => [Role::RATER],
            Role::MANAGER_OF_REPRESENTATIVES => [Role::RATER, Role::REPRESENTATIVE],
            Role::MANAGER_OF_RATERS => [Role::RATER, Role::REPRESENTATIVE],
        ];

        if (! isset($allowedRoles[$currentUserRole])) {
            return true;
        }

        return in_array($targetUserRole, $allowedRoles[$currentUserRole]);
    }

    /**
     * SEARCH USERS
     */
    public function search(array $filters, string $excludeUid, ?int $currentUserRole): LengthAwarePaginator
    {
        Log::info('User search requested', [
            'filters' => $filters,
            'exclude_uid' => $excludeUid,
            'current_user_role' => $currentUserRole,
        ]);

        return $this->users->search($filters, $excludeUid, $currentUserRole);
    }

    /**
     * DELETE USER (soft delete logic)
     */
    public function destroy(User $user): void
    {
        Log::warning('User delete requested', [
            'uid' => $user->firebase_uid,
        ]);

        $this->users->markDeleted($user);
    }

    /**
     * Get a team member's network (connections, sent requests, received requests).
     *
     * @return array{connections: Collection, sentRequests: Collection, receivedRequests: Collection}
     */
    public function teamMemberNetwork(User $member): array
    {
        $pendingStatusId = Status::idByName('pending');

        $connections = Connection::forUser($member->firebase_uid)
            ->active()
            ->with(['userA', 'userB'])
            ->get()
            ->map(fn (Connection $c) => $c->user_a_firebase_uid === $member->firebase_uid
                ? $c->userB
                : $c->userA
            )
            ->filter();

        $sentRequests = ConnectionRequest::forRequester($member->firebase_uid)
            ->when($pendingStatusId, fn ($q) => $q->where('status_id', $pendingStatusId))
            ->with(['target'])
            ->get()
            ->pluck('target')
            ->filter();

        $receivedRequests = ConnectionRequest::forTarget($member->firebase_uid)
            ->when($pendingStatusId, fn ($q) => $q->where('status_id', $pendingStatusId))
            ->with(['requester'])
            ->get()
            ->pluck('requester')
            ->filter();

        return [
            'connections' => $connections,
            'sentRequests' => $sentRequests,
            'receivedRequests' => $receivedRequests,
        ];
    }
}
