<?php

namespace App\Repositories;

use App\Models\Connection;
use App\Models\ConnectionRequest;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserRepository implements UserRepositoryInterface
{
    public function findByEmail(string $email): ?User
    {
        return User::where('email', strtolower($email))->first();
    }

    public function findByFirebaseUid(string $firebaseUid): ?User
    {
        return User::where('firebase_uid', $firebaseUid)->first();
    }

    public function search(array $filters, string $excludeUid, ?int $currentUserRole): LengthAwarePaginator
    {
        $query = User::where('firebase_uid', '!=', $excludeUid)
            ->where('is_blocked', false);

        foreach (['first_name', 'last_name', 'email', 'company_name', 'position'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, 'like', '%'.$filters[$field].'%');
            }
        }

        if (! empty($filters['address'])) {
            $terms = array_filter(array_map('trim', explode(',', $filters['address'])));

            $query->whereHas('address', function ($addressQuery) use ($terms) {
                $addressQuery->where(function ($q) use ($terms) {
                    foreach ($terms as $term) {
                        $like = '%'.$term.'%';
                        $q->where(function ($sub) use ($like) {
                            $sub->where('country', 'like', $like)
                                ->orWhere('state', 'like', $like)
                                ->orWhere('city', 'like', $like)
                                ->orWhere('postal_code', 'like', $like)
                                ->orWhere('address_line_1', 'like', $like);
                        });
                    }
                });
            });
        }

        if (! empty($filters['industry'])) {
            $query->whereHas('industries', fn ($q) => $q->where('name', 'like', '%'.$filters['industry'].'%'));
        }

        // Cross-role search restriction
        $allowedRoles = [
            Role::RATER => [Role::REPRESENTATIVE],
            Role::REPRESENTATIVE => [Role::RATER],
            Role::MANAGER_OF_REPRESENTATIVES => [Role::RATER, Role::REPRESENTATIVE],
            Role::MANAGER_OF_RATERS => [Role::RATER, Role::REPRESENTATIVE],
        ];

        if (isset($allowedRoles[$currentUserRole])) {
            $query->whereHas('roles', fn ($q) => $q->whereIn('roles.id', $allowedRoles[$currentUserRole]));
        }

        if (! empty($filters['role'])) {
            $query->whereHas('roles', fn ($q) => $q->where('roles.id', (int) $filters['role']));
        }

        $result = $query
            ->select([
                'firebase_uid',
                'first_name',
                'last_name',
                'email',
                'company_name',
                'position',
                'image_url',
            ])
            ->with(['roles', 'address', 'industries'])
            ->paginate(20);

        $connectedMap = Connection::forUser($excludeUid)
            ->active()
            ->get()
            ->mapWithKeys(fn (Connection $c) => [
                $c->user_a_firebase_uid === $excludeUid
                    ? $c->user_b_firebase_uid
                    : $c->user_a_firebase_uid => true,
            ])
            ->all();

        $pendingStatusId = Status::idByName('pending');

        $pendingSentMap = ConnectionRequest::where('requester_firebase_uid', $excludeUid)
            ->where('status_id', $pendingStatusId)
            ->pluck('requester_firebase_uid', 'target_user_firebase_uid')
            ->map(fn () => true)
            ->all();

        $pendingReceivedMap = ConnectionRequest::where('target_user_firebase_uid', $excludeUid)
            ->where('status_id', $pendingStatusId)
            ->pluck('target_user_firebase_uid', 'requester_firebase_uid')
            ->map(fn () => true)
            ->all();

        $favoriteUids = DB::table('user_favorites')
            ->where('user_firebase_uid', $excludeUid)
            ->pluck('favorite_user_firebase_uid')
            ->map(fn () => true)
            ->all();

        foreach ($result->getCollection() as $user) {
            $uid = $user->firebase_uid;

            $user->connection_status = match (true) {
                isset($connectedMap[$uid]) => 'connected',
                isset($pendingSentMap[$uid]) => 'pending',
                isset($pendingReceivedMap[$uid]) => 'pending',
                default => 'connect',
            };

            $user->is_favorite = isset($favoriteUids[$uid]);

            $role = $user->roles->pluck('id')->first();

            if ($role === Role::REPRESENTATIVE) {
                $user->load('salesRepProfile');
            }
        }

        return $result;
    }

    public function searchForTeamInvite(array $filters, string $excludeUid, string $managerUid, array $managerRoles): LengthAwarePaginator
    {
        $pendingStatusId = Status::idByName('pending');

        $searchableRoles = [];
        if (in_array(Role::MANAGER_OF_REPRESENTATIVES, $managerRoles, true)) {
            $searchableRoles[] = Role::REPRESENTATIVE;
        }
        if (in_array(Role::MANAGER_OF_RATERS, $managerRoles, true)) {
            $searchableRoles[] = Role::RATER;
        }

        $query = User::where('users.firebase_uid', '!=', $excludeUid)
            ->where('users.is_blocked', false)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', $searchableRoles))
            ->whereDoesntHave('managers', fn ($q) => $q->where('manager_firebase_uid', $managerUid))
            ->whereDoesntHave('teamInvitesReceived', fn ($q) => $q
                ->where('manager_firebase_uid', $managerUid)
                ->where('status_id', $pendingStatusId)
            );

        foreach (['first_name', 'last_name', 'email', 'company_name', 'position'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, 'like', '%'.$filters[$field].'%');
            }
        }

        if (! empty($filters['address'])) {
            $terms = array_filter(array_map('trim', explode(',', $filters['address'])));

            $query->whereHas('address', function ($addressQuery) use ($terms) {
                $addressQuery->where(function ($q) use ($terms) {
                    foreach ($terms as $term) {
                        $like = '%'.$term.'%';
                        $q->where(function ($sub) use ($like) {
                            $sub->where('country', 'like', $like)
                                ->orWhere('state', 'like', $like)
                                ->orWhere('city', 'like', $like)
                                ->orWhere('postal_code', 'like', $like)
                                ->orWhere('address_line_1', 'like', $like);
                        });
                    }
                });
            });
        }

        if (! empty($filters['industry'])) {
            $query->whereHas('industries', fn ($q) => $q->where('name', 'like', '%'.$filters['industry'].'%'));
        }

        if (! empty($filters['role'])) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $filters['role']));
        }

        $result = $query
            ->select([
                'users.firebase_uid',
                'users.first_name',
                'users.last_name',
                'users.email',
                'users.company_name',
                'users.position',
                'users.image_url',
            ])
            ->with(['roles', 'address', 'industries'])
            ->paginate(20);

        foreach ($result->getCollection() as $user) {
            $roleId = $user->roles->first()?->id;

            if ($roleId === Role::REPRESENTATIVE) {
                $user->load('salesRepProfile');
            }
        }

        return $result;
    }

    public function connectedUsers(string $firebaseUid, ?int $role = null): Collection
    {
        $targetRole = match ($role) {
            Role::RATER => Role::REPRESENTATIVE,
            Role::REPRESENTATIVE => Role::RATER,
            default => null,
        };

        $connections = Connection::forUser($firebaseUid)
            ->active()
            ->get()
            ->keyBy(fn ($connection) => $connection->user_a_firebase_uid === $firebaseUid
                ? $connection->user_b_firebase_uid
                : $connection->user_a_firebase_uid);

        $connectedUids = $connections->keys()->unique()->values();

        $users = User::whereIn('firebase_uid', $connectedUids)
            ->with(['roles', 'address', 'industries'])
            ->when($targetRole, fn ($q) => $q->whereHas('roles', fn ($rq) => $rq->where('roles.id', $targetRole)))
            ->get();

        foreach ($users as $user) {
            $userRole = $user->roles->first()?->id;

            if ($userRole === Role::REPRESENTATIVE) {
                $user->load('salesRepProfile');
            }

            if ($connections->has($user->firebase_uid)) {
                $user->setAttribute('connection_uuid', $connections->get($user->firebase_uid)->firebase_uuid);
            }
        }

        return $users;
    }

    public function connectableUsers(string $firebaseUid, int $role): Collection
    {
        $targetRole = $role === Role::RATER ? Role::REPRESENTATIVE : Role::RATER;

        $connectedUids = Connection::forUser($firebaseUid)
            ->active()
            ->get()
            ->map(fn ($c) => $c->user_a_firebase_uid === $firebaseUid
                ? $c->user_b_firebase_uid
                : $c->user_a_firebase_uid)
            ->unique()
            ->values()
            ->toArray();

        $pendingStatusId = Status::idByName('pending');

        $pendingUids = ConnectionRequest::where(function ($q) use ($firebaseUid) {
            $q->where('requester_firebase_uid', $firebaseUid)
                ->orWhere('target_user_firebase_uid', $firebaseUid);
        })
            ->when($pendingStatusId, fn ($q) => $q->where('status_id', $pendingStatusId))
            ->get()
            ->map(fn ($r) => $r->requester_firebase_uid === $firebaseUid
                ? $r->target_user_firebase_uid
                : $r->requester_firebase_uid)
            ->unique()
            ->values()
            ->toArray();

        $excludeUids = array_unique(array_merge($connectedUids, $pendingUids, [$firebaseUid]));

        $users = User::whereNotIn('firebase_uid', $excludeUids)
            ->where('is_blocked', false)
            ->whereHas('roles', fn ($q) => $q->where('roles.id', $targetRole))
            ->with(['roles', 'address', 'industries'])
            ->get();

        foreach ($users as $user) {
            if ($targetRole === Role::REPRESENTATIVE) {
                $user->load('salesRepProfile');
            }
        }

        return $users;
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(User $user, array $data): bool
    {
        return $user->update($data);
    }

    public function assignRole(User $user, int $roleId): void
    {
        $role = Role::find($roleId);

        if ($role) {
            $user->roles()->syncWithoutDetaching([
                $role->id => [
                    'created_by' => $user->firebase_uid,
                    'updated_by' => $user->firebase_uid,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }

    public function createSalesRepProfile(string $firebaseUid): void
    {
        DB::table('sales_rep_users')->updateOrInsert(
            ['user_firebase_uid' => $firebaseUid],
            [
                'avg_rating' => 0.00,
                'ratings_count' => 0,
                'is_subscribed' => false,
                'created_by' => $firebaseUid,
                'updated_by' => $firebaseUid,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function markDeleted(User $user): void
    {
        $user->update(['is_deleted' => true, 'updated_by' => $user->firebase_uid]);
        $user->delete();
        $user->tokens()->delete();
    }

    public function generateUniqueUid(int $length = 10): string
    {
        do {
            $uid = Str::random($length);
        } while (User::where('firebase_uid', $uid)->exists());

        return $uid;
    }
}
