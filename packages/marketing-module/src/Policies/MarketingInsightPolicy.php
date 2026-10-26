<?php

namespace Nreljed\MarketingModule\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Nreljed\MarketingModule\Models\MarketingInsight;
use Nreljed\Platform\Models\User;

class MarketingInsightPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('marketing.plans.manage') || $user->hasPermission('marketing.insights.apply');
    }

    public function view(User $user, MarketingInsight $insight): bool
    {
        return $user->hasPermission('marketing.plans.manage') || $user->hasPermission('marketing.insights.apply');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('marketing.plans.manage');
    }

    public function update(User $user, MarketingInsight $insight): bool
    {
        return $user->hasPermission('marketing.plans.manage') || $user->hasPermission('marketing.insights.apply');
    }

    public function delete(User $user, MarketingInsight $insight): bool
    {
        return $user->hasPermission('marketing.plans.manage');
    }
}
