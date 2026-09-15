<?php

namespace Nreljed\ShopModule\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Nreljed\Platform\Models\User;
use Nreljed\ShopModule\Models\Coupon;

class CouponPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('shop.view');
    }

    public function view(User $user, Coupon $coupon): bool
    {
        return $user->hasPermission('shop.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('shop.coupons.manage');
    }

    public function update(User $user, Coupon $coupon): bool
    {
        return $user->hasPermission('shop.coupons.manage');
    }

    public function delete(User $user, Coupon $coupon): bool
    {
        return $user->hasPermission('shop.coupons.manage');
    }
}
