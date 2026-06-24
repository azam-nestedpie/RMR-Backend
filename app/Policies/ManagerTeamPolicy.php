<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ManagerTeamPolicy
{
    use HandlesAuthorization;

    public function viewTeam(User $user): bool
    {
        return $user->hasPermission('team.view.members')
            && $user->hasRole([Role::MANAGER_OF_REPRESENTATIVES, Role::MANAGER_OF_RATERS]);
    }

    public function manageTeamMembers(User $user): bool
    {
        return $user->hasPermission('team.manage.members')
            && $user->hasRole([Role::MANAGER_OF_REPRESENTATIVES, Role::MANAGER_OF_RATERS]);
    }
}
