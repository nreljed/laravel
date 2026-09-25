<?php

namespace Nreljed\ShopModule\Observers;

use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductVariation;
use Nreljed\ShopModule\Services\PriceLedgerService;

/**
 * Mirrors saved product/variation prices into the `product_prices` ledger
 * (PR 10C).
 *
 * The ledger was previously written only by its own migration, so prices
 * changed in the admin never reached the storefront. Hooking the model events
 * instead of each controller means the product form, bulk `set_price`, CSV
 * import, variation manager and any future write path are all covered.
 */
class SyncsPriceLedger
{
    public function __construct(
        protected PriceLedgerService $ledger,
    ) {}

    /**
     * Every new product or variation gets a public ledger row, whatever it was
     * created with. `wasChanged()` is not usable here: the framework only
     * populates it on update, so it is empty during an insert.
     */
    public function created(Product|ProductVariation $priceable): void
    {
        $this->ledger->sync($priceable);
    }

    /**
     * Updates only rewrite the ledger when a price column actually moved, so
     * editing a description does not touch a price row.
     */
    public function updated(Product|ProductVariation $priceable): void
    {
        if (! $priceable->wasChanged(PriceLedgerService::PRICE_ATTRIBUTES)) {
            return;
        }

        $this->ledger->sync($priceable);
    }
}
