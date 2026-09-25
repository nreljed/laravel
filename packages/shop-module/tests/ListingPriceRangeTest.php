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
            new Product(['name' => 'Charger', 'price' => 29]),
            new Product(['name' => 'Power bank', 'price' => 39]),
        ]));

        $this->assertSame(['min' => 29.0, 'max' => 39.0], $product->price_range);
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
            '/function baseQuery.*?->with\(\s*[\'"]groupedItems/s',
            $source,
            'baseQuery() must eager-load groupedItems, otherwise every grouped product in a listing lazy-loads its bundle'
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
            '/function relatedProducts.*?->with\(\s*[\'"]groupedItems/s',
            $storefront,
            'relatedProducts() must eager-load groupedItems'
        );

        $wishlist = (string) file_get_contents(
            dirname(__DIR__).'/src/Http/Controllers/WishlistController.php'
        );
        $this->assertStringContainsString(
            'groupedItems',
            $wishlist,
            'the wishlist grid must eager-load groupedItems'
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
}
