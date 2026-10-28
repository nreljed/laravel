<?php

namespace Nreljed\Platform\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Nreljed\Platform\Models\Role;
use Nreljed\Platform\Models\User;

class RolePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage-roles');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasPermission('manage-roles');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage-roles');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasPermission('manage-roles');
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->hasPermission('manage-roles');
    }

    public function duplicate(User $user, Role $role): bool
    {
        return $user->hasPermission('manage-roles');
    }
}
