<?php

namespace Nreljed\MarketingModule\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Nreljed\MarketingModule\Models\Campaign;
use Nreljed\Platform\Models\User;

/**
 * Campaigns are shared planning objects, so the policy is permission-only.
 */
class CampaignPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('marketing.campaigns.manage');
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->hasPermission('marketing.campaigns.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('marketing.campaigns.manage');
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->hasPermission('marketing.campaigns.manage');
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->hasPermission('marketing.campaigns.manage');
    }
}
