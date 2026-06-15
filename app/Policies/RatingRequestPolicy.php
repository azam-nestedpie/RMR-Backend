<?php

namespace App\Policies;

use App\Models\RatingRequest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RatingRequestPolicy
{
    use HandlesAuthorization;

    public function send(User $user, User $target): bool
    {
        return ($user->hasPermission('send rating request') || $user->hasPermission('ratings.request.self'))
            && $user->hasRole('rep')
            && $target->hasRole('rater');
    }

    public function sendOnBehalf(User $user, User $behalfUser, User $target): bool
    {
        return $user->hasPermission('team.ratings.request_on_behalf')
            && $user->hasRole('manager_of_reps')
            && $user->manages($behalfUser->firebase_uid)
            && $behalfUser->hasRole('rep')
            && $target->hasRole('rater');
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
            || ($user->hasRole('manager_of_reps') && $user->manages($request->requester_firebase_uid));
    }

    public function viewTeamRatings(User $user): bool
    {
        return $user->hasPermission('dashboard.view.team')
            && $user->hasRole(['manager_of_reps', 'manager_of_raters']);
    }

    private function canRespondToRequest(User $user, RatingRequest $request): bool
    {
        return $user->firebase_uid === $request->target_user_firebase_uid
            || ($user->hasRole('manager_of_raters') && $user->manages($request->target_user_firebase_uid));
    }
}
