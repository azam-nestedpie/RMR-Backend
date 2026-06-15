<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ManagerTeamPolicy
{
    use HandlesAuthorization;

    public function viewTeam(User $user): bool
    {
        return $user->hasPermission('team.view.members')
            && $user->hasRole(['manager_of_reps', 'manager_of_raters']);
    }

    public function manageTeamMembers(User $user): bool
    {
        return $user->hasPermission('team.manage.members')
            && $user->hasRole(['manager_of_reps', 'manager_of_raters']);
    }
}
