<?php

namespace App\Services\V1;

use App\Exceptions\ApiException;
use App\Models\ManagerTeamMember;
use App\Models\Role;
use App\Models\Status;
use App\Models\Team;
use App\Models\User;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TeamService
{
    public function __construct(
        private readonly NotificationRepositoryInterface $notifications,
        private readonly UserRepositoryInterface $users,
    ) {}

    public function send(User $manager, array $targetUids): array
    {
        if (! $manager->hasPermission('send connection request') || ! $manager->hasRole([Role::MANAGER_OF_REPRESENTATIVES, Role::MANAGER_OF_RATERS])) {
            throw ApiException::forbidden('You do not have permission to perform this action.');
        }

        $teams = [];

        DB::transaction(function () use ($manager, $targetUids, &$teams): void {
            foreach ($targetUids as $targetUid) {
                $target = $this->userOrFail($targetUid);

                $teams[] = array_merge([
                    'target_uid' => $target->firebase_uid,
                ], $this->createTeam($manager, $target));
            }
        });

        return ['teams' => $teams];
    }

    public function accept(User $user, string $teamUuid): void
    {
        $team = $this->pendingForTarget($user, $teamUuid);
        $acceptedStatusId = $this->statusId('accepted');

        if ($team->manager->manages($user->firebase_uid)) {
            throw ApiException::conflict('This user is already in your team.');
        }

        DB::transaction(function () use ($team, $user, $acceptedStatusId): void {
            $team->update([
                'status_id' => $acceptedStatusId,
                'responded_at' => now(),
                'updated_by' => $user->firebase_uid,
            ]);

            DB::table('manager_team_members')->updateOrInsert(
                [
                    'manager_firebase_uid' => $team->manager_firebase_uid,
                    'member_firebase_uid' => $team->target_user_firebase_uid,
                ],
                [
                    'manager_type_role_id' => $team->manager_type_role_id,
                    'active' => true,
                    'joined_at' => now(),
                    'left_at' => null,
                    'created_by' => $team->manager_firebase_uid,
                    'updated_by' => $user->firebase_uid,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $this->notify(
                $team->manager_firebase_uid,
                $user->firebase_uid,
                $user->first_name.' accepted your team invitation'
            );
        });
    }

    public function reject(User $user, string $teamUuid): void
    {
        $team = $this->pendingForTarget($user, $teamUuid);
        $rejectedStatusId = $this->statusId('rejected');

        DB::transaction(function () use ($team, $user, $rejectedStatusId): void {
            $team->update([
                'status_id' => $rejectedStatusId,
                'responded_at' => now(),
                'updated_by' => $user->firebase_uid,
            ]);

            $this->notify(
                $team->manager_firebase_uid,
                $user->firebase_uid,
                $user->first_name.' rejected your team invitation'
            );
        });
    }

    public function cancel(User $user, string $teamUuid): void
    {
        $team = $this->pendingByUuid($teamUuid);

        if (! $team || $team->manager_firebase_uid !== $user->firebase_uid) {
            throw ApiException::notFound('Team invitation not found.');
        }

        $team->update([
            'status_id' => $this->statusId('cancelled'),
            'updated_by' => $user->firebase_uid,
        ]);
    }

    public function pending(User $user): array
    {
        $pendingStatusId = $this->statusId('pending');

        return [
            'received' => Team::query()
                ->forTarget($user->firebase_uid)
                ->where('status_id', $pendingStatusId)
                ->with(['manager.roles', 'target.roles', 'status'])
                ->orderByDesc('created_at')
                ->get(),
            'sent' => Team::query()
                ->forManager($user->firebase_uid)
                ->where('status_id', $pendingStatusId)
                ->with(['manager.roles', 'target.roles', 'status'])
                ->orderByDesc('created_at')
                ->get(),
        ];
    }

    public function requests(User $user): array
    {
        $relations = ['manager.roles', 'target.roles', 'managerRole', 'status'];
        $pendingStatusId = $this->statusId('pending');

        return [
            'received' => Team::query()
                ->forTarget($user->firebase_uid)
                ->where('status_id', $pendingStatusId)
                ->with($relations)
                ->orderByDesc('created_at')
                ->get(),
            'sent' => Team::query()
                ->forManager($user->firebase_uid)
                ->where('status_id', $pendingStatusId)
                ->with($relations)
                ->orderByDesc('created_at')
                ->get(),
        ];
    }

    public function searchForInvite(User $manager, array $filters): LengthAwarePaginator
    {
        return $this->users->searchForTeamInvite(
            $filters,
            $manager->firebase_uid,
            $manager->firebase_uid,
            $manager->roles->pluck('name')->toArray()
        );
    }

    public function members(User $manager): Collection
    {
        if (! $manager->hasRole([Role::MANAGER_OF_REPRESENTATIVES, Role::MANAGER_OF_RATERS])) {
            throw ApiException::forbidden('Only managers can view team members.');
        }

        return $manager->teamMembers()
            ->with(['roles', 'address', 'industries', 'salesRepProfile'])
            ->orderBy('users.first_name')
            ->orderBy('users.last_name')
            ->get();
    }

    public function destroy(User $manager, string $memberUid): void
    {
        if (! $manager->hasRole([Role::MANAGER_OF_REPRESENTATIVES, Role::MANAGER_OF_RATERS])) {
            throw ApiException::forbidden('Only managers can remove team members.');
        }

        $updated = ManagerTeamMember::query()
            ->where('manager_firebase_uid', $manager->firebase_uid)
            ->where('member_firebase_uid', $memberUid)
            ->where('active', true)
            ->whereNull('left_at')
            ->update([
                'active' => false,
                'left_at' => now(),
                'updated_by' => $manager->firebase_uid,
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            throw ApiException::notFound('Team member not found.');
        }
    }

    public function leave(User $member, string $managerUid): void
    {
        $updated = ManagerTeamMember::query()
            ->where('manager_firebase_uid', $managerUid)
            ->where('member_firebase_uid', $member->firebase_uid)
            ->where('active', true)
            ->whereNull('left_at')
            ->update([
                'active' => false,
                'left_at' => now(),
                'updated_by' => $member->firebase_uid,
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            throw ApiException::notFound('Team membership not found.');
        }

        $this->notify(
            $managerUid,
            $member->firebase_uid,
            $member->first_name.' left your team'
        );
    }

    private function createTeam(User $manager, User $target): array
    {
        $this->assertManagerMemberPair($manager, $target);

        if ($manager->manages($target->firebase_uid)) {
            throw ApiException::conflict('This user is already in your team.');
        }

        if ($this->pendingBetween($manager->firebase_uid, $target->firebase_uid)) {
            throw ApiException::conflict('Team invitation already pending.');
        }

        $teamUuid = $this->generateUniqueUuid();
        $managerRoleId = $manager->roles()->whereIn('id', [Role::MANAGER_OF_REPRESENTATIVES, Role::MANAGER_OF_RATERS])->value('roles.id');

        Team::create([
            'firebase_uuid' => $teamUuid,
            'manager_firebase_uid' => $manager->firebase_uid,
            'target_user_firebase_uid' => $target->firebase_uid,
            'manager_type_role_id' => $managerRoleId,
            'status_id' => $this->statusId('pending'),
            'created_by' => $manager->firebase_uid,
            'updated_by' => $manager->firebase_uid,
        ]);

        $this->notify(
            $target->firebase_uid,
            $manager->firebase_uid,
            $manager->first_name.' sent you a team invitation'
        );

        return ['team_uuid' => $teamUuid];
    }

    private function pendingForTarget(User $user, string $teamUuid): Team
    {
        $team = Team::query()
            ->where('firebase_uuid', $teamUuid)
            ->where('target_user_firebase_uid', $user->firebase_uid)
            ->where('status_id', $this->statusId('pending'))
            ->with(['manager.roles', 'target.roles'])
            ->first();

        return $team ?? throw ApiException::notFound('Team invitation not found.');
    }

    private function pendingByUuid(string $teamUuid): ?Team
    {
        return Team::query()
            ->where('firebase_uuid', $teamUuid)
            ->where('status_id', $this->statusId('pending'))
            ->with(['manager.roles', 'target.roles'])
            ->first();
    }

    private function pendingBetween(string $managerUid, string $targetUid): ?Team
    {
        return Team::query()
            ->where('manager_firebase_uid', $managerUid)
            ->where('target_user_firebase_uid', $targetUid)
            ->where('status_id', $this->statusId('pending'))
            ->first();
    }

    private function assertManagerMemberPair(User $manager, User $target): void
    {
        if ($manager->firebase_uid === $target->firebase_uid) {
            throw ApiException::badRequest('You cannot invite yourself to your team.');
        }

        if (
            ! (($manager->hasRole(Role::MANAGER_OF_REPRESENTATIVES) && $target->hasRole(Role::REPRESENTATIVE))
                || ($manager->hasRole(Role::MANAGER_OF_RATERS) && $target->hasRole(Role::RATER)))
        ) {
            throw ApiException::badRequest('Team invitations are only allowed between managers and their team role.');
        }
    }

    private function userOrFail(string $firebaseUid): User
    {
        return $this->users->findByFirebaseUid($firebaseUid)
            ?? throw ApiException::notFound('User not found.');
    }

    private function statusId(string $name): int
    {
        return Status::idByName($name)
            ?? throw new \LogicException("Required status seed is missing: {$name}.");
    }

    private function generateUniqueUuid(int $length = 10): string
    {
        do {
            $teamUuid = Str::random($length);
        } while (Team::query()->where('firebase_uuid', $teamUuid)->exists());

        return $teamUuid;
    }

    private function notify(string $toUid, string $fromUid, string $message): void
    {
        $this->notifications->create([
            'firebase_uuid' => $this->generateUniqueUuid(),
            'to_user_firebase_uid' => $toUid,
            'from_user_firebase_uid' => $fromUid,
            'message' => $message,
            'screen' => 'team',
            'tab_index' => 0,
            'is_read' => false,
            'sent_at' => now(),
            'created_by' => $fromUid,
            'updated_by' => $fromUid,
        ]);
    }
}
