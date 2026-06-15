<?php

namespace App\Services\V1;

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

        $role = $user->roles->pluck('name')->first();

        if ($role === 'rep') {
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

        $role = $user->roles->pluck('name')->first();

        if ($role === 'rep') {
            $user->load('salesRepProfile');
        }

        return $user;
    }

    /**
     * SHOW USER
     */
    public function show(string $userUid, ?string $currentUserRole): ?User
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

        $targetRole = $user->roles->pluck('name')->first();

        if (! $this->canViewRole($currentUserRole, $targetRole)) {
            return null;
        }

        if ($targetRole === 'rep') {
            $user->load('salesRepProfile');
        }

        return $user;
    }

    private function canViewRole(?string $currentUserRole, ?string $targetUserRole): bool
    {
        if (! $targetUserRole) {
            return true;
        }

        $allowedRoles = [
            'rater' => ['rep'],
            'rep' => ['rater'],
            'manager_of_reps' => ['rater', 'rep'],
            'manager_of_raters' => ['rater', 'rep'],
        ];

        if (! isset($allowedRoles[$currentUserRole])) {
            return true;
        }

        return in_array($targetUserRole, $allowedRoles[$currentUserRole]);
    }

    /**
     * SEARCH USERS
     */
    public function search(array $filters, string $excludeUid, ?string $currentUserRole, bool $bulkConnection = false): LengthAwarePaginator
    {
        Log::info('User search requested', [
            'filters' => $filters,
            'exclude_uid' => $excludeUid,
            'current_user_role' => $currentUserRole,
            'bulk_connection' => $bulkConnection,
        ]);

        return $this->users->search($filters, $excludeUid, $currentUserRole, $bulkConnection);
    }

    /**
     * CONNECTIONS
     */
    public function myConnections(string $firebaseUid): Collection
    {
        return $this->users->connectedUsers($firebaseUid);
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
}
