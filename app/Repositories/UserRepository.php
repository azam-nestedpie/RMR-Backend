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

    public function search(array $filters, string $excludeUid, ?string $currentUserRole, bool $bulkConnection = false): LengthAwarePaginator
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

        if ($bulkConnection) {
            $pendingStatusId = Status::idByName('pending');

            $connectedUids = Connection::forUser($excludeUid)
                ->active()
                ->get()
                ->map(fn (Connection $c) => $c->user_a_firebase_uid === $excludeUid
                    ? $c->user_b_firebase_uid
                    : $c->user_a_firebase_uid)
                ->unique()
                ->values()
                ->toArray();

            $pendingUids = ConnectionRequest::where(function ($q) use ($excludeUid) {
                $q->where('requester_firebase_uid', $excludeUid)
                    ->orWhere('target_user_firebase_uid', $excludeUid);
            })
                ->where('status_id', $pendingStatusId)
                ->get()
                ->map(fn (ConnectionRequest $r) => $r->requester_firebase_uid === $excludeUid
                    ? $r->target_user_firebase_uid
                    : $r->requester_firebase_uid)
                ->unique()
                ->values()
                ->toArray();

            $excludeUids = array_unique(array_merge($connectedUids, $pendingUids));

            $query->whereNotIn('firebase_uid', $excludeUids);
        }

        // Cross-role search restriction
        $allowedRoles = [
            'rater' => ['rep'],
            'rep' => ['rater'],
            'manager_of_reps' => ['rater', 'rep'],
            'manager_of_raters' => ['rater', 'rep'],
        ];

        if (isset($allowedRoles[$currentUserRole])) {
            $query->whereHas('roles', fn ($q) => $q->whereIn('name', $allowedRoles[$currentUserRole]));
        }

        if (! empty($filters['role'])) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $filters['role']));
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

        // ✅ Attach salesRepProfile ONLY for reps
        foreach ($result->getCollection() as $user) {

            $role = $user->roles->pluck('name')->first();

            if ($role === 'rep') {
                $user->load('salesRepProfile');
            }
        }

        return $result;
    }

    public function searchForTeamInvite(array $filters, string $excludeUid, string $managerUid, array $managerRoles): LengthAwarePaginator
    {
        $pendingStatusId = Status::idByName('pending');

        $searchableRoles = [];
        if (in_array('manager_of_reps', $managerRoles, true)) {
            $searchableRoles[] = 'rep';
        }
        if (in_array('manager_of_raters', $managerRoles, true)) {
            $searchableRoles[] = 'rater';
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
            $role = $user->roles->pluck('name')->first();

            if ($role === 'rep') {
                $user->load('salesRepProfile');
            }
        }

        return $result;
    }

    public function connectedUsers(string $firebaseUid, ?string $role = null): Collection
    {
        $targetRole = match ($role) {
            'rater' => 'rep',
            'rep' => 'rater',
            default => null,
        };

        $connections = Connection::forUser($firebaseUid)
            ->active()
            ->get();

        $connectedUids = $connections->map(function ($connection) use ($firebaseUid) {
            return $connection->user_a_firebase_uid === $firebaseUid
                ? $connection->user_b_firebase_uid
                : $connection->user_a_firebase_uid;
        })->unique()->values();

        $users = User::whereIn('firebase_uid', $connectedUids)
            ->with(['roles', 'address', 'industries'])
            ->when($targetRole, fn ($q) => $q->whereHas('roles', fn ($rq) => $rq->where('name', $targetRole)))
            ->get();

        foreach ($users as $user) {
            $userRole = $user->roles->pluck('name')->first();

            if ($userRole === 'rep') {
                $user->load('salesRepProfile');
            }
        }

        return $users;
    }

    public function connectableUsers(string $firebaseUid, string $role): Collection
    {
        $targetRole = $role === 'rater' ? 'rep' : 'rater';

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
            ->whereHas('roles', fn ($q) => $q->where('name', $targetRole))
            ->with(['roles', 'address', 'industries'])
            ->get();

        foreach ($users as $user) {
            if ($targetRole === 'rep') {
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

    public function assignRole(User $user, string $roleName): void
    {
        $roleId = Role::idByName($roleName);
        if ($roleId) {
            $user->roles()->syncWithoutDetaching([
                $roleId => [
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
