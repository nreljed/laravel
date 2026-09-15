<?php

namespace Nreljed\ShopModule\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Nreljed\Platform\Models\User;
use Nreljed\ShopModule\Models\Order;

class OrderPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('shop.orders.view');
    }

    public function view(User $user, Order $order): bool
    {
        return $user->hasPermission('shop.orders.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('shop.orders.edit');
    }

    public function update(User $user, Order $order): bool
    {
        return $user->hasPermission('shop.orders.edit');
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->hasPermission('shop.orders.edit');
    }

    public function refund(User $user, Order $order): bool
    {
        return $user->hasPermission('shop.orders.refund');
    }
}
