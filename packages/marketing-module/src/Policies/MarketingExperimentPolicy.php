<?php

namespace Nreljed\MarketingModule\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Nreljed\MarketingModule\Models\MarketingExperiment;
use Nreljed\Platform\Models\User;

class MarketingExperimentPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('marketing.plans.manage');
    }

    public function view(User $user, MarketingExperiment $experiment): bool
    {
        return $user->hasPermission('marketing.plans.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('marketing.plans.manage');
    }

    public function update(User $user, MarketingExperiment $experiment): bool
    {
        return $user->hasPermission('marketing.plans.manage');
    }

    public function delete(User $user, MarketingExperiment $experiment): bool
    {
        return $user->hasPermission('marketing.plans.manage');
    }
}
