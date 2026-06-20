<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Properties\Models\Property;
use Illuminate\Auth\Access\HandlesAuthorization;

class PropertyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('properties.view');
    }

    public function view(User $user, Property $property): bool
    {
        return $user->id === $property->user_id || $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('properties.create');
    }

    public function update(User $user, Property $property): bool
    {
        return $user->id === $property->user_id || $user->isAdmin();
    }

    public function delete(User $user, Property $property): bool
    {
        return $user->id === $property->user_id || $user->isAdmin();
    }
}
