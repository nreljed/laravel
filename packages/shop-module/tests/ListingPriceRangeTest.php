<?php

namespace Nreljed\ShopModule\Tests;

use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductVariation;
use PHPUnit\Framework\TestCase;

/**
 * Regression guard for the 0.00 storefront listing cards.
 *
 * Variable and grouped products carry no price of their own — `products.price`
 * is 0 and the real money lives on the variations (variable) or the bundled
 * items (grouped). `ProductCard` rendered `product.price` directly, so both
 * types advertised themselves as free, while the product page resolved the real
 * amount from the same variation data.
 *
 * `price_range` is now appended to the model and drives the card, so these
 * cases are exercised directly. DB-free: the range helpers reuse an
 * already-loaded relation and only fall back to a query for the product types
 * that need one, so the plain-product case runs without a connection.
 */
class ListingPriceRangeTest extends TestCase
{
    public function test_variable_product_uses_the_cached_variation_range(): void
    {
        $product = new Product([
            'type' => 'variable',
            'price' => 0,
            'min_variation_price' => 349,
            'max_variation_price' => 409,
        ]);

        $this->assertSame(['min' => 349.0, 'max' => 409.0], $product->price_range);
    }

    public function test_variable_product_derives_range_from_active_variations(): void
    {
        $product = new Product(['type' => 'variable', 'price' => 0]);
        $product->setRelation('variations', collect([
            $this->variation(349.0, true),
            $this->variation(409.0, true),
        ]));

        $this->assertSame(['min' => 349.0, 'max' => 409.0], $product->price_range);
    }

    public function test_inactive_variations_are_excluded_from_the_range(): void
    {
        $product = new Product(['type' => 'variable', 'price' => 0]);
        $product->setRelation('variations', collect([
            $this->variation(349.0, true),
            $this->variation(999.0, false),
        ]));

        $this->assertSame(['min' => 349.0, 'max' => 349.0], $product->price_range);
    }

    public function test_grouped_product_derives_range_from_bundled_items(): void
    {
        $product = new Product(['type' => 'grouped', 'price' => 0]);
        $product->setRelation('groupedItems', collect([
            $this->bundledItem('Charger', 29),
            $this->bundledItem('Power bank', 39),
        ]));

        $this->assertSame(['min' => 29.0, 'max' => 39.0], $product->price_range);
    }

    /**
     * A bundle child the shop will not sell must not move the advertised range.
     *
     * The range is what a grouped card and the price filter both show, and it
     * is built from the children's own prices. Counting a `hidden` child made a
     * kit advertise "EUR 10 - EUR 5000" with nothing payable in between - the
     * shopper is shown a range the shop cannot honour. `search` is the
     * counter-case that has to keep counting: it is reachable and buyable, it is
     * simply never listed on its own.
     */
    public function test_a_withdrawn_bundled_item_does_not_stretch_the_range(): void
    {
        $product = new Product(['type' => 'grouped', 'price' => 0]);
        $product->setRelation('groupedItems', collect([
            $this->bundledItem('Charger', 29),
            $this->bundledItem('Withdrawn premium bundle', 5000, 'hidden'),
            $this->bundledItem('Searchable add-on', 45, 'search'),
        ]));

        $this->assertSame(
            ['min' => 29.0, 'max' => 45.0],
            $product->price_range,
            'A hidden child is not payable, so it cannot set the ceiling. A search-only child still can, because a shopper can buy it.'
        );
    }

    /**
     * An unpublished child is as unpurchasable as a hidden one, and the same
     * argument applies to it.
     */
    public function test_an_unpublished_bundled_item_does_not_stretch_the_range(): void
    {
        $product = new Product(['type' => 'grouped', 'price' => 0]);
        $product->setRelation('groupedItems', collect([
            $this->bundledItem('Charger', 29),
            (new Product)->forceFill([
                'name' => 'Draft premium bundle',
                'price' => 5000,
                'status' => 'draft',
                'catalog_visibility' => 'visible',
            ]),
        ]));

        $this->assertSame(['min' => 29.0, 'max' => 29.0], $product->price_range);
    }

    public function test_grouped_product_without_items_falls_back_to_its_own_price(): void
    {
        $product = new Product(['type' => 'grouped', 'price' => 0]);
        $product->setRelation('groupedItems', collect());

        $this->assertSame(['min' => 0.0, 'max' => 0.0], $product->price_range);
    }

