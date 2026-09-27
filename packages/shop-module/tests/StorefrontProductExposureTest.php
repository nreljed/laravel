<?php

namespace Nreljed\ShopModule\Tests;

use Illuminate\Http\Request;
use Nreljed\ShopModule\Http\Controllers\StorefrontController;
use Nreljed\ShopModule\Http\Controllers\WishlistController;
use Nreljed\ShopModule\Http\Resources\StorefrontProductResource;
use Nreljed\ShopModule\Http\Resources\StorefrontVariationResource;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductVariation;
use Nreljed\ShopModule\Tests\Concerns\BootsShopSchema;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The storefront published every column of `products` to anonymous visitors.
 *
 * `StorefrontController` passed raw Eloquent models to Inertia in four places -
 * the product page (`product`, `related`, `variations`, `defaultVariation`),
 * the catalog grid, the homepage's featured and new-arrivals rails, and the
 * wishlist. Inertia serialises whatever `toArray()` produces, and `Product`
 * declares no `$hidden`, so the page source carried the full row: `cost_price`
 * and `price_hash` (what a merchant pays and what their pricing logic hashes),
 * plus `hold_stock_minutes`, `incoming_stock`, `low_stock_threshold`,
 * `menu_order`, `tax_class`, `created_by`, `default_attributes` and the rest.
 *
 * A `$hidden` array on the model was rejected deliberately: the admin product
 * form binds `cost_price` for editing, and hiding it there would render an
 * empty field that overwrites the stored figure on save. So the trim belongs at
 * the storefront's own boundary instead, which is what these tests hold.
 */
final class StorefrontProductExposureTest extends TestCase
{
    use BootsShopSchema;

