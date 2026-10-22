<?php

namespace Nreljed\MarketingModule\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Nreljed\MarketingModule\Models\SocialPost;
use Nreljed\Platform\Models\User;

/**
 * Publishing is a workspace capability, so the policy is permission-only -
 * the same shape the audience policies use. Which accounts a user may target
 * is decided by the composer's account list, not by the post record.
 */
class SocialPostPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('marketing.posts.manage');
    }

    public function view(User $user, SocialPost $post): bool
    {
        return $user->hasPermission('marketing.posts.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('marketing.posts.manage');
    }

    public function update(User $user, SocialPost $post): bool
    {
        return $user->hasPermission('marketing.posts.manage');
    }

    public function delete(User $user, SocialPost $post): bool
    {
        return $user->hasPermission('marketing.posts.manage');
    }

    public function publish(User $user, SocialPost $post): bool
    {
        return $user->hasPermission('marketing.posts.manage');
    }
}
