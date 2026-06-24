<?php

namespace App\Policies;

use App\Models\RatingRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RatingRequestPolicy
{
    use HandlesAuthorization;

    public function send(User $user, User $target): bool
    {
        return ($user->hasPermission('send rating request') || $user->hasPermission('ratings.request.self'))
            && $user->hasRole(Role::REPRESENTATIVE)
            && $target->hasRole(Role::RATER);
    }

    public function sendOnBehalf(User $user, User $behalfUser, User $target): bool
    {
        return $user->hasPermission('team.ratings.request_on_behalf')
            && $user->hasRole(Role::MANAGER_OF_REPRESENTATIVES)
            && $user->manages($behalfUser->firebase_uid)
            && $behalfUser->hasRole(Role::REPRESENTATIVE)
            && $target->hasRole(Role::RATER);
    }

    public function accept(User $user, RatingRequest $request): bool
    {
        return $this->canRespondToRequest($user, $request);
    }

    public function reject(User $user, RatingRequest $request): bool
    {
        return $this->canRespondToRequest($user, $request);
    }

    public function cancel(User $user, RatingRequest $request): bool
    {
        return $user->firebase_uid === $request->requester_firebase_uid
            || ($user->hasRole(Role::MANAGER_OF_REPRESENTATIVES) && $user->manages($request->requester_firebase_uid));
    }

    public function viewTeamRatings(User $user): bool
    {
        return $user->hasPermission('dashboard.view.team')
            && $user->hasRole([Role::MANAGER_OF_REPRESENTATIVES, Role::MANAGER_OF_RATERS]);
    }

    private function canRespondToRequest(User $user, RatingRequest $request): bool
    {
        return $user->firebase_uid === $request->target_user_firebase_uid
            || ($user->hasRole(Role::MANAGER_OF_RATERS) && $user->manages($request->target_user_firebase_uid));
    }
}
