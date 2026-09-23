<?php

namespace Nreljed\ShopModule\Pipelines\Checkout;

use Closure;
use Nreljed\ShopModule\Pipelines\CheckoutContext;
use Nreljed\ShopModule\Services\InventoryService;

class RecordInventory
{
    public function __construct(protected InventoryService $inventory) {}

    public function handle(CheckoutContext $context, Closure $next)
    {
        $this->inventory->recordSaleForOrder($context->order, $context->user?->id);

        return $next($context);
    }
}
