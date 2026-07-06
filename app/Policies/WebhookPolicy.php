<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Shop\Models\Webhook;
use Illuminate\Auth\Access\HandlesAuthorization;

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
