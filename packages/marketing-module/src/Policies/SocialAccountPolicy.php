<?php

namespace Nreljed\MarketingModule\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Nreljed\MarketingModule\Models\SocialAccount;
use Nreljed\Platform\Models\User;

/**
 * A social account belongs to the user who connected it. The permission
 * gates *what* you may do; the ownership check gates *whose* accounts you
 * may do it to.
 */
class SocialAccountPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('marketing.accounts.manage');
    }

    public function view(User $user, SocialAccount $account): bool
    {
        return $this->owns($user, $account);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('marketing.accounts.manage');
    }

    public function update(User $user, SocialAccount $account): bool
    {
        return $this->owns($user, $account);
    }

    public function delete(User $user, SocialAccount $account): bool
    {
        return $this->owns($user, $account);
    }

    private function owns(User $user, SocialAccount $account): bool
    {
        return $user->hasPermission('marketing.accounts.manage')
            && (int) $account->user_id === (int) $user->id;
    }
}
