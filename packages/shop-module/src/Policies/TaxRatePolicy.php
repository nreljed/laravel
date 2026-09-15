<?php

namespace Nreljed\ShopModule\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Nreljed\Platform\Models\User;
use Nreljed\ShopModule\Models\TaxRate;

class TaxRatePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('shop.view');
    }

    public function view(User $user, TaxRate $taxRate): bool
    {
        return $user->hasPermission('shop.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('shop.taxes.manage');
    }

    public function update(User $user, TaxRate $taxRate): bool
    {
        return $user->hasPermission('shop.taxes.manage');
    }

    public function delete(User $user, TaxRate $taxRate): bool
    {
        return $user->hasPermission('shop.taxes.manage');
    }
}
