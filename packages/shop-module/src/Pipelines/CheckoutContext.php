<?php

namespace Nreljed\ShopModule\Pipelines;

use Nreljed\Platform\Models\User;
use Nreljed\ShopModule\Models\Cart;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Models\ShippingMethod;

class CheckoutContext
{
    public ?ShippingMethod $shippingMethod = null;

    public ?array $quote = null;

    public ?Order $order = null;

    public function __construct(
        public Cart $cart,
        public array $validated,
        public ?User $user,
        public ?Order $provisionalOrder = null,
    ) {}
}