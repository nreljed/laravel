<?php

namespace Nreljed\MarketingModule\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Nreljed\MarketingModule\Models\MarketingPlan;
use Nreljed\Platform\Models\User;

class MarketingPlanPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('marketing.plans.manage');
    }

    public function view(User $user, MarketingPlan $plan): bool
    {
        return $user->hasPermission('marketing.plans.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('marketing.plans.manage');
    }

    public function update(User $user, MarketingPlan $plan): bool
    {
        return $user->hasPermission('marketing.plans.manage');
    }

    public function delete(User $user, MarketingPlan $plan): bool
    {
        return $user->hasPermission('marketing.plans.manage');
    }
}
