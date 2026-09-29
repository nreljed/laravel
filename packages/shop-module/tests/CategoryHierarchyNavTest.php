<?php

namespace Nreljed\ShopModule\Tests;

use Illuminate\Database\Eloquent\Collection;
use Nreljed\ShopModule\Http\Controllers\StorefrontController;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductCategory;
use Nreljed\ShopModule\Tests\Concerns\BootsShopSchema;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * The hierarchical category navigation on the storefront (PR 11D).
 *
 * The grid on a category page still lists only the category's own products;
 * what was flat - the sidebar facets - is now a tree, and a category page
 * carries a breadcrumb trail and its browseable children. This pins the tree,
 * the trail and the children rails as behaviours of the controller helpers,
 * and the wiring plus the unscoped-product decision as source invariants.
 */
final class CategoryHierarchyNavTest extends TestCase
{
    use BootsShopSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootShopSchema();
        $this->resetShopTables();
    }

    public function test_a_category_receives_a_root_to_itself_breadcrumb_trail(): void
    {
        $electronics = $this->category(['name' => 'Electronics']);
        $phones = $this->category(['name' => 'Phones', 'parent_id' => $electronics->id]);
        $smartphones = $this->category(['name' => 'Smartphones', 'parent_id' => $phones->id]);

        $this->assertSame(
            [
                ['name' => 'Electronics', 'slug' => $electronics->slug],
                ['name' => 'Phones', 'slug' => $phones->slug],
                ['name' => 'Smartphones', 'slug' => $smartphones->slug],
            ],
            $this->breadcrumbs($smartphones),
            'The trail must run from the root category down to the page\'s category.'
        );
    }

    public function test_an_inactive_ancestor_has_no_place_in_the_trail(): void
    {
        $discontinued = $this->category(['name' => 'Discontinued', 'is_active' => false]);
        $sales = $this->category(['name' => 'Sales', 'parent_id' => $discontinued->id]);

        $this->assertSame(
            [['name' => 'Sales', 'slug' => $sales->slug]],
            $this->breadcrumbs($sales),
            'A category a shopper cannot browse must not be linked in the trail above a page they reached.'
        );
    }

    public function test_children_only_offer_active_categories_with_a_product_the_grid_can_list(): void
    {
        $parent = $this->category(['name' => 'Gear']);
        $listed = $this->category(['name' => 'Listed', 'parent_id' => $parent->id]);
        $hiddenStock = $this->category(['name' => 'Hidden stock', 'parent_id' => $parent->id]);
        $inactive = $this->category(['name' => 'Inactive', 'parent_id' => $parent->id, 'is_active' => false]);

        $visible = $this->product(['name' => 'Buyable', 'price' => 10]);
        $withdrawn = $this->product(['name' => 'Withdrawn', 'price' => 20, 'catalog_visibility' => Product::HIDDEN]);
        $searchOnly = $this->product(['name' => 'Findable only', 'price' => 30, 'catalog_visibility' => Product::SEARCH]);

        $listed->products()->attach([$visible->id, $withdrawn->id, $searchOnly->id]);
        $hiddenStock->products()->attach($withdrawn->id);

        $this->assertSame(
            [
                [
                    'id' => $listed->id,
                    'name' => 'Listed',
                    'slug' => $listed->slug,
                    'products_count' => 1,
                ],
            ],
            $this->children($parent),
            'Only an active child with a product the child\'s own grid can list belongs in the rail.'
        );
    }

    public function test_the_sidebar_tree_nests_a_child_under_its_parent(): void
    {
        $electronics = $this->category(['name' => 'Electronics']);
        $phones = $this->category(['name' => 'Phones', 'parent_id' => $electronics->id]);
        $laptops = $this->category(['name' => 'Laptops', 'parent_id' => $electronics->id]);
        $ghost = $this->category(['name' => 'Ghost']);

        $listed = $this->product(['name' => 'Listed thing', 'price' => 10]);
        $orphanProduct = $this->product(['name' => 'Orphan thing', 'price' => 15]);

        $electronics->products()->attach($listed->id);
        $phones->products()->attach($listed->id);
        $laptops->products()->attach($listed->id);
        $category = $this->category(['name' => 'Orphan', 'parent_id' => $ghost->id]);
        $category->products()->attach($orphanProduct->id);

        $flat = ProductCategory::select('id', 'name', 'slug', 'parent_id')
            ->whereHas('products', fn ($q) => $q->published()->inListing())
            ->orderBy('name')
            ->get();

        $tree = $this->tree($flat);

        $this->assertCount(2, $tree, 'Electronics and the orphan child are the two roots.');
        $this->assertSame('Electronics', $tree[0]['name']);
        $this->assertSame(
            ['Laptops', 'Phones'],
            array_column($tree[0]['children'], 'name'),
            'A child must nest under its parent, in the shared name order.'
        );
        $this->assertSame(
            'Orphan',
            $tree[1]['name'],
            'A child whose parent lists nothing is still browseable, so it is offered at the top level.'
        );
        $this->assertSame([], $tree[1]['children']);
    }

    public function test_the_category_page_ships_the_trail_and_children_in_its_payload(): void
    {
        $source = $this->source(StorefrontController::class);

        $this->assertStringContainsString("'breadcrumbs' => \$this->categoryBreadcrumbs(\$category)", $source);
        $this->assertStringContainsString("'children' => \$this->categoryChildren(\$category)", $source);
        $this->assertStringContainsString("\$data['breadcrumbs'] = \$options['breadcrumbs'];", $source);
    }

    public function test_browsing_a_parent_still_lists_only_its_own_products(): void
    {
        $source = $this->source(StorefrontController::class);

        $this->assertStringContainsString(
            "whereHas('categories', fn (\$c) => \$c->where('slug', \$slug))",
            $source,
            'A parent page must list its own grid; merging a descendant\'s catalog in changes what a category URL promises.'
        );
    }

    // ---------------------------------------------------------------------
    // Helpers.
    // ---------------------------------------------------------------------

    /**
     * Invoke a protected storefront helper the way a request would wire it.
     */
    private function helper(string $method): callable
    {
        $controller = app()->make(StorefrontController::class);

        return fn (...$args) => (new ReflectionMethod($controller, $method))
            ->invoke($controller, ...$args);
    }

    private function breadcrumbs(ProductCategory $category): array
    {
        return $this->helper('categoryBreadcrumbs')($category);
    }

    private function children(ProductCategory $category): array
    {
        return $this->helper('categoryChildren')($category);
    }

    private function tree(Collection $categories): array
    {
        return $this->helper('categoryTree')($categories);
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

    private function source(string $class): string
    {
        return (string) file_get_contents((new ReflectionClass($class))->getFileName());
    }
}