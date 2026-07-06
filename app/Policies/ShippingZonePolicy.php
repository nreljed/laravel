<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Shop\Models\ShippingZone;
use Illuminate\Auth\Access\HandlesAuthorization;

class ShippingZonePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('shop.view');
    }

    public function view(User $user, ShippingZone $shippingZone): bool
    {
        return $user->hasPermission('shop.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('shop.shipping.manage');
    }

    public function update(User $user, ShippingZone $shippingZone): bool
    {
        return $user->hasPermission('shop.shipping.manage');
    }

    public function delete(User $user, ShippingZone $shippingZone): bool
    {
        return $user->hasPermission('shop.shipping.manage');
    }
}
