<?php

namespace Nreljed\MarketingModule\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Nreljed\MarketingModule\Models\Segment;
use Nreljed\Platform\Models\User;

/**
 * Segments are shared audience definitions, so the policy is permission-only.
 */
class SegmentPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('marketing.segments.manage');
    }

    public function view(User $user, Segment $segment): bool
    {
        return $user->hasPermission('marketing.segments.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('marketing.segments.manage');
    }

    public function update(User $user, Segment $segment): bool
    {
        return $user->hasPermission('marketing.segments.manage');
    }

    public function delete(User $user, Segment $segment): bool
    {
        return $user->hasPermission('marketing.segments.manage');
    }
}
