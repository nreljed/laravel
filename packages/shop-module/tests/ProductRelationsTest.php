<?php

namespace Nreljed\ShopModule\Tests;

use Illuminate\Database\QueryException;
use Nreljed\ShopModule\Http\Controllers\StorefrontController;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductCategory;
use Nreljed\ShopModule\Models\ProductRelation;
use Nreljed\ShopModule\Tests\Concerns\BootsShopSchema;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Merchant-curated product links (PR 11B).
 *
 * Products can carry an explicit "also bought" (cross-sell) and "instead,
 * buy this" (up-sell) list, stored as `product_relations` rows with a
 * `type`. The storefront shows those first - they are merchandising, chosen
 * by a human - and only falls back to same-category picks when the rail
 * would otherwise run short.
 */
final class ProductRelationsTest extends TestCase
{
    use BootsShopSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootShopSchema();
        $this->resetShopTables();
    }

    // ---------------------------------------------------------------------
    // Persistence.
    // ---------------------------------------------------------------------

    public function test_a_product_can_carry_both_cross_sell_and_up_sell_rows(): void
    {
        $product = $this->product();
        $cross = $this->product();
        $up = $this->product();

        ProductRelation::create([
            'product_id' => $product->id,
            'related_product_id' => $cross->id,
            'type' => ProductRelation::TYPE_CROSS_SELL,
            'sort_order' => 0,
        ]);
        ProductRelation::create([
            'product_id' => $product->id,
            'related_product_id' => $up->id,
            'type' => ProductRelation::TYPE_UP_SELL,
            'sort_order' => 0,
        ]);

        $this->assertRowExists('product_relations', [
            'product_id' => $product->id,
            'related_product_id' => $cross->id,
            'type' => ProductRelation::TYPE_CROSS_SELL,
        ]);
        $this->assertRowExists('product_relations', [
            'product_id' => $product->id,
            'related_product_id' => $up->id,
            'type' => ProductRelation::TYPE_UP_SELL,
        ]);
    }

    public function test_the_same_pair_cannot_be_linked_twice_with_the_same_type(): void
    {
        $product = $this->product();
        $target = $this->product();

        ProductRelation::create([
            'product_id' => $product->id,
            'related_product_id' => $target->id,
            'type' => ProductRelation::TYPE_CROSS_SELL,
            'sort_order' => 0,
        ]);

        $this->expectException(QueryException::class);

        ProductRelation::create([
            'product_id' => $product->id,
            'related_product_id' => $target->id,
            'type' => ProductRelation::TYPE_CROSS_SELL,
            'sort_order' => 1,
        ]);
    }

    // ---------------------------------------------------------------------
    // The relations.
    // ---------------------------------------------------------------------

    public function test_cross_sales_and_up_sales_queries_return_only_their_own_type(): void
    {
        $product = $this->product();
        $cross = $this->product();
        $up = $this->product();

        $product->crossSales()->attach($cross->id, ['type' => ProductRelation::TYPE_CROSS_SELL, 'sort_order' => 0]);
        $product->upSales()->attach($up->id, ['type' => ProductRelation::TYPE_UP_SELL, 'sort_order' => 0]);

        $this->assertSame([$cross->id], $product->crossSales()->pluck('products.id')->all());
        $this->assertSame([$up->id], $product->upSales()->pluck('products.id')->all());
    }

    public function test_curated_relations_mix_both_types_in_sort_order(): void
    {
        $product = $this->product();
        $first = $this->product();
        $second = $this->product();
        $third = $this->product();

        $rows = [
            ['related' => $first->id, 'type' => ProductRelation::TYPE_CROSS_SELL],
            ['related' => $second->id, 'type' => ProductRelation::TYPE_UP_SELL],
            ['related' => $third->id, 'type' => ProductRelation::TYPE_CROSS_SELL],
        ];

        foreach ($rows as $i => $row) {
            $product->curatedRelations()->attach($row['related'], [
                'type' => $row['type'],
                'sort_order' => $i,
            ]);
        }

        $this->assertSame(
            [$first->id, $second->id, $third->id],
            $product->curatedRelations()->pluck('products.id')->all(),
            'The storefront rail reads one mixed list, so the pivot must come back in the order the merchant picked.'
        );
    }

    // ---------------------------------------------------------------------
    // The storefront preference.
    // ---------------------------------------------------------------------

    public function test_the_storefront_puts_curated_relations_first_in_sort_order(): void
    {
        $category = $this->category();
        $product = $this->product();
        $product->categories()->attach($category->id);

        [$curatedA, $curatedB, $curatedC] = array_map(fn () => $this->product(), range(1, 3));

        ProductRelation::create(['product_id' => $product->id, 'related_product_id' => $curatedA->id, 'type' => ProductRelation::TYPE_CROSS_SELL, 'sort_order' => 0]);
        ProductRelation::create(['product_id' => $product->id, 'related_product_id' => $curatedB->id, 'type' => ProductRelation::TYPE_UP_SELL, 'sort_order' => 1]);
        ProductRelation::create(['product_id' => $product->id, 'related_product_id' => $curatedC->id, 'type' => ProductRelation::TYPE_CROSS_SELL, 'sort_order' => 2]);

        // Two more same-category products, so the rail has something to fall
        // back on when the curated list runs short.
        $fill = array_map(fn () => $this->product(), range(1, 2));
        foreach ($fill as $fillProduct) {
            $fillProduct->categories()->attach($category->id);
        }

        $related = $this->relatedProducts($product);

        $this->assertCount(4, $related);
        $this->assertSame(
            [$curatedA->id, $curatedB->id, $curatedC->id],
            $related->take(3)->pluck('id')->all(),
            'Merchandising beats coincidence: curated links open the rail, in the order the merchant set.'
        );
    }

    public function test_the_storefront_skips_curated_relations_that_are_no_longer_visible(): void
    {
        $category = $this->category();
        $product = $this->product();
        $product->categories()->attach($category->id);

        $retired = $this->product(['status' => 'draft']);
        ProductRelation::create(['product_id' => $product->id, 'related_product_id' => $retired->id, 'type' => ProductRelation::TYPE_CROSS_SELL, 'sort_order' => 0]);

        $fill = array_map(fn () => $this->product(), range(1, 4));
        foreach ($fill as $fillProduct) {
            $fillProduct->categories()->attach($category->id);
        }

        $related = $this->relatedProducts($product);

        $this->assertNotContains($retired->id, $related->pluck('id')->all(), 'A link to a withdrawn product would 404 the rail, worse than not linking it.');
        $this->assertCount(4, $related, 'The category fill must make up the rail the retired link cost.');
    }

    public function test_with_no_curated_relations_the_rail_is_filled_from_same_category_products(): void
    {
        $category = $this->category();
        $product = $this->product();
        $product->categories()->attach($category->id);

        $fill = array_map(fn () => $this->product(), range(1, 4));
        foreach ($fill as $fillProduct) {
            $fillProduct->categories()->attach($category->id);
        }

        $related = $this->relatedProducts($product);

        $this->assertCount(4, $related);
        $this->assertNotContains($product->id, $related->pluck('id')->all(), 'The rail must never point at the product showing it.');
        $this->assertCount(4, $related->whereIn('id', array_column($fill, 'id')));
    }

    public function test_a_full_curated_list_is_never_diluted_by_category_picks(): void
    {
        $category = $this->category();
        $product = $this->product();
        $product->categories()->attach($category->id);

        $curated = array_map(fn () => $this->product(), range(1, 5));
        foreach ($curated as $i => $target) {
            ProductRelation::create([
                'product_id' => $product->id,
                'related_product_id' => $target->id,
                'type' => ProductRelation::TYPE_CROSS_SELL,
                'sort_order' => $i,
            ]);
        }

        $fill = $this->product();
        $fill->categories()->attach($category->id);

        $related = $this->relatedProducts($product);

        $this->assertCount(4, $related, 'The rail tops out at four cards.');
        $this->assertSame(
            array_slice(array_column($curated, 'id'), 0, 4),
            $related->pluck('id')->all(),
            'A curated list that already fills the rail must not lose its own row to a random category pick.'
        );
    }

    // ---------------------------------------------------------------------
    // Helpers.
    // ---------------------------------------------------------------------

    /**
     * Invoke the protected storefront preference through the real controller,
     * `app()->make()` so the constructor-bound services resolve exactly the
     * way a request would wire them.
     */
    private function relatedProducts(Product $product)
    {
        $controller = app()->make(StorefrontController::class);

        $method = new ReflectionMethod($controller, 'relatedProducts');

        return $method->invoke($controller, $product);
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

    private function category(array $attributes = []): ProductCategory
    {
        return ProductCategory::create($attributes + [
            'name' => 'Category',
            'slug' => 'category-'.uniqid(),
            'is_active' => true,
        ]);
    }
}