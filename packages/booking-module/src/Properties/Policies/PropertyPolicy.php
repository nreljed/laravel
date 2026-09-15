<?php

namespace Nreljed\BookingModule\Properties\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Nreljed\BookingModule\Properties\Models\Property;
use Nreljed\Platform\Models\User;

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
