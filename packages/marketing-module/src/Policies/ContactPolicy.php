<?php

namespace Nreljed\MarketingModule\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Nreljed\MarketingModule\Models\Contact;
use Nreljed\Platform\Models\User;

/**
 * Contacts are shared audience records rather than per-user records, so the
 * policy is permission-only.
 */
class ContactPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('marketing.contacts.manage');
    }

    public function view(User $user, Contact $contact): bool
    {
        return $user->hasPermission('marketing.contacts.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('marketing.contacts.manage');
    }

    public function update(User $user, Contact $contact): bool
    {
        return $user->hasPermission('marketing.contacts.manage');
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $user->hasPermission('marketing.contacts.manage');
    }
}
