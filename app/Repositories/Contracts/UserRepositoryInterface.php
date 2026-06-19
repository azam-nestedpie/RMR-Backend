<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?User;

    public function findByFirebaseUid(string $firebaseUid): ?User;

    public function search(array $filters, string $excludeUid, ?string $currentUserRole): LengthAwarePaginator;

    public function searchForTeamInvite(array $filters, string $excludeUid, string $managerUid, array $managerRoles): LengthAwarePaginator;

    public function connectedUsers(string $firebaseUid, ?string $role = null): Collection;

    public function connectableUsers(string $firebaseUid, string $role): Collection;

    public function create(array $data): User;

    public function update(User $user, array $data): bool;

    public function assignRole(User $user, string $roleName): void;

    public function createSalesRepProfile(string $firebaseUid): void;

    public function markDeleted(User $user): void;

    public function generateUniqueUid(int $length = 10): string;
}
