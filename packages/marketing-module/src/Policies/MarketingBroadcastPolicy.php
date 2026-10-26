<?php

namespace Nreljed\MarketingModule\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Nreljed\MarketingModule\Models\MarketingBroadcast;
use Nreljed\Platform\Models\User;

class MarketingBroadcastPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('marketing.broadcasts.manage');
    }

    public function view(User $user, MarketingBroadcast $broadcast): bool
    {
        return $user->hasPermission('marketing.broadcasts.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('marketing.broadcasts.manage');
    }

    public function update(User $user, MarketingBroadcast $broadcast): bool
    {
        return $user->hasPermission('marketing.broadcasts.manage');
    }

    public function delete(User $user, MarketingBroadcast $broadcast): bool
    {
        return $user->hasPermission('marketing.broadcasts.manage');
    }
}