    /**
     * A simple product always ranges over its own price, so the accessor must
     * answer without touching the variations table — this is what keeps the
     * appended accessor from turning every listing into an N+1.
     */
    public function test_simple_product_keeps_its_own_price(): void
    {
        $product = new Product(['type' => 'simple', 'price' => 899]);

        $this->assertFalse($product->relationLoaded('variations'));
        $this->assertSame(['min' => 899.0, 'max' => 899.0], $product->price_range);
    }

    public function test_grouped_items_are_eager_loaded_for_catalog_listings(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__).'/src/Http/Controllers/StorefrontController.php'
        );

        $this->assertMatchesRegularExpression(
            '/function baseQuery.*?->with\(\s*\$this->groupedItems\(\)/s',
            $source,
            'baseQuery() must eager-load groupedItems, otherwise every grouped product in a listing lazy-loads its bundle'
        );
    }

    /**
     * The eager load has to be the shared, visibility-aware one.
     *
     * All four public surfaces that show a bundle - the catalog grid, the
     * product page, the related rail and the wishlist - used to load the
     * children unconstrained, so a `hidden` child shipped its name and price
     * inside the parent's JSON and then 404'd when the shopper clicked the
     * link the card had just printed. The four now share one definition, and
     * this holds that definition to the `reachable()` rule rather than trusting
     * a future fifth call site to remember it.
     */
    public function test_the_shared_bundle_eager_load_excludes_withdrawn_children(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__).'/src/Http/Controllers/StorefrontController.php'
        );

        $this->assertMatchesRegularExpression(
            '/function groupedItems.*?reachable\(\)/s',
            $source,
            'The shared groupedItems eager load must apply reachable().'
        );

        // Every public surface must go through the helper rather than repeating
        // the constraint - four hand-written copies is how the two paths drift.
        $this->assertSame(
            1,
            preg_match_all('/function groupedItems\(.*?\n    \}/s', $source),
            'groupedItems() must be defined exactly once, so there is a single rule to keep correct.'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/->with\([\'"]groupedItems:/',
            $source,
            'A bare `groupedItems:` string eager load is the unconstrained form that leaked hidden children. Use $this->groupedItems().'
        );
    }

    /**
     * The related-products rail and the wishlist grid render their own price
     * markup instead of reusing ProductCard, so each needs the eager load for
     * the appended range to resolve without a query per row.
     */
    public function test_related_and_wishlist_queries_eager_load_grouped_items(): void
    {
        $storefront = (string) file_get_contents(
            dirname(__DIR__).'/src/Http/Controllers/StorefrontController.php'
        );
        $this->assertMatchesRegularExpression(
            '/function relatedProducts.*?->with\(\s*\$this->groupedItems\(\)/s',
            $storefront,
            'relatedProducts() must eager-load groupedItems through the shared helper'
        );

        $wishlist = (string) file_get_contents(
            dirname(__DIR__).'/src/Http/Controllers/WishlistController.php'
        );
        $this->assertMatchesRegularExpression(
            '/groupedItems.*?reachable\(\)/s',
            $wishlist,
            'the wishlist grid must eager-load groupedItems, and must apply reachable() to them'
        );
    }

    /**
     * The product page headline had its own special case that returned
     * `product.price` for a bundle, which is 0, so a grouped product opened at
     * 0.00 even though its items were listed right below at their real prices.
     */
    public function test_grouped_detail_page_headline_uses_the_derived_range(): void
    {
        $controller = (string) file_get_contents(
            dirname(__DIR__).'/src/Http/Controllers/StorefrontController.php'
        );
        $this->assertStringNotContainsString(
            "\$priceRange = ['min' => (float) \$product->price, 'max' => (float) \$product->price];",
            $controller,
            'the non-variable priceRange fallback must use the model range, not the parent price'
        );
        $this->assertStringContainsString(
            '$range = $product->price_range;',
            $controller,
            'the non-variable priceRange fallback must read price_range so bundles resolve their item prices'
        );

        $page = (string) file_get_contents(
            dirname(__DIR__).'/src/Resources/js/Pages/Storefront/ProductDetail.tsx'
        );
        $this->assertStringNotContainsString(
            'if (isExternal || isGrouped) {',
            $page,
            'a grouped product must not be grouped with external products on the parent-price branch'
        );
        $this->assertStringContainsString(
            'rangeMax: priceRange.max > min ? priceRange.max : null',
            $page,
            'a grouped product must headline the range of its bundled items'
        );
    }

    /**
     * The catalog price filter advertised a catalog starting at 0 because it
     * aggregated the raw `price` column, which is 0 on every variable and
     * grouped parent.
     */
    public function test_catalog_filter_bounds_span_every_payable_price(): void
    {
        $controller = (string) file_get_contents(
            dirname(__DIR__).'/src/Http/Controllers/StorefrontController.php'
        );

        $this->assertStringNotContainsString(
            "Product::published()->min('price')",
            $controller,
            'the filter bounds must not aggregate the raw price column, which is 0 on variable and grouped parents'
        );
        $this->assertStringContainsString(
            'effectivePriceBounds',
            $controller,
            'the filter bounds must come from effectivePriceBounds()'
        );
        $this->assertStringContainsString(
            "'min_variation_price'",
            $controller,
            'the bounds must consider cached variation ranges'
        );
        $this->assertStringContainsString(
            'product_grouped_products',
            $controller,
            'the bounds must consider the prices of bundled items'
        );
    }

    /**
     * The price filter compared the raw `price` column directly, so a
     * variable or grouped parent - both of which are 0 there - could never
     * match, and filtering 300-500 returned an empty catalog for a product
     * priced 349-409. Matching is now by overlap across every payable price.
     */
    public function test_price_filter_matches_by_overlap_not_the_raw_price_column(): void
    {
        $controller = (string) file_get_contents(
            dirname(__DIR__).'/src/Http/Controllers/StorefrontController.php'
        );

        $this->assertStringNotContainsString(
            "\$query->where('price', '>=',",
            $controller,
            'the filter must not compare the raw price column, which is 0 on variable and grouped parents'
        );
        $this->assertStringNotContainsString(
            "\$query->where('price', '<=',",
            $controller,
            'the filter must not compare the raw price column, which is 0 on variable and grouped parents'
        );
        $this->assertStringContainsString(
            'applyPriceWindow',
            $controller,
            'the filter must delegate to applyPriceWindow()'
        );

        foreach ([
            "'price', '>', 0" => 'a parent price of 0 is the "no price" sentinel, not a match for a window starting at 0',
            'min_variation_price' => 'variable products must match through their variation range',
            'item.price' => 'grouped products must match through a bundled item price',
            'rangeOverlaps' => 'a range must match by overlapping the window, not by containing it',
        ] as $needle => $why) {
            $this->assertStringContainsString($needle, $controller, $why);
        }
    }

    public function test_listing_card_renders_the_range_instead_of_the_raw_price(): void
    {
        $this->assertProductRenderersUseDisplayPrice(
            'src/Resources/js/components/storefront/ProductCard.tsx',
            ['{money(product.price)}</span>']
        );
        $this->assertProductRenderersUseDisplayPrice(
            'src/Resources/js/Pages/Storefront/ProductDetail.tsx',
            ['{money(r.price)}']
        );
        $this->assertProductRenderersUseDisplayPrice(
            'src/Resources/js/Pages/Wishlist/Index.tsx',
            ['{money(product.price)}']
        );
    }

    /**
     * @param  array<int, string>  $forbidden
     */
    private function assertProductRenderersUseDisplayPrice(string $relativePath, array $forbidden): void
    {
        $source = (string) file_get_contents(dirname(__DIR__).'/'.$relativePath);

        foreach ($forbidden as $needle) {
            $this->assertStringNotContainsString(
                $needle,
                $source,
                "{$relativePath} must not render a raw product price, which is 0 for variable and grouped products"
            );
        }
    }

    private function variation(float $activePrice, bool $isActive): ProductVariation
    {
        return (new ProductVariation)->forceFill([
            'active_price' => $activePrice,
            'is_active' => $isActive,
        ]);
    }

    /**
     * A bundle child as the storefront would see it: published and browsable
     * unless the test says otherwise. `groupedItemPrices()` filters on exactly
     * these two fields, so a bare `new Product(['price' => …])` would be
     * excluded as an unpublished draft and quietly test nothing.
     */
    private function bundledItem(string $name, float $price, string $visibility = 'visible'): Product
    {
        return (new Product)->forceFill([
            'name' => $name,
            'price' => $price,
            'status' => 'published',
            'catalog_visibility' => $visibility,
            'unpublish_at' => null,
        ]);
    }
}