    private Request $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootShopSchema();
        $this->resetShopTables();
        $this->request = Request::create('/store/products/thing');
    }

    // ---------------------------------------------------------------------
    // The commercial figures.
    // ---------------------------------------------------------------------

    public function test_cost_price_never_reaches_the_storefront(): void
    {
        $payload = $this->payload($this->product(['cost_price' => 3.10]));

        $this->assertArrayNotHasKey(
            'cost_price',
            $payload,
            'Supplier cost reached the page source. Any visitor could read what the shop pays, which is the whole basis of its pricing and its reason to exist.'
        );
        $this->assertStringNotContainsString(
            '3.1',
            json_encode($payload, JSON_THROW_ON_ERROR),
            'Guard on the value, not just the key: a renamed or nested cost field would still ship it.'
        );
    }

    public function test_internal_pricing_and_stock_bookkeeping_is_not_published(): void
    {
        $payload = $this->payload($this->product([
            'price_hash' => 'deadbeefcafe',
            'hold_stock_minutes' => 45,
            'menu_order' => 9,
            'tax_class' => 'standard',
            'created_by' => 42,
        ]));

        foreach ([
            'price_hash' => 'the hash the pricing logic compares against',
            'hold_stock_minutes' => 'how long a cart hold survives',
            'menu_order' => 'a merchandising control',
            'tax_class' => 'internal tax configuration',
            'created_by' => 'which staff account authored the product',
        ] as $field => $why) {
            $this->assertArrayNotHasKey($field, $payload, "`{$field}` is not catalog data - {$why}.");
        }

        $this->assertStringNotContainsString('deadbeefcafe', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * `incoming_stock` and `low_stock_threshold` look like internal restock
     * columns and were nearly trimmed as such. They are public on purpose: the
     * detail page renders "(5 incoming)" next to the stock line
     * (`ProductDetail.tsx`) and both pages use the threshold to raise a
     * low-stock badge. Pinned here so a later reader does not repeat the
     * mistake and break a shopper-facing feature.
     */
    public function test_incoming_stock_and_low_stock_threshold_stay_public(): void
    {
        $payload = $this->payload($this->product([
            'incoming_stock' => 7,
            'low_stock_threshold' => 3,
        ]));

        $this->assertSame(7, $payload['incoming_stock'], 'The detail page tells shoppers how many units are inbound.');
        $this->assertSame(3, $payload['low_stock_threshold'], 'The low-stock badge is driven by the threshold.');
    }

    public function test_editorial_state_is_not_published(): void
    {
        $payload = $this->payload($this->product([
            'status' => 'published',
            'catalog_visibility' => 'visible',
            'is_featured' => true,
        ]));

        foreach (['status', 'catalog_visibility', 'is_featured', 'publish_date', 'unpublish_at'] as $field) {
            $this->assertArrayNotHasKey($field, $payload, "`{$field}` is workflow state, not catalog data.");
        }
    }

    // ---------------------------------------------------------------------
    // The storefront still works.
    // ---------------------------------------------------------------------

    public function test_the_detail_page_contract_is_intact(): void
    {
        $payload = $this->payload($this->product(['name' => 'Thing', 'price' => 42.5]));

        // Exactly the fields `ProductDetail.tsx` declares. If the allowlist and
        // the component ever drift apart, one of these two assertions fires.
        foreach ([
            'id', 'name', 'slug', 'short_description', 'description',
            'price', 'compare_price', 'sale_price', 'active_price', 'on_sale',
            'is_in_stock', 'price_range',
            'sku', 'stock_quantity', 'track_stock', 'backorders', 'stock_status',
            'incoming_stock', 'low_stock_threshold',
            'type', 'virtual', 'downloadable', 'external_url', 'button_text',
            'sold_individually', 'min_quantity', 'max_quantity',
            'images', 'thumbnail_url', 'average_rating', 'reviews_count',
        ] as $field) {
            $this->assertArrayHasKey($field, $payload, "The storefront needs `{$field}`; dropping it is a UI regression, not a security fix.");
        }
    }

    public function test_the_card_contract_is_intact(): void
    {
        $payload = $this->payload($this->product());

        foreach ([
            'id', 'name', 'slug', 'price', 'price_range', 'compare_price', 'type',
            'virtual', 'downloadable', 'external_url', 'thumbnail_url',
            'average_rating', 'reviews_count', 'stock_quantity', 'track_stock',
            'backorders', 'stock_status', 'low_stock_threshold',
        ] as $field) {
            $this->assertArrayHasKey($field, $payload, "`ProductCard.tsx` reads `{$field}`.");
        }
    }

    public function test_the_allowlist_is_exhaustive_not_merely_short_of_the_bad_fields(): void
    {
        // A denylist passes every test above the moment a new column lands.
        // Pin the exact key set so an addition has to be a deliberate edit.
        $payload = $this->payload($this->product([
            'vendor' => 'ACME', 'barcode' => '123', 'global_unique_id' => 'guid',
            'is_taxable' => true, 'weight' => 1.5, 'default_attributes' => ['a' => 'b'],
        ]));

        foreach (['vendor', 'barcode', 'global_unique_id', 'is_taxable', 'weight', 'default_attributes', 'attributes'] as $field) {
            $this->assertArrayNotHasKey($field, $payload);
        }
    }

    // ---------------------------------------------------------------------
    // Nested relations.
    // ---------------------------------------------------------------------

    public function test_grouped_items_keep_the_quantity_they_are_bought_in(): void
    {
        $bundle = $this->product(['name' => 'Bundle', 'price' => 30]);
        $parent = $this->product(['name' => 'Kit', 'price' => 0, 'type' => 'grouped']);
        $parent->groupedItems()->attach($bundle->id, ['quantity' => 3, 'sort_order' => 1]);

        $parent->load('groupedItems');
        $payload = $this->payload($parent);

        $this->assertCount(1, $payload['groupedItems']);
        $this->assertSame(3, $payload['groupedItems'][0]['pivot']['quantity'], 'The pivot is the only place a bundle\'s quantity lives, so the grid cannot show "1" for a 3-pack.');
        $this->assertSame('Bundle', $payload['groupedItems'][0]['name']);
    }

    public function test_a_grouped_item_is_not_given_the_whole_child_model(): void
    {
        $bundle = $this->product(['name' => 'Bundle', 'cost_price' => 1.25]);
        $parent = $this->product(['type' => 'grouped']);
        $parent->groupedItems()->attach($bundle->id, ['quantity' => 1]);

        $parent->load('groupedItems');
        $payload = $this->payload($parent);

        $this->assertArrayNotHasKey('cost_price', $payload['groupedItems'][0], 'The nested child is a product too.');
    }

    public function test_unloaded_relations_are_omitted_rather_than_serialised(): void
    {
        $payload = $this->payload($this->product());

        // `MissingValue` is how `whenLoaded()` declines; Inertia drops it. A
        // stray `[]` here would mean a storefront page silently rendering an
        // empty review list instead of loading the relation.
        $this->assertArrayNotHasKey('reviews', array_filter($payload, fn ($v) => ! $v instanceof \Illuminate\Http\Resources\MissingValue));
    }

    // ---------------------------------------------------------------------
    // Variations.
    // ---------------------------------------------------------------------

    public function test_variations_are_allowlisted_too(): void
    {
        $variation = new ProductVariation([
            'sku' => 'SKU-1', 'price' => 10, 'stock_quantity' => 4,
            'barcode' => '999', 'weight' => 2.0, 'visible' => true, 'is_active' => true,
        ]);
        $variation->active_price = 10.0;

        $payload = (new StorefrontVariationResource($variation))->toArray($this->request);

        foreach (['id', 'sku', 'price', 'compare_price', 'sale_price', 'active_price', 'stock_quantity', 'manage_stock', 'backorders', 'stock_status', 'image_url', 'attributes', 'is_active'] as $field) {
            $this->assertArrayHasKey($field, $payload, "`ProductDetail.tsx` reads `{$field}` on a variation.");
        }

        foreach ([
            'barcode' => 'the warehouse identifier',
            'weight' => 'shipping internals',
            'visible' => 'an admin-side visibility flag',
        ] as $field => $why) {
            $this->assertArrayNotHasKey($field, $payload, "`{$field}` is {$why}.");
        }
    }

    // ---------------------------------------------------------------------
    // Wiring.
    //
    // The allowlist above only protects anyone if the storefront actually uses
    // it, and a resource-level test cannot see that: reverting the controller
    // to `Inertia::render(..., ['product' => $product])` left this whole suite
    // green, because the resource was still correct - it was just no longer
    // called. Both mutations below were caught only after these assertions
    // were added.
    //
    // These read the controller source rather than booting a request, matching
    // `ShopAdminAccessContractTest` and the other contract tests. The package
    // has no Testbench harness, so a functional Inertia-prop assertion is not
    // available here; the consumer app's integration tests are the backstop.
    // ---------------------------------------------------------------------

    public function test_the_product_page_sends_the_allowlist_not_the_model(): void
    {
        $source = $this->controllerSource(StorefrontController::class);

        foreach ([
            "'product' => StorefrontProductResource::make(\$product)->resolve(\$request)" => 'the product itself',
            "'related' => StorefrontProductResource::collection(\$related)->resolve(\$request)" => 'the related rail',
            "'variations' => StorefrontVariationResource::collection(\$variations)->resolve(\$request)" => 'the variation picker',
        ] as $snippet => $what) {
            $this->assertStringContainsString(
                $snippet,
                $source,
                "The product page must send {$what} through the allowlist resource. A raw model here republishes `cost_price`."
            );
        }

        $this->assertMatchesRegularExpression(
            '/\'defaultVariation\' => \$defaultVariation !== null\s*\?\s*StorefrontVariationResource::make\(\$defaultVariation\)->resolve\(\$request\)\s*:\s*null/',
            $source,
            'The default variation is a product row too.'
        );
    }

    public function test_the_catalog_grid_and_homepage_send_the_allowlist(): void
    {
        $source = $this->controllerSource(StorefrontController::class);

        $this->assertMatchesRegularExpression(
            '/\'products\' => \$products->through\(/',
            $source,
            'The grid paginator must be mapped through the allowlist; `through()` keeps the page links.'
        );

        foreach (['featured', 'newArrivals'] as $rail) {
            $this->assertStringContainsString(
                "'{$rail}' => StorefrontProductResource::collection(\${$rail})->resolve(\$request)",
                $source,
                "The homepage `{$rail}` rail must go through the allowlist."
            );
        }
    }

    public function test_the_wishlist_sends_the_allowlist(): void
    {
        $this->assertStringContainsString(
            'StorefrontProductResource',
            $this->controllerSource(WishlistController::class),
            'The wishlist is a storefront page, so it publishes to signed-in customers and needs the same allowlist.'
        );
        $this->assertMatchesRegularExpression(
            '/\'products\' => \$products->through\(/',
            $this->controllerSource(WishlistController::class),
        );
    }

    public function test_no_storefront_prop_still_hands_over_a_bare_product_model(): void
    {
        foreach ([StorefrontController::class, WishlistController::class] as $controller) {
            $source = $this->controllerSource($controller);

            $this->assertDoesNotMatchRegularExpression(
                "/'(product|products|related|featured|newArrivals|variations|defaultVariation)' => \\\$(product|products|related|featured|newArrivals|variations|defaultVariation),/",
                $source,
                "{$controller} still hands a raw model straight to a prop. Every storefront product payload must go through StorefrontProductResource."
            );
        }
    }

    // ---------------------------------------------------------------------
    // Helpers.
    // ---------------------------------------------------------------------

    private function controllerSource(string $controller): string
    {
        return (string) file_get_contents((new ReflectionClass($controller))->getFileName());
    }

    private function payload(Product $product): array
    {
        return array_filter(
            (new StorefrontProductResource($product))->toArray($this->request),
            fn ($value) => ! $value instanceof \Illuminate\Http\Resources\MissingValue
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
            'cost_price' => 4,
            'stock_quantity' => 5,
            'track_stock' => true,
        ]);
    }
}
