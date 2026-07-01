<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\ECommerce\Models\Product;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProductPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('ecommerce.view');
    }

    public function view(User $user, Product $product): bool
    {
        return $user->hasPermission('ecommerce.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('ecommerce.products.create');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->hasPermission('ecommerce.products.edit');
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->hasPermission('ecommerce.products.delete');
    }
}
