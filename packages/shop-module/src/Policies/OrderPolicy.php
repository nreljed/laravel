<?php

namespace Nreljed\ShopModule\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Nreljed\Platform\Models\User;
use Nreljed\ShopModule\Models\Order;

class OrderPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('shop.orders.view');
    }

    /**
     * Whether this member of staff may open any order.
     */
    public function view(User $user, Order $order): bool
    {
        return $user->hasPermission('shop.orders.view');
    }

    /**
     * Whether this user may read this order on a self-service surface.
     *
     * `view` asks "is this member of staff allowed to open any order?". The
     * customer-facing surfaces ask a different question - "is this my order?" -
     * and the two of them were answering it two different ways. The account page
     * compared `user_id` inline, while the JSON API bound any order straight off
     * the URL and returned it, so any signed-in customer could read anyone
     * else's name, email, both addresses and item lines by incrementing a
     * number. Both now come through here so the rule has one home.
     *
     * Deliberately "my order" and not "my order or staff": `api.orders.index`
     * only ever returns the caller's own orders, so granting a staff ability
     * here would let a single-order fetch read an order that the same API
     * refuses to list. Staff have the dashboard.
     */
    public function viewOwn(User $user, Order $order): bool
    {
        // A guest order has no `user_id` to compare against, so it can never be
        // read here. Those are reached through the confirmation token instead
        // (see `CheckoutController::authorizeOrderView`), and a deleted account
        // leaves `user_id` null via `nullOnDelete` rather than inheriting the
        // old behaviour of making the order world-readable.
        return $order->user_id !== null
            && (int) $order->user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('shop.orders.edit');
    }

    public function update(User $user, Order $order): bool
    {
        return $user->hasPermission('shop.orders.edit');
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->hasPermission('shop.orders.edit');
    }

    public function refund(User $user, Order $order): bool
    {
        return $user->hasPermission('shop.orders.refund');
    }
}
