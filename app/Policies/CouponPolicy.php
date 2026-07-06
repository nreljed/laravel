<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Shop\Models\Coupon;
use Illuminate\Auth\Access\HandlesAuthorization;

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
