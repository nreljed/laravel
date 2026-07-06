<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Shop\Models\ProductReview;
use Illuminate\Auth\Access\HandlesAuthorization;

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
