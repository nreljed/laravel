<?php

namespace Nreljed\ShopModule\Tests;

use Nreljed\ShopModule\Models\Currency;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductPrice;
use Nreljed\ShopModule\Models\ProductVariation;
use Nreljed\ShopModule\Services\CurrencyService;
use Nreljed\ShopModule\Services\PriceResolver;
use Nreljed\ShopModule\Services\ShopSettingsService;
use PHPUnit\Framework\TestCase;

/**
 * Golden matrix for PriceResolver — the PriceResolver (PR 2) ledger lookup for
 * the storefront.
 *
 * DB-free: the `product_prices` query (`findRow`) and the legacy-column
 * fallback (`legacyView`) are the only DB-coupled seams; the fake resolver
 * overrides them, while the real resolution chain (exact currency → default
 * currency → legacy) and sale-window logic run unchanged.
 */
final class PriceResolverTest extends TestCase
{
    public function test_resolves_exact_currency_ledger_row(): void
    {
        $product = new Product(['id' => 1, 'price' => 100]);
        $row = new ProductPrice([
            'product_id' => 1,
            'currency' => 'USD',
            'price' => 85,
            'compare_price' => 95,
            'sale_price' => null,
        ]);

        $resolved = $this->makeResolver([
            'rows' => ['USD' => $row],
            'default' => 'EUR',
        ])->resolve($product, 'USD');

        $this->assertSame(85.0, $resolved['price']);
        $this->assertSame(95.0, $resolved['compare_price']);
        $this->assertSame('USD', $resolved['currency']);
        $this->assertSame('product_prices', $resolved['source']);
    }

    public function test_falls_back_to_default_currency_row_when_no_exact_row(): void
    {
        $product = new Product(['id' => 1, 'price' => 100]);
        $defaultRow = new ProductPrice([
            'product_id' => 1,
            'currency' => 'EUR',
            'price' => 100,
            'compare_price' => 120,
            'sale_price' => null,
        ]);

        $resolved = $this->makeResolver([
            'rows' => ['EUR' => $defaultRow],
            'default' => 'EUR',
        ])->resolve($product, 'USD');

        $this->assertSame(100.0, $resolved['price']);
        $this->assertSame('EUR', $resolved['currency']);
        $this->assertSame('default_product_prices', $resolved['source']);
    }

    public function test_falls_back_to_legacy_columns_when_no_ledger_rows_at_all(): void
    {
        $product = new Product(['id' => 1, 'price' => 100, 'compare_price' => 125]);

        $resolved = $this->makeResolver([
            'rows' => [],
            'default' => 'EUR',
        ])->resolve($product, 'USD');

        $this->assertSame(100.0, $resolved['price']);
        $this->assertSame(125.0, $resolved['compare_price']);
        $this->assertSame('EUR', $resolved['currency']);
        $this->assertSame('legacy', $resolved['source']);
    }

    public function test_default_currency_requested_uses_default_without_intermediate_fallback(): void
    {
        $product = new Product(['id' => 1, 'price' => 100]);
        $defaultRow = new ProductPrice([
            'product_id' => 1,
            'currency' => 'EUR',
            'price' => 99,
            'compare_price' => null,
            'sale_price' => null,
        ]);

        $resolved = $this->makeResolver([
            'rows' => ['EUR' => $defaultRow],
            'default' => 'EUR',
        ])->resolve($product, 'EUR');

        $this->assertSame(99.0, $resolved['price']);
        $this->assertSame('product_prices', $resolved['source']);
    }

    public function test_resolves_variation_ledger_row_preferring_variation_scope(): void
    {
        $variation = new ProductVariation(['id' => 7, 'product_id' => 1, 'price' => 40]);
        $row = new ProductPrice([
            'product_id' => 1,
            'product_variation_id' => 7,
            'currency' => 'USD',
            'price' => 32,
            'compare_price' => null,
            'sale_price' => null,
        ]);

        $resolved = $this->makeResolver([
            'rows' => ['USD' => $row],
            'default' => 'EUR',
        ])->resolve($variation, 'USD');

        $this->assertSame(32.0, $resolved['price']);
        $this->assertSame('product_prices', $resolved['source']);
    }

    public function test_variation_legacy_fallback_prefers_persisted_active_price(): void
    {
        $variation = new ProductVariation([
            'id' => 7,
            'product_id' => 1,
            'price' => 40,
            'active_price' => 33,
            'compare_price' => 45,
        ]);

        $resolved = $this->makeResolver([
            'rows' => [],
            'default' => 'EUR',
        ])->resolve($variation, 'USD');

        $this->assertSame(33.0, $resolved['price']);
        $this->assertSame(45.0, $resolved['compare_price']);
        $this->assertSame('legacy', $resolved['source']);
    }

    public function test_active_price_uses_ledger_sale_when_sale_is_active(): void
    {
        $product = new Product(['id' => 1, 'price' => 100]);
        // Skip the connection-backed datetime cast in DB-free tests.
        $product->setDateFormat('Y-m-d H:i:s');
        $product->sale_from = now()->subDay();
        $product->sale_to = now()->addDay();
        $row = new ProductPrice([
            'product_id' => 1,
            'currency' => 'USD',
            'price' => 85,
            'compare_price' => null,
            'sale_price' => 70,
        ]);

        $resolver = $this->makeResolver([
            'rows' => ['USD' => $row],
            'default' => 'EUR',
        ]);

        $this->assertSame(70.0, $resolver->activePrice($product, 'USD'));
    }

