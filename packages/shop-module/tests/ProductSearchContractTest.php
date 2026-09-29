<?php

namespace Nreljed\ShopModule\Tests;

use Nreljed\ShopModule\Contracts\ProductSearchDriver;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Search\DatabaseProductSearchDriver;
use Nreljed\ShopModule\Services\ProductSearch;
use Nreljed\ShopModule\Tests\Concerns\BootsShopSchema;
use PHPUnit\Framework\TestCase;

/**
 * The product search service is the single entry point the storefront, the
 * public catalog API and the admin product list all go through.
 *
 * Each surface used to build its own `LIKE '%...%'` predicate, which let the
 * term handling drift: the API trimmed and capped, the storefront and admin
 * did not, and none of them escaped a LIKE wildcard - so a shopper searching
 * for a literal `50%` or `_` matched dramatically more than they asked for.
 * This suite pins the service contract all three rely on now.
 */
final class ProductSearchContractTest extends TestCase
{
    use BootsShopSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootShopSchema();
        $this->resetShopTables();
    }

    public function test_a_term_is_trimmed_and_capped(): void
    {
        $search = new ProductSearch(new DatabaseProductSearchDriver);

        $this->assertSame('boots', $search->term('  boots  '), 'Whitespace around a term should not change the match.');

        $this->assertNull($search->term(''), 'An empty term should add no filter, not filter on empty.');
        $this->assertNull($search->term('   '));

        $this->assertSame(
            100,
            mb_strlen((string) $search->term(str_repeat('a', 5000))),
            'The term is interpolated into a LIKE pattern; an unbounded string is unbounded scan work per caller.'
        );
    }

    public function test_the_configured_driver_is_the_database_driver(): void
    {
        $this->assertSame(
            DatabaseProductSearchDriver::class,
            config('shop.search.driver'),
            'The default driver configuration is the database scan.'
        );
        $this->assertInstanceOf(DatabaseProductSearchDriver::class, app(ProductSearchDriver::class));
        $this->assertInstanceOf(ProductSearch::class, app(ProductSearch::class));
    }

    public function test_the_service_matches_on_the_given_fields(): void
    {
        $boots = $this->product(['name' => 'Red Boots', 'sku' => 'RB-2000']);
        $this->product(['name' => 'Blue Shirt', 'sku' => 'BS-1']);

        $search = app(ProductSearch::class);

        $byName = $search->applyTo(Product::query(), 'boots', ['name', 'sku'])->pluck('id');
        $this->assertSame([$boots->id], $byName->all(), 'A name search should match the product by name.');

        $bySku = $search->applyTo(Product::query(), 'RB-2000', ['name', 'sku'])->pluck('id');
        $this->assertSame([$boots->id], $bySku->all(), 'The field list is what makes SKU searchable.');
    }

    public function test_a_null_term_leaves_the_query_unchanged(): void
    {
        $this->product(['name' => 'Red Boots']);
        $this->product(['name' => 'Blue Shirt']);

        $search = app(ProductSearch::class);
        $query = Product::query();

        $this->assertSame($query, $search->applyTo($query, null, ['name']), 'A null term should add no filter at all.');
        $this->assertCount(2, $search->applyTo(Product::query(), null, ['name'])->get());
    }

    public function test_like_wildcards_in_a_term_are_escaped(): void
    {
        $sale = $this->product(['name' => '50% Off Deal']);
        $plain = $this->product(['name' => 'Plain Deal']);

        $search = app(ProductSearch::class);

        $this->assertSame(
            [$sale->id],
            $search->applyTo(Product::query(), '50%', ['name'])->pluck('id')->all(),
            'A term containing `%` used to match the whole catalogue; it must match the literal product.'
        );
        $this->assertSame(
            [$sale->id],
            $search->applyTo(Product::query(), '%', ['name'])->pluck('id')->all(),
            'A lone `%` matched every name; escaped it is a literal percent sign.'
        );
    }

    public function test_an_underscore_in_a_term_is_escaped(): void
    {
        $snake = $this->product(['name' => '2025_Launch']);
        $double = $this->product(['name' => '2025__Launch']);
        $spaced = $this->product(['name' => '2025 Launch']);

        $search = app(ProductSearch::class);

        $this->assertSame(
            [$snake->id, $double->id],
            $search->applyTo(Product::query(), '_', ['name'])->pluck('id')->all(),
            '`_` is a single-character wildcard in LIKE; escaped it matches names with a literal underscore only, never the space-separated name.'
        );
        $this->assertSame(
            [$double->id],
            $search->applyTo(Product::query(), '__', ['name'])->pluck('id')->all(),
            'Two escaped underscores still match a literal adjacent pair, never a wildcard.'
        );
        $this->assertNotContains($spaced->id, $search->applyTo(Product::query(), '_', ['name'])->pluck('id')->all());
    }

    public function test_every_surface_ships_with_its_search_fields(): void
    {
        $byDescription = $this->product(['name' => 'Canvas Tote', 'description' => 'hand-stitched hemp weave']);
        $bySku = $this->product(['name' => 'Copper Mug', 'sku' => 'MUG-CU-9']);

        $search = app(ProductSearch::class);

        $this->assertSame(
            [$byDescription->id],
            $search->applyTo(Product::query(), 'hemp', ['name', 'description'])->pluck('id')->all(),
            'The storefront search reads the name and the description.'
        );
        $this->assertSame(
            [$bySku->id],
            $search->applyTo(Product::query(), 'MUG-CU-9', ['name', 'sku'])->pluck('id')->all(),
            'The API and admin searches read the name and the SKU.'
        );
    }

    private function product(array $attributes = []): Product
    {
        return Product::create($attributes + [
            'name' => 'Thing',
            'slug' => 'thing-'.uniqid(),
            'status' => 'published',
            'type' => 'simple',
            'catalog_visibility' => 'visible',
            'price' => 10,
            'stock_quantity' => 5,
            'track_stock' => true,
        ]);
    }
}