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
 * PR 10C adds the customer-group dimension on top: when the shopper resolves
 * to a group that is allowed business pricing, a group row is preferred over
 * the public row. The chain is currency-first, then group:
 *   1. group row for the requested currency
 *   2. public row for the requested currency
 *   3. group row for the default currency
 *   4. public row for the default currency
 *   5. legacy columns
 *
 * Currency before group is deliberate: a shopper viewing prices in USD must
 * never be shown a group row denominated in the default currency, because the
 * amount would then be labelled with the wrong currency.
 *
 * The resolved value is the raw row (or a value-object view of it) so callers
 * can reuse the query once and derive price / compare / sale as needed.
 */
class PriceResolver
{
    /** @var array<string, bool> connection name => whether the ledger exists */
    protected array $ledgerExists = [];

    public function __construct(
        protected CurrencyService $currencyService,
    ) {}

    /**
     * Resolve the ledger price for a product or variation in a currency.
     *
     * @param  int|null  $customerGroupId  group override to prefer, if any
     * @return array{
     *     price: float,
     *     compare_price: float|null,
     *     sale_price: float|null,
     *     currency: string,
     *     source: 'group_product_prices'|'product_prices'|'group_default_product_prices'|'default_product_prices'|'legacy',
     * }
     */
    public function resolve(Product|ProductVariation $priceable, ?string $currencyCode = null, ?int $customerGroupId = null): array
    {
        $currency = $this->normalizeCurrency($currencyCode);
        $default = $this->currencyService->defaultCode();

        // Currency first, then group: a shopper viewing prices in USD must
        // never be shown a group row denominated in the default currency, so
        // a row in the requested currency always beats a default-currency row
        // regardless of which group authored it.
        $row = $this->findRow($priceable, $currency, $customerGroupId);

        if ($row) {
            return $this->rowToView($row, $customerGroupId !== null ? 'group_product_prices' : 'product_prices');
        }

        $row = $this->findRow($priceable, $currency);

        if ($row) {
            return $this->rowToView($row, 'product_prices');
        }

        if ($currency !== $default) {
            $groupRow = $customerGroupId !== null ? $this->findRow($priceable, $default, $customerGroupId) : null;
            $publicRow = $this->findRow($priceable, $default);

            if ($groupRow) {
                return $this->rowToView($groupRow, 'group_default_product_prices');
            }

            if ($publicRow) {
                return $this->rowToView($publicRow, 'default_product_prices');
            }
        }

        return $this->legacyView($priceable, $default);
    }

    /**
     * Resolve the active (sale-aware) price, mirroring
     * `Product::getActivePriceAttribute` / `ProductVariation::effective_price`
     * but against the ledger row resolution.
     */
    public function activePrice(Product|ProductVariation $priceable, ?string $currencyCode = null, ?int $customerGroupId = null): float
    {
        $resolved = $this->resolve($priceable, $currencyCode, $customerGroupId);

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
    public function hydrate(Product|ProductVariation $priceable, ?string $currencyCode = null, ?int $customerGroupId = null): void
    {
        $resolved = $this->resolve($priceable, $currencyCode, $customerGroupId);

        $priceable->price = $resolved['price'];
        $priceable->compare_price = $resolved['compare_price'];
        $priceable->sale_price = $resolved['sale_price'];

        if ($priceable instanceof ProductVariation) {
            $priceable->active_price = $this->activePrice($priceable, $currencyCode, $customerGroupId);
        }
    }

    protected function normalizeCurrency(?string $currencyCode): string
    {
        return strtoupper($currencyCode ?: $this->currencyService->defaultCode());
    }

    protected function findRow(Product|ProductVariation $priceable, string $currency, ?int $customerGroupId = null): ?ProductPrice
    {
        // The ledger can legitimately be absent (module installed with
        // migrations off, or a package test harness with a minimal schema).
        // Falling through to the legacy columns keeps the storefront serving
        // prices instead of failing the request.
        if (! $this->ledgerExists($priceable)) {
            return null;
        }

        $query = ProductPrice::query()
            ->where('currency', $currency)
            ->orderBy('min_quantity');

        if ($priceable instanceof ProductVariation) {
            $query->where('product_variation_id', $priceable->id);
        } else {
            $query->where('product_id', $priceable->id)->whereNull('product_variation_id');
        }

        if ($customerGroupId !== null) {
            $query->where('customer_group_id', $customerGroupId);
        } else {
            $query->whereNull('customer_group_id');
        }

        return $query->first();
    }

    /**
     * Whether the ledger table exists, memoised per instance (and per
     * connection) for the lifetime of the resolver. The ledger can legitimately
     * be absent: the module may be installed with migrations off, or a package
     * test harness may build only a minimal schema. Falling through to the
     * legacy columns keeps the storefront serving prices instead of failing the
     * request.
     */
    protected function ledgerExists(Product|ProductVariation $priceable): bool
    {
        $connection = $priceable->getConnection();

        return $this->ledgerExists[$connection->getName()] ??= $connection
            ->getSchemaBuilder()
            ->hasTable('product_prices');
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