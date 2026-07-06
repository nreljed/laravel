<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Shop\Models\Order;
use Illuminate\Auth\Access\HandlesAuthorization;

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
