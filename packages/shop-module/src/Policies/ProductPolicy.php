<?php

namespace Nreljed\ShopModule\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Nreljed\Platform\Models\User;
use Nreljed\ShopModule\Models\Product;

class ProductPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('shop.view');
    }

    public function view(User $user, Product $product): bool
    {
        return $user->hasPermission('shop.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('shop.products.create');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->hasPermission('shop.products.edit');
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->hasPermission('shop.products.delete');
    }
}
