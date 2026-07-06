<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Shop\Models\Currency;
use Illuminate\Auth\Access\HandlesAuthorization;

class CurrencyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('shop.view');
    }

    public function view(User $user, Currency $currency): bool
    {
        return $user->hasPermission('shop.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('shop.currencies.manage');
    }

    public function update(User $user, Currency $currency): bool
    {
        return $user->hasPermission('shop.currencies.manage');
    }

    public function delete(User $user, Currency $currency): bool
    {
        return $user->hasPermission('shop.currencies.manage');
    }
}
