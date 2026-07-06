<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Shop\Models\TaxRate;
use Illuminate\Auth\Access\HandlesAuthorization;

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
