<?php

namespace App\Policies;

use App\Models\Connection;
use App\Models\ConnectionRequest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ConnectionPolicy
{
    use HandlesAuthorization;

    public function send(User $user, User $target): bool
    {
        return $user->hasPermission('send connection request')
            && $this->isParticipantPair($user, $target);
    }

    public function sendOnBehalf(User $user, User $behalfUser, User $target): bool
    {
        return $user->hasPermission('send connection request')
            && $this->isManager($user)
            && $user->manages($behalfUser->firebase_uid)
            && $this->managerOwnsRole($user, $behalfUser)
            && $this->isParticipantPair($behalfUser, $target);
    }

    public function accept(User $user, ConnectionRequest $request): bool
    {
        return $user->hasPermission('accept connection request')
            && $this->canRespondToRequest($user, $request);
    }

    public function reject(User $user, ConnectionRequest $request): bool
    {
        return $user->hasPermission('reject connection request')
            && $this->canRespondToRequest($user, $request);
    }

    public function disconnect(User $user, Connection $connection): bool
    {
        return $user->hasPermission('disconnect connection')
            && in_array($user->firebase_uid, [
                $connection->user_a_firebase_uid,
                $connection->user_b_firebase_uid,
            ], true);
    }

    public function viewTeamConnections(User $user): bool
    {
        return $user->hasPermission('team.view.connections') && $this->isManager($user);
    }

    private function isParticipant(User $user): bool
    {
        return $user->hasRole(['rep', 'rater']);
    }

    private function isManager(User $user): bool
    {
        return $user->hasRole(['manager_of_reps', 'manager_of_raters']);
    }

    private function managerOwnsRole(User $manager, User $member): bool
    {
        return ($manager->hasRole('manager_of_reps') && $member->hasRole('rep'))
            || ($manager->hasRole('manager_of_raters') && $member->hasRole('rater'));
    }

    private function isParticipantPair(User $user, User $target): bool
    {
        return $this->isParticipant($user)
            && $this->isParticipant($target)
            && (($user->hasRole('rep') && $target->hasRole('rater'))
                || ($user->hasRole('rater') && $target->hasRole('rep')));
    }

    private function canRespondToRequest(User $user, ConnectionRequest $request): bool
    {
        return $user->firebase_uid === $request->target_user_firebase_uid
            || ($this->isManager($user) && $user->manages($request->target_user_firebase_uid));
    }
}
