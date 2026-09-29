<?php

namespace Nreljed\ShopModule\Tests;

use Nreljed\ShopModule\Http\Controllers\NotifyBackInStockController;
use Nreljed\ShopModule\Http\Controllers\ReviewsController;
use Nreljed\ShopModule\Http\Controllers\StorefrontController;
use Nreljed\ShopModule\Http\Controllers\WishlistController;
use Nreljed\ShopModule\Models\Attribute;
use Nreljed\ShopModule\Models\AttributeTerm;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductBrand;
use Nreljed\ShopModule\Models\ProductCategory;
use Nreljed\ShopModule\ShopServiceProvider;
use Nreljed\ShopModule\Tests\Concerns\BootsShopSchema;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The surfaces the original `catalog_visibility` work never reached.
 *
 * `CatalogVisibilityTest` covers the shopper's path to a product: the grid, the
 * search results, the direct URL, the public API, the cart and checkout. Those
 * are the paths a shopper *walks*, and they were all closed.
 *
 * This covers the paths that describe the catalog *around* a product, which
 * turned out to be just as informative and were all open:
 *
 *  - the bundle children riding along inside every product payload, so a
 *    `hidden` child printed its name and price on a card whose own link 404s;
 *  - the category and brand facets, which listed and counted withdrawn stock;
 *  - the attribute facets, which offered swatches only a hidden product uses;
 *  - the price filter's bounds, stretched by a child nobody can buy;
 *  - the sitemap, submitting categories whose catalog was entirely withdrawn;
 *  - three write endpoints that turned into product-existence oracles.
 *
 * The pattern throughout: `hidden` means "this product is not in the shop", and
 * anything that answers a question *about* the catalog has to honour that as
 * strictly as the grid does. A count is an assertion that a product exists; a
 * facet is a link to a page listing it; a price bound is a promise that
 * something in that range can be bought.
 *
 * The two sets that do *not* need honouring are called out where they apply:
 * a `search`-only product is a real, buyable product that is merely never
 * listed, so it counts in facets and bounds; and a customer who already holds a
 * withdrawn product keeps seeing their own order and cart line, because that is
 * their receipt rather than the shop's catalogue.
 */
