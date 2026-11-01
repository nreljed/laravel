<?php

namespace Nreljed\ShopModule\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CartAbandoned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $cartToken,
        public ?string $customerEmail = null,
        public ?int $customerId = null,
        public array $cartData = [],
    ) {
    }
}

