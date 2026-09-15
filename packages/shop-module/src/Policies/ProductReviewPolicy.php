<?php

namespace Nreljed\ShopModule\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Nreljed\Platform\Models\User;
use Nreljed\ShopModule\Models\ProductReview;

class ProductReviewPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('shop.view');
    }

    public function view(User $user, ProductReview $review): bool
    {
        return $user->hasPermission('shop.view');
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function approve(User $user, ProductReview $review): bool
    {
        return $user->hasPermission('shop.reviews.moderate');
    }

    public function delete(User $user, ProductReview $review): bool
    {
        return $user->hasPermission('shop.reviews.moderate');
    }
}
