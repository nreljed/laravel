<?php

namespace Nreljed\ShopModule\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Nreljed\Platform\Models\User;
use Nreljed\ShopModule\Models\Webhook;

class WebhookPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('shop.webhooks.manage');
    }

    public function view(User $user, Webhook $webhook): bool
    {
        return $user->hasPermission('shop.webhooks.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('shop.webhooks.manage');
    }

    public function update(User $user, Webhook $webhook): bool
    {
        return $user->hasPermission('shop.webhooks.manage');
    }

    public function delete(User $user, Webhook $webhook): bool
    {
        return $user->hasPermission('shop.webhooks.manage');
    }

    public function test(User $user, Webhook $webhook): bool
    {
        return $user->hasPermission('shop.webhooks.manage');
    }
}
