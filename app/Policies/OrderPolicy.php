<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\ECommerce\Models\Order;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrderPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('ecommerce.orders.view');
    }

    public function view(User $user, Order $order): bool
    {
        return $user->hasPermission('ecommerce.orders.view');
    }

    public function update(User $user, Order $order): bool
    {
        return $user->hasPermission('ecommerce.orders.edit');
    }
}