final class StorefrontSurfaceVisibilityTest extends TestCase
{
    use BootsShopSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootShopSchema();
        $this->resetShopTables();
    }

    // ---------------------------------------------------------------------
    // Bundle children.
    // ---------------------------------------------------------------------

    /**
     * The load every public bundle surface uses, run for real.
     *
     * `StorefrontProductExposureTest` pins the *columns* a nested child is given;
     * it never checked which children came back, so a `hidden` one passed
     * straight through. This is that check, and it is deliberately expressed as
     * the scoped query the controller runs rather than as a source pattern.
     */
    public function test_a_withdrawn_bundle_child_is_not_returned_to_any_public_payload(): void
    {
        $bundle = $this->product(['name' => 'Charger', 'price' => 29]);
        $withdrawn = $this->product(['name' => 'Withdrawn premium', 'price' => 5000, 'catalog_visibility' => Product::HIDDEN]);
        $searchOnly = $this->product(['name' => 'Findable add-on', 'price' => 45, 'catalog_visibility' => Product::SEARCH]);
        $parent = $this->product(['name' => 'Kit', 'type' => 'grouped', 'price' => 0]);

        $parent->groupedItems()->attach([
            $bundle->id => ['quantity' => 1, 'sort_order' => 1],
            $withdrawn->id => ['quantity' => 1, 'sort_order' => 2],
            $searchOnly->id => ['quantity' => 1, 'sort_order' => 3],
        ]);

        $children = $this->reachableGroupedItemNames($parent);

        $this->assertNotContains(
            'Withdrawn premium',
            $children,
            'A hidden child reached the page source. The card printed its name and price, and the link it printed 404s.'
        );
        $this->assertContains(
            'Findable add-on',
            $children,
            'A search-only child stays: a shopper looking at this kit can still buy it, it is only never listed on its own.'
        );
        $this->assertContains('Charger', $children);
    }

    public function test_an_unpublished_bundle_child_is_not_returned_either(): void
    {
        $draft = $this->product(['name' => 'Draft part', 'price' => 5000, 'status' => 'draft']);
        $parent = $this->product(['type' => 'grouped', 'price' => 0]);
        $parent->groupedItems()->attach($draft->id, ['quantity' => 1]);

        $this->assertSame(
            [],
            $this->reachableGroupedItemNames($parent),
            'A draft is not in the shop either, so it cannot be advertised as part of a kit.'
        );
    }

    /**
     * Every public surface has to use the one constrained load.
     *
     * Four call sites each spelled the eager load out by hand, which is how the
     * grid and the wishlist ended up unconstrained while the product page was
     * not. The guard is that there is exactly one definition and no bare
     * `groupedItems:` string left to reintroduce the leak.
     */
    public function test_there_is_one_constrained_bundle_load_and_no_unconstrained_copy(): void
    {
        $source = $this->source(StorefrontController::class);

        $this->assertMatchesRegularExpression(
            '/function groupedItems.*?reachable\(\)/s',
            $source,
            'The shared groupedItems() load must apply reachable().'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/->with\([\'"]groupedItems:/',
            $source,
            'A bare `groupedItems:` string load is the unconstrained form that leaked hidden children.'
        );

        $this->assertMatchesRegularExpression(
            '/groupedItems.*?reachable\(\)/s',
            $this->source(WishlistController::class),
            'The wishlist must apply reachable() to the children it loads.'
        );
    }

    // ---------------------------------------------------------------------
    // Facets and counts.
    //
    // A facet count is a statement that a product exists, and a facet is a link
    // to a page that lists it. Both were computed over every published product,
    // so a category whose entire catalog had been withdrawn still appeared in
    // the sidebar with a count beside it, and the count included search-only
    // products the page underneath it can never show.
    // ---------------------------------------------------------------------

    public function test_a_brand_counts_only_products_the_page_can_list(): void
    {
        $brand = $this->brand('Xiaomi');
        $listed = $this->product(['name' => 'Listed']);
        $withdrawn = $this->product(['name' => 'Withdrawn', 'catalog_visibility' => Product::HIDDEN]);
        $searchOnly = $this->product(['name' => 'Search only', 'catalog_visibility' => Product::SEARCH]);

        $brand->products()->attach([$listed->id, $withdrawn->id, $searchOnly->id]);

        $this->assertSame(
            1,
            $this->brandCount($brand),
            'Only the listed product belongs in a brand count. The search-only product is not on the page the count sits above, and the hidden one is not in the shop.'
        );
    }

    public function test_a_brand_with_only_withdrawn_products_is_not_offered_at_all(): void
    {
        $brand = $this->brand('Retired');
        $brand->products()->attach($this->product(['catalog_visibility' => Product::HIDDEN])->id);

        $this->assertSame(
            0,
            $this->brandCount($brand),
            'A brand whose every product is withdrawn must not appear as a filter.'
        );
    }

    public function test_a_category_counts_only_products_the_page_can_list(): void
    {
        $category = $this->category('Phones');
        $listed = $this->product();
        $withdrawn = $this->product(['catalog_visibility' => Product::HIDDEN]);
        $searchOnly = $this->product(['catalog_visibility' => Product::SEARCH]);

        $category->products()->attach([$listed->id, $withdrawn->id, $searchOnly->id]);

        $this->assertSame(
            1,
            $this->categoryCount($category),
            'The category tile count must match the grid the tile links to.'
        );
    }

    public function test_a_category_with_no_listable_product_is_not_linked_from_a_facet(): void
    {
        $withdrawnOnly = $this->category('Discontinued');
        $withdrawnOnly->products()->attach($this->product(['catalog_visibility' => Product::HIDDEN])->id);

        $this->assertFalse(
            $this->categoryHasAListableProduct($withdrawnOnly),
            'The sidebar listed a category whose page can only ever be empty - the filter promised products and delivered none.'
        );

        $searchOnly = $this->category('Findable only');
        $searchOnly->products()->attach($this->product(['catalog_visibility' => Product::SEARCH])->id);

        $this->assertFalse(
            $this->categoryHasAListableProduct($searchOnly),
            'Same rule for a search-only category: a category page lists products, and a search-only product is never listed.'
        );
    }

    /**
     * The term level of the attribute facet.
     *
     * Scoping the attribute but not its terms still leaked: every term of a
     * surviving attribute was offered, including the swatch name and swatch
     * image that only a withdrawn product uses. That is a way to read a hidden
     * product's options without ever loading the product.
     */
    public function test_an_attribute_offers_only_terms_a_listed_product_uses(): void
    {
        $attribute = $this->attribute('Colour');
        $usedTerm = $this->term($attribute, 'Midnight');
        $withdrawnOnlyTerm = $this->term($attribute, 'Discontinued Rose');

        $listed = $this->product();
        $withdrawn = $this->product(['catalog_visibility' => Product::HIDDEN]);

        $attribute->products()->attach($listed->id);
        $attribute->products()->attach($withdrawn->id);
        $usedTerm->products()->attach($listed->id);
        $withdrawnOnlyTerm->products()->attach($withdrawn->id);

        $offered = $this->attributeTermNames($attribute);

        $this->assertContains('Midnight', $offered);
        $this->assertNotContains(
            'Discontinued Rose',
            $offered,
            'A swatch only a hidden product uses is still a description of that product, and it was offered as a filter.'
        );
    }

    public function test_an_attribute_only_withdrawn_products_use_is_not_offered(): void
    {
        $attribute = $this->attribute('Finish');
        $term = $this->term($attribute, 'Matte');
        $attribute->products()->attach($this->product(['catalog_visibility' => Product::HIDDEN])->id);
        $term->products()->attach($this->product(['catalog_visibility' => Product::HIDDEN])->id);

        $this->assertSame(
            [],
            $this->attributeTermNames($attribute),
            'A filter whose every result is withdrawn should not be offered.'
        );
    }

    // ---------------------------------------------------------------------
    // Sitemap.
    // ---------------------------------------------------------------------

    /**
     * The sitemap is the one surface a crawler is *told* to trust, so it is the
     * last place a private copy of the visibility rules can hide.
     */
    public function test_the_sitemap_carries_no_private_copy_of_the_visibility_rules(): void
    {
        $source = $this->source(ShopServiceProvider::class);

        $this->assertDoesNotMatchRegularExpression(
            "/whereIn\('catalog_visibility',\s*\[/",
            $source,
            "The sitemap hardcoded a literal visibility list. It is the one file the 'no private copy' guard did not cover, and a literal there is how a new value would be silently omitted from search engines while every other surface had it."
        );

        $this->assertStringContainsString(
            '->reachable()',
            $source,
            'What belongs in a sitemap is "still buyable at a URL", which is exactly reachable().'
        );
    }

    public function test_the_sitemap_only_submits_a_category_that_lists_something(): void
    {
        $empty = $this->category('All withdrawn');
        $empty->products()->attach($this->product(['catalog_visibility' => Product::HIDDEN])->id);

        $this->assertFalse(
            $this->categoryHasAListableProduct($empty),
            'Submitting a category whose catalog is entirely withdrawn invites a crawler onto an empty grid.'
        );
    }

    // ---------------------------------------------------------------------
    // Existence oracles.
    //
    // Three write endpoints accepted a product id and answered differently
    // depending on whether that id belonged to a product, a hidden one, or
    // nothing at all. Any of them is enough to walk the catalogue and learn
    // what the shop is withholding.
    // ---------------------------------------------------------------------

    public function test_back_in_stock_signups_cannot_enumerate_the_catalogue(): void
    {
        $source = $this->source(NotifyBackInStockController::class);

        $this->assertDoesNotMatchRegularExpression(
            "/'product_id' => 'required\|exists:/",
            $source,
            '`exists:products,id` answered 422 for an id that never existed and 201 for a hidden one, so walking the ids told an anonymous visitor exactly what the shop is hiding.'
        );

        $this->assertMatchesRegularExpression(
            '/product_id.*?required\|integer/s',
            $source,
            'The id must be validated as a bare integer, then resolved through the storefront scopes.'
        );

        $this->assertStringContainsString(
            '->reachable()',
            $source,
            'The product must be resolved through reachable(), so a hidden one 404s exactly like an unreachable product page.'
        );

        $this->assertStringContainsString(
            'abort_unless',
            $source,
            'A miss has to be a 404, not a validation error, or the two cases are still distinguishable.'
        );
    }

    public function test_reviews_cannot_be_written_for_a_withdrawn_product(): void
    {
        $source = $this->source(ReviewsController::class);

        $this->assertStringContainsString(
            'isVisibleOnStorefront',
            $source,
            'A review form is not the place to write about a product that is not in the shop. Without the gate, "already reviewed" versus a success redirect confirmed the product existed.'
        );
    }

    public function test_the_wishlist_cannot_save_a_product_its_own_list_will_hide(): void
    {
        $source = $this->source(WishlistController::class);

        $this->assertStringContainsString(
            'isVisibleOnStorefront',
            $source,
            'toggle() must apply the same gate the wishlist list applies, or a row is stored that nothing will ever render and the product is confirmed to exist.'
        );
    }

    // ---------------------------------------------------------------------
    // Enforcement.
    // ---------------------------------------------------------------------

    /**
     * `CatalogVisibilityTest` guards two controllers against hardcoding a
     * visibility list. The sitemap was not one of them and had exactly such a
     * list, which is the whole argument for widening the guard to the package.
     */
    public function test_no_file_in_the_package_hardcodes_a_visibility_list(): void
    {
        $offenders = [];

        foreach ($this->phpFilesIn(dirname(__DIR__).'/src') as $file) {
            $source = (string) file_get_contents($file);

            // The model's own constant definitions are the source of truth.
            if (str_contains($file, 'Models'.DIRECTORY_SEPARATOR.'Product.php')) {
                continue;
            }

            if (preg_match("/(whereIn|whereNotIn)\(\s*'catalog_visibility'\s*,\s*\[/", $source)) {
                $offenders[] = $this->relative($file);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "These files carry a private copy of the visibility list instead of reading the model's scopes:\n".implode("\n", $offenders)
        );
    }

    // ---------------------------------------------------------------------
    // Helpers.
    // ---------------------------------------------------------------------

    /**
     * The bundle children a public payload receives: the same scopes the
     * controller's shared eager load applies.
     *
     * @return list<string>
     */
    private function reachableGroupedItemNames(Product $parent): array
    {
        $parent->load(['groupedItems' => fn ($q) => $q
            ->select('products.id', 'name', 'slug', 'price', 'thumbnail_url')
            ->reachable()
            ->published()]);

        return $parent->groupedItems->pluck('name')->all();
    }

    private function brandCount(ProductBrand $brand): int
    {
        return (int) ProductBrand::where('is_active', true)
            ->withCount(['products' => fn ($q) => $q->published()->inListing()])
            ->find($brand->id)
            ->products_count;
    }

    private function categoryCount(ProductCategory $category): int
    {
        return (int) ProductCategory::withCount(['products' => fn ($q) => $q->published()->inListing()])
            ->find($category->id)
            ->products_count;
    }

    private function categoryHasAListableProduct(ProductCategory $category): bool
    {
        return ProductCategory::where('id', $category->id)
            ->whereHas('products', fn ($q) => $q->published()->inListing())
            ->exists();
    }

    /**
     * The terms a storefront filter would offer for this attribute.
     *
     * An attribute no listed product uses is not offered at all, which is the
     * stricter of the two guarantees and the one that makes this return nothing
     * rather than a list of its terms.
     *
     * @return list<string>
     */
    private function attributeTermNames(Attribute $attribute): array
    {
        $offered = Attribute::where('id', $attribute->id)
            ->whereHas('products', fn ($q) => $q->published()->inListing())
            ->with(['terms' => fn ($q) => $q
                ->whereHas('products', fn ($products) => $products->published()->inListing())
                ->orderBy('sort_order')])
            ->first();

        return $offered?->terms->pluck('name')->all() ?? [];
    }

    private function product(array $attributes = []): Product
    {
        return Product::create($attributes + [
            'name' => 'Thing',
            'slug' => 'thing-'.uniqid(),
            'status' => 'published',
            'type' => 'simple',
            'catalog_visibility' => Product::VISIBLE,
            'price' => 10,
            'stock_quantity' => 5,
            'track_stock' => true,
        ]);
    }

    private function brand(string $name): ProductBrand
    {
        return ProductBrand::create(['name' => $name, 'slug' => str($name)->slug()->value(), 'is_active' => true]);
    }

    private function category(string $name): ProductCategory
    {
        return ProductCategory::create(['name' => $name, 'slug' => str($name)->slug()->value(), 'is_active' => true]);
    }

    private function attribute(string $name): Attribute
    {
        return Attribute::create([
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'is_active' => true,
            'display_type' => 'swatch',
        ]);
    }

    private function term(Attribute $attribute, string $name): AttributeTerm
    {
        return $attribute->terms()->create(['name' => $name, 'slug' => str($name)->slug()->value()]);
    }

    /**
     * @return list<string>
     */
    private function phpFilesIn(string $directory): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function relative(string $path): string
    {
        return str_replace(dirname(__DIR__).'\\', '', $path);
    }

    private function source(string $class): string
    {
        return (string) file_get_contents((new ReflectionClass($class))->getFileName());
    }
}
