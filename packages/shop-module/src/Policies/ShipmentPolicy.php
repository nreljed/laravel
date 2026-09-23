<?php

namespace Nreljed\ShopModule\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Nreljed\Platform\Models\User;
use Nreljed\ShopModule\Models\Shipment;

class ShipmentPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('shop.shipments.manage');
    }

    public function view(User $user, Shipment $shipment): bool
    {
        return $user->hasPermission('shop.shipments.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('shop.shipments.manage');
    }

    public function update(User $user, Shipment $shipment): bool
    {
        return $user->hasPermission('shop.shipments.manage');
    }

    public function delete(User $user, Shipment $shipment): bool
    {
        return $user->hasPermission('shop.shipments.manage');
    }
}
