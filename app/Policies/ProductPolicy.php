<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Shop\Models\Product;
use Illuminate\Auth\Access\HandlesAuthorization;

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
