<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Shop\Models\ShippingMethod;
use Illuminate\Auth\Access\HandlesAuthorization;

class ShippingMethodPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('shop.view');
    }

    public function view(User $user, ShippingMethod $shippingMethod): bool
    {
        return $user->hasPermission('shop.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('shop.shipping.manage');
    }

    public function update(User $user, ShippingMethod $shippingMethod): bool
    {
        return $user->hasPermission('shop.shipping.manage');
    }

    public function delete(User $user, ShippingMethod $shippingMethod): bool
    {
        return $user->hasPermission('shop.shipping.manage');
    }
}
