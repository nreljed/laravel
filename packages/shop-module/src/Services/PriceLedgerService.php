<?php

namespace Nreljed\ShopModule\Services;

use Illuminate\Support\Facades\Schema;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductPrice;
use Nreljed\ShopModule\Models\ProductVariation;

/**
 * Single writer for the `product_prices` ledger (PR 10C).
 *
 * The ledger used to be written only by its own migration, so every admin
 * price edit (product form, bulk `set_price`, CSV import, variation manager)
 * left the storefront reading stale rows. Every legacy price column is the
 * default-currency entry for its own product/variation, so this service
 * mirrors those columns into the ledger and owns customer-group overrides.
 *
 * It is driven by {@see \Nreljed\ShopModule\Observers\SyncsPriceLedger} so no
 * write path can forget it, and by the product form for group rows.
 */
class PriceLedgerService
{
    /** Product/variation attributes that map onto ledger columns. */
    public const PRICE_ATTRIBUTES = ['price', 'compare_price', 'sale_price'];

    public function __construct(
        protected CurrencyService $currencyService,
    ) {}

    /**
     * Mirror a product's or variation's default-currency legacy columns into
     * the ledger. The group-less row is always refreshed; group override rows
     * are left untouched (they are authored explicitly).
     */
    public function sync(Product|ProductVariation $priceable): void
    {
        if (! $this->ledgerExists()) {
            return;
        }

        $currency = $this->defaultCode();

        $isVariation = $priceable instanceof ProductVariation;

        ProductPrice::query()->updateOrCreate(
            [
                'product_id' => $isVariation ? (int) $priceable->product_id : (int) $priceable->id,
                'product_variation_id' => $isVariation ? $priceable->id : null,
                'customer_group_id' => null,
                'currency' => $currency,
                'min_quantity' => 1,
            ],
            [
                'price' => (float) $priceable->price,
                'compare_price' => $priceable->compare_price !== null ? (float) $priceable->compare_price : null,
                'sale_price' => $priceable->sale_price !== null ? (float) $priceable->sale_price : null,
            ],
        );
    }

    /**
     * Replace the customer-group price rows of a product with the given set.
     *
     * Groups present in `$rows` are upserted; group rows missing from it are
     * deleted, so removing a row in the admin form clears the override instead
     * of silently keeping a stale price. Rows in a non-default currency are
     * preserved (they are authored outside the single-currency form).
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function syncGroupRows(Product $product, array $rows): void
    {
        if (! $this->ledgerExists()) {
            return;
        }

        $currency = $this->defaultCode();
        $keep = [];

        foreach ($rows as $row) {
            $groupId = (int) ($row['customer_group_id'] ?? 0);

            if ($groupId <= 0) {
                continue;
            }

            $minQuantity = max(1, (int) ($row['min_quantity'] ?? 1));
            $keep[] = $groupId.':'.$minQuantity;

            ProductPrice::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'product_variation_id' => null,
                    'customer_group_id' => $groupId,
                    'currency' => $currency,
                    'min_quantity' => $minQuantity,
                ],
                [
                    'price' => (float) ($row['price'] ?? 0),
                    'compare_price' => isset($row['compare_price']) ? (float) $row['compare_price'] : null,
                    'sale_price' => isset($row['sale_price']) ? (float) $row['sale_price'] : null,
                ],
            );
        }

        ProductPrice::query()
            ->where('product_id', $product->id)
            ->whereNull('product_variation_id')
            ->whereNotNull('customer_group_id')
            ->where('currency', $currency)
            ->get()
            ->each(function (ProductPrice $price) use ($keep) {
                if (! in_array($price->customer_group_id.':'.$price->min_quantity, $keep, true)) {
                    $price->delete();
                }
            });
    }

    /**
     * Ledger rows for a product shaped for the admin form. The group-less row
     * is excluded: it mirrors the legacy price columns already on the form.
     *
     * @return array<int, array<string, mixed>>
     */
    public function groupRowsFor(Product $product): array
    {
        if (! $this->ledgerExists()) {
            return [];
        }

        return ProductPrice::query()
            ->where('product_id', $product->id)
            ->whereNull('product_variation_id')
            ->whereNotNull('customer_group_id')
            ->orderBy('customer_group_id')
            ->get()
            ->map(fn (ProductPrice $price) => [
                'customer_group_id' => (int) $price->customer_group_id,
                'currency' => $price->currency,
                'price' => (float) $price->price,
                'compare_price' => $price->compare_price !== null ? (float) $price->compare_price : null,
                'sale_price' => $price->sale_price !== null ? (float) $price->sale_price : null,
                'min_quantity' => (int) $price->min_quantity,
            ])
            ->all();
    }

    /**
     * Delete every ledger row of a product, group overrides included.
     */
    public function forget(Product $product): void
    {
        if (! $this->ledgerExists()) {
            return;
        }

        ProductPrice::query()->where('product_id', $product->id)->delete();
    }

    /**
     * Public rows whose stored price no longer matches the legacy column — the
     * drift this service exists to prevent. Group rows are excluded.
     *
     * @return array<int, array<string, mixed>>
     */
    public function driftedRows(): array
    {
        if (! $this->ledgerExists()) {
            return [];
        }

        return ProductPrice::query()
            ->join('products', 'products.id', '=', 'product_prices.product_id')
            ->whereNull('product_prices.product_variation_id')
            ->whereNull('product_prices.customer_group_id')
            ->where('product_prices.min_quantity', 1)
            ->where('product_prices.currency', $this->defaultCode())
            ->whereColumn('products.price', '!=', 'product_prices.price')
            ->get([
                'product_prices.id',
                'product_prices.product_id',
                'product_prices.price as ledger_price',
                'products.price as product_price',
            ])
            ->map(fn (ProductPrice $price) => [
                'id' => (int) $price->id,
                'product_id' => (int) $price->product_id,
                'ledger_price' => (float) $price->getAttribute('ledger_price'),
                'product_price' => (float) $price->getAttribute('product_price'),
            ])
            ->all();
    }

    /**
     * The ledger may legitimately be absent: package test harnesses build a
     * minimal schema, and an app can install the module with migrations off.
     */
    protected function ledgerExists(): bool
    {
        return Schema::hasTable('product_prices');
    }

    protected function defaultCode(): string
    {
        return $this->currencyService->defaultCode();
    }
}
