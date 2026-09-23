<?php

namespace Nreljed\ShopModule\Services;

use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductPrice;
use Nreljed\ShopModule\Models\ProductVariation;

/**
 * Resolves the price ledger (`product_prices`) for a product or variation in
 * a requested currency.
 *
 * Decision 1 (PR 2): the storefront prices are ledger rows per currency, not
 * client-side exchange-rate conversion. Resolution chain:
 *   1. exact `product_prices` row for the requested currency
 *   2. default-currency `product_prices` row
 *   3. legacy `products.price` / `product_variations.price` columns
 *      (fallback only; every migrated row has a default-currency ledger entry)
 *
 * The resolved value is the raw row (or a value-object view of it) so callers
 * can reuse the query once and derive price / compare / sale as needed.
 */
class PriceResolver
{
    public function __construct(
        protected CurrencyService $currencyService,
    ) {}

    /**
     * Resolve the ledger price for a product or variation in a currency.
     *
     * @return array{
     *     price: float,
     *     compare_price: float|null,
     *     sale_price: float|null,
     *     currency: string,
     *     source: 'product_prices'|'default_product_prices'|'legacy',
     * }
     */
    public function resolve(Product|ProductVariation $priceable, ?string $currencyCode = null): array
    {
        $currency = $this->normalizeCurrency($currencyCode);
        $default = $this->currencyService->defaultCode();

        $row = $this->findRow($priceable, $currency);

        if ($row) {
            return $this->rowToView($row, 'product_prices');
        }

        if ($currency !== $default) {
            $row = $this->findRow($priceable, $default);

            if ($row) {
                return $this->rowToView($row, 'default_product_prices');
            }
        }

        return $this->legacyView($priceable, $default);
    }

    /**
     * Resolve the active (sale-aware) price, mirroring
     * `Product::getActivePriceAttribute` / `ProductVariation::effective_price`
     * but against the ledger row resolution.
     */
    public function activePrice(Product|ProductVariation $priceable, ?string $currencyCode = null): float
    {
        $resolved = $this->resolve($priceable, $currencyCode);

        $sale = $resolved['sale_price'];

        if ($sale !== null && $this->saleIsActive($priceable)) {
            return (float) $sale;
        }

        return (float) $resolved['price'];
    }

    /**
     * Mutate the priceable's in-memory price attributes to the resolved ledger
     * view for a currency so Inertia serialization emits already-resolved
     * values instead of the legacy default-currency columns.
     *
     * Product `active_price`/`on_sale` are accessors over these attributes, so
     * they stay correct; variations additionally carry a persisted
     * `active_price` column that is set explicitly.
     */
    public function hydrate(Product|ProductVariation $priceable, ?string $currencyCode = null): void
    {
        $resolved = $this->resolve($priceable, $currencyCode);

        $priceable->price = $resolved['price'];
        $priceable->compare_price = $resolved['compare_price'];
        $priceable->sale_price = $resolved['sale_price'];

        if ($priceable instanceof ProductVariation) {
            $priceable->active_price = $this->activePrice($priceable, $currencyCode);
        }
    }

    protected function normalizeCurrency(?string $currencyCode): string
    {
        return strtoupper($currencyCode ?: $this->currencyService->defaultCode());
    }

    protected function findRow(Product|ProductVariation $priceable, string $currency): ?ProductPrice
    {
        if ($priceable instanceof ProductVariation) {
            return ProductPrice::where('currency', $currency)
                ->where('product_variation_id', $priceable->id)
                ->orderBy('min_quantity')
                ->first();
        }

        return ProductPrice::where('currency', $currency)
            ->where('product_id', $priceable->id)
            ->whereNull('product_variation_id')
            ->orderBy('min_quantity')
            ->first();
    }

    protected function rowToView(ProductPrice $row, string $source): array
    {
        return [
            'price' => (float) $row->price,
            'compare_price' => $row->compare_price !== null ? (float) $row->compare_price : null,
            'sale_price' => $row->sale_price !== null ? (float) $row->sale_price : null,
            'currency' => $row->currency,
            'source' => $source,
        ];
    }

    protected function legacyView(Product|ProductVariation $priceable, string $default): array
    {
        if ($priceable instanceof ProductVariation) {
            $price = $priceable->active_price !== null
                ? $priceable->active_price
                : ($priceable->price ?? $priceable->product?->price ?? 0);

            return [
                'price' => (float) $price,
                'compare_price' => $priceable->compare_price !== null ? (float) $priceable->compare_price : null,
                'sale_price' => $priceable->sale_price !== null ? (float) $priceable->sale_price : null,
                'currency' => $default,
                'source' => 'legacy',
            ];
        }

        return [
            'price' => (float) ($priceable->price ?? 0),
            'compare_price' => $priceable->compare_price !== null ? (float) $priceable->compare_price : null,
            'sale_price' => $priceable->sale_price !== null ? (float) $priceable->sale_price : null,
            'currency' => $default,
            'source' => 'legacy',
        ];
    }

    protected function saleIsActive(Product|ProductVariation $priceable): bool
    {
        $saleFrom = $priceable->sale_from;
        $saleTo = $priceable->sale_to;
        $now = now();

        if ($saleFrom && $saleTo) {
            return $now->between($saleFrom, $saleTo);
        }

        if ($saleFrom) {
            return $now->gte($saleFrom);
        }

        if ($saleTo) {
            return $now->lte($saleTo);
        }

        return $saleFrom === null && $saleTo === null;
    }
}