    public function test_active_price_uses_regular_price_when_sale_window_ended(): void
    {
        $product = new Product(['id' => 1, 'price' => 100]);
        $product->setDateFormat('Y-m-d H:i:s');
        $product->sale_from = now()->subDays(5);
        $product->sale_to = now()->subDay();
        $row = new ProductPrice([
            'product_id' => 1,
            'currency' => 'USD',
            'price' => 85,
            'compare_price' => null,
            'sale_price' => 70,
        ]);

        $resolver = $this->makeResolver([
            'rows' => ['USD' => $row],
            'default' => 'EUR',
        ]);

        $this->assertSame(85.0, $resolver->activePrice($product, 'USD'));
    }

    public function test_active_price_uses_sale_when_both_dates_absent(): void
    {
        $product = new Product(['id' => 1, 'price' => 100]);
        $row = new ProductPrice([
            'product_id' => 1,
            'currency' => 'USD',
            'price' => 85,
            'compare_price' => null,
            'sale_price' => 72,
        ]);

        $resolver = $this->makeResolver([
            'rows' => ['USD' => $row],
            'default' => 'EUR',
        ]);

        $this->assertSame(72.0, $resolver->activePrice($product, 'USD'));
    }

    public function test_hydrate_mutates_product_price_attributes_in_memory(): void
    {
        $product = new Product(['id' => 1, 'price' => 100, 'compare_price' => 125]);
        $row = new ProductPrice([
            'product_id' => 1,
            'currency' => 'USD',
            'price' => 85,
            'compare_price' => 95,
            'sale_price' => null,
        ]);

        $resolver = $this->makeResolver([
            'rows' => ['USD' => $row],
            'default' => 'EUR',
        ]);
        $resolver->hydrate($product, 'USD');

        $this->assertSame(85.0, (float) $product->price);
        $this->assertSame(95.0, (float) $product->compare_price);
        $this->assertNull($product->sale_price);
    }

    public function test_hydrate_applies_resolved_sale_to_product_active_price_accessor(): void
    {
        $product = new Product(['id' => 1, 'price' => 100]);
        $row = new ProductPrice([
            'product_id' => 1,
            'currency' => 'USD',
            'price' => 85,
            'compare_price' => null,
            'sale_price' => 70,
        ]);

        $resolver = $this->makeResolver([
            'rows' => ['USD' => $row],
            'default' => 'EUR',
        ]);
        $resolver->hydrate($product, 'USD');

        $this->assertSame(70.0, (float) $product->sale_price);
        $this->assertSame(70.0, (float) $product->active_price);
        $this->assertTrue($product->on_sale);
    }

    public function test_hydrate_sets_variation_active_price_column(): void
    {
        $variation = new ProductVariation([
            'id' => 7,
            'product_id' => 1,
            'price' => 40,
            'active_price' => 39,
        ]);
        $row = new ProductPrice([
            'product_id' => 1,
            'product_variation_id' => 7,
            'currency' => 'USD',
            'price' => 32,
            'compare_price' => null,
            'sale_price' => 28,
        ]);

        $resolver = $this->makeResolver([
            'rows' => ['USD' => $row],
            'default' => 'EUR',
        ]);
        $resolver->hydrate($variation, 'USD');

        $this->assertSame(32.0, (float) $variation->price);
        $this->assertSame(28.0, (float) $variation->sale_price);
        $this->assertSame(28.0, (float) $variation->active_price);
    }

    public function test_hydrate_default_currency_matches_legacy_columns_when_no_row(): void
    {
        $product = new Product(['id' => 1, 'price' => 100, 'compare_price' => 125]);

        $resolver = $this->makeResolver([
            'rows' => [],
            'default' => 'EUR',
        ]);
        $resolver->hydrate($product, 'EUR');

        $this->assertSame(100.0, (float) $product->price);
        $this->assertSame(125.0, (float) $product->compare_price);
    }

    /*
    |--------------------------------------------------------------------------
    | Fixtures
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array{
     *     rows: array<string, ProductPrice>,
     *     default: string,
     *     active_currency?: array<string, Currency>,
     *     active_first?: bool,
     * }  $map
     */
    private function makeResolver(array $map): PriceResolver
    {
        $currencyService = $this->fakeCurrencyService($map['default']);

        return new class($map['rows'], $currencyService) extends PriceResolver {
            /** @var array<string, ProductPrice> */
            private array $rows;

            public function __construct(array $rows, CurrencyService $currencyService)
            {
                $this->rows = $rows;

                parent::__construct($currencyService);
            }

            /**
             * Rows are keyed by currency; a group lookup reads a
             * `CURRENCY:GROUP_ID` key so group overrides can be faked.
             */
            protected function findRow(Product|ProductVariation $priceable, string $currency, ?int $customerGroupId = null): ?ProductPrice
            {
                if ($customerGroupId !== null) {
                    return $this->rows[$currency.':'.$customerGroupId] ?? null;
                }

                return $this->rows[$currency] ?? null;
            }
        };
    }

    private function fakeCurrencyService(string $defaultCode): CurrencyService
    {
        return new class($defaultCode) extends CurrencyService
        {
            public function __construct(private string $defaultCode)
            {
                parent::__construct(new ShopSettingsService);
            }

            public function defaultCode(): string
            {
                return $this->defaultCode;
            }
        };
    }
}