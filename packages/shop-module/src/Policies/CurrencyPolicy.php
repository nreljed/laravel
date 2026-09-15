<?php

namespace Nreljed\ShopModule\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Nreljed\Platform\Models\User;
use Nreljed\ShopModule\Models\Currency;

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
