<?php

namespace Nreljed\ShopModule\Services;

use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\DB;
use Nreljed\Platform\Models\User;
use Nreljed\ShopModule\Models\Cart;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Pipelines\CheckoutContext;

class CheckoutService
{
    public function processCheckout(Cart $cart, array $validated, ?User $user, ?Order $provisionalOrder = null): Order
    {
        $cart->loadMissing('items.product', 'items.variation');

        $context = DB::transaction(function () use ($cart, $validated, $user, $provisionalOrder) {
            return (new Pipeline(app()))
                ->send(new CheckoutContext($cart, $validated, $user, $provisionalOrder))
                ->through(config('shop.pipelines.checkout'))
                ->thenReturn();
        });

        return $context->order;
    }
}
