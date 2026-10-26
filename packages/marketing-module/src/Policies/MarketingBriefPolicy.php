<?php

namespace Nreljed\MarketingModule\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Nreljed\MarketingModule\Models\MarketingBrief;
use Nreljed\Platform\Models\User;

class MarketingBriefPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('marketing.plans.manage');
    }

    public function view(User $user, MarketingBrief $brief): bool
    {
        return $user->hasPermission('marketing.plans.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('marketing.plans.manage');
    }

    public function update(User $user, MarketingBrief $brief): bool
    {
        return $user->hasPermission('marketing.plans.manage');
    }

    public function delete(User $user, MarketingBrief $brief): bool
    {
        return $user->hasPermission('marketing.plans.manage');
    }
}
