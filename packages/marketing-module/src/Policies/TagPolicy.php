<?php

namespace Nreljed\MarketingModule\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Nreljed\MarketingModule\Models\Tag;
use Nreljed\Platform\Models\User;

/**
 * Tags are shared audience labels, so the policy is permission-only.
 */
class TagPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('marketing.tags.manage');
    }

    public function view(User $user, Tag $tag): bool
    {
        return $user->hasPermission('marketing.tags.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('marketing.tags.manage');
    }

    public function update(User $user, Tag $tag): bool
    {
        return $user->hasPermission('marketing.tags.manage');
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $user->hasPermission('marketing.tags.manage');
    }
}
