<?php

namespace Tests\Feature\Modules\Shop;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\ShopModule\Models\Attribute;
use Nreljed\ShopModule\Models\AttributeTerm;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductVariation;
use Tests\BootsFeatures;
use Tests\TestCase;

class VariationMechanismTest extends TestCase
{
    use BootsFeatures, RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFeatures();
        $this->admin = User::factory()->create(['email' => 'admin@test.com']);
        $this->admin->assignRole('admin');
    }

    private function assignVariationAttributes(Product $product, array $attributeIds): void
    {
        foreach ($attributeIds as $i => $attrId) {
            $product->productAttributes()->create([
                'attribute_id' => $attrId,
                'name' => Attribute::find($attrId)->name,
                'position' => $i,
                'visible' => true,
                'variation' => true,
                'options' => null,
            ]);
        }
        $product->globalAttributes()->sync($attributeIds);
    }

    private function createColorSizeAttributes(): array
    {
        $color = Attribute::create(['name' => 'Color']);
        $red = AttributeTerm::create(['attribute_id' => $color->id, 'name' => 'Red']);
        $blue = AttributeTerm::create(['attribute_id' => $color->id, 'name' => 'Blue']);
        $size = Attribute::create(['name' => 'Size']);
        $s = AttributeTerm::create(['attribute_id' => $size->id, 'name' => 'S']);
        $m = AttributeTerm::create(['attribute_id' => $size->id, 'name' => 'M']);

        return compact('color', 'red', 'blue', 'size', 's', 'm');
    }

    public function test_single_variation_crud(): void
    {
        $this->actingAs($this->admin);
        $a = $this->createColorSizeAttributes();

        $product = Product::create(['name' => 'Var CRUD', 'slug' => 'var-crud', 'price' => 20, 'type' => 'variable']);
        // Ensure product type is variable before creating variations
        $product->update(['type' => 'variable']);

        // CREATE via store endpoint
        $response = $this->postJson(route('dashboard.shop.products.variations.store', $product->id), [
            'attributes' => [
                ['attribute_id' => $a['color']->id, 'attribute_term_id' => $a['red']->id, 'name' => 'Red'],
                ['attribute_id' => $a['size']->id, 'attribute_term_id' => $a['s']->id, 'name' => 'S'],
            ],
            'price' => 25.00,
            'stock_quantity' => 50,
            'is_active' => true,
        ]);
        $response->assertOk();
        $variation = ProductVariation::where('product_id', $product->id)->first();
        $this->assertNotNull($variation);
        $this->assertEquals(25.00, (float) $variation->price);
        $this->assertEquals(50, $variation->stock_quantity);
        $this->assertTrue($variation->is_active);
        $this->assertCount(2, $variation->attributeTerms);

        // INDEX — variations list
        $indexResponse = $this->getJson(route('dashboard.shop.products.variations.index', $product->id));
        $indexResponse->assertOk();
        $this->assertCount(1, $indexResponse->json());

        // UPDATE — price + stock
        $updateResponse = $this->putJson(
            route('dashboard.shop.products.variations.update', [$product->id, $variation->id]),
            ['price' => 30.00, 'stock_quantity' => 25, 'is_active' => false]
        );
        $updateResponse->assertOk();
        $variation->refresh();
        $this->assertEquals(30.00, (float) $variation->price);
        $this->assertEquals(25, $variation->stock_quantity);
        $this->assertFalse($variation->is_active);

        // DELETE
        $deleteResponse = $this->deleteJson(
            route('dashboard.shop.products.variations.destroy', [$product->id, $variation->id])
        );
        $deleteResponse->assertOk();
        $this->assertCount(0, $product->fresh()->variations);
    }

    public function test_active_price_with_sale_dates(): void
    {
        $this->actingAs($this->admin);
        $a = $this->createColorSizeAttributes();

        $product = Product::create(['name' => 'Sale Test', 'slug' => 'sale-test', 'price' => 20, 'type' => 'variable']);

        // Variation with no sale dates (sale_price only) — should always be active
        $v1 = $product->variations()->create(['price' => 50, 'sale_price' => 40, 'is_active' => true]);
        $v1->calculateActivePrice();
        $this->assertEquals(40.00, (float) $v1->active_price);
        $this->assertTrue($v1->on_sale);

        // Variation with both dates in range
        $v2 = $product->variations()->create([
            'price' => 60, 'sale_price' => 45,
            'sale_from' => now()->subDays(2), 'sale_to' => now()->addDays(2),
            'is_active' => true,
        ]);
        $v2->calculateActivePrice();
        $this->assertEquals(45.00, (float) $v2->active_price);
        $this->assertTrue($v2->on_sale);

        // Variation with expired sale (sale_to in past)
        $v3 = $product->variations()->create([
            'price' => 70, 'sale_price' => 50,
            'sale_from' => now()->subDays(10), 'sale_to' => now()->subDays(5),
            'is_active' => true,
        ]);
        $v3->calculateActivePrice();
        $this->assertEquals(70.00, (float) $v3->active_price);
        $this->assertFalse($v3->on_sale);

        // Variation with only sale_from (sale started, no end)
        $v4 = $product->variations()->create([
            'price' => 80, 'sale_price' => 55,
            'sale_from' => now()->subDay(),
            'is_active' => true,
        ]);
        $v4->calculateActivePrice();
        $this->assertEquals(55.00, (float) $v4->active_price);
        $this->assertTrue($v4->on_sale);

        // Variation with only sale_to (sale ends future, no start)
        $v5 = $product->variations()->create([
            'price' => 90, 'sale_price' => 60,
            'sale_to' => now()->addDays(3),
            'is_active' => true,
        ]);
        $v5->calculateActivePrice();
        $this->assertEquals(60.00, (float) $v5->active_price);
        $this->assertTrue($v5->on_sale);

        // Variation with future sale (sale_from in future) — not on sale
        $v6 = $product->variations()->create([
            'price' => 100, 'sale_price' => 65,
            'sale_from' => now()->addDays(5), 'sale_to' => now()->addDays(10),
            'is_active' => true,
        ]);
        $v6->calculateActivePrice();
        $this->assertEquals(100.00, (float) $v6->active_price);
        $this->assertFalse($v6->on_sale);

        // Variation with no price — active_price should be null
        $v7 = $product->variations()->create(['is_active' => true]);
        $v7->calculateActivePrice();
        $this->assertNull($v7->active_price);
        $this->assertFalse($v7->on_sale);
    }

    public function test_computed_fields(): void
    {
        $this->actingAs($this->admin);
        $a = $this->createColorSizeAttributes();

        $product = Product::create(['name' => 'Computed', 'slug' => 'computed', 'price' => 20, 'type' => 'variable']);

        // Variation that is purchasable: active, has price, in stock
        $v1 = $product->variations()->create([
            'price' => 30, 'stock_quantity' => 10, 'is_active' => true,
            'manage_stock' => 'true', 'backorders' => 'no',
        ]);

        $v1->calculateActivePrice();
        $this->assertTrue($v1->purchasable);
        $this->assertTrue($v1->is_visible);
        $this->assertFalse($v1->backorders_allowed);
        $this->assertFalse($v1->backordered);

        // Variation with backorders allowed
        $v2 = $product->variations()->create([
            'price' => 35, 'stock_quantity' => 0, 'is_active' => true,
            'manage_stock' => 'true', 'backorders' => 'yes',
        ]);
        $v2->calculateActivePrice();
        $this->assertTrue($v2->purchasable); // backordered but purchasable
        $this->assertTrue($v2->backorders_allowed);
        $this->assertTrue($v2->backordered); // stock=0 + backorders=yes
        $this->assertEquals('onbackorder', $v2->stock_status);

        // Variation out of stock, backorders not allowed — not purchasable
        $v3 = $product->variations()->create([
            'price' => 40, 'stock_quantity' => 0, 'is_active' => true,
            'manage_stock' => 'true', 'backorders' => 'no',
        ]);
        $v3->calculateActivePrice();
        $this->assertFalse($v3->purchasable);
        $this->assertEquals('outofstock', $v3->stock_status);

        // Variation with no price — not visible, not purchasable
        $v4 = $product->variations()->create(['is_active' => true]);
        $v4->calculateActivePrice();
        $this->assertNull($v4->active_price);
        $this->assertFalse($v4->purchasable);
        $this->assertFalse($v4->is_visible);

        // Inactive variation — not purchasable
        $v5 = $product->variations()->create([
            'price' => 50, 'stock_quantity' => 5, 'is_active' => false,
            'manage_stock' => 'true', 'backorders' => 'no',
        ]);
        $v5->calculateActivePrice();
        $this->assertFalse($v5->purchasable);

        // Variation with manage_stock=false — always purchasable if has price and is active
        $v6 = $product->variations()->create([
            'price' => 55, 'is_active' => true,
            'manage_stock' => 'false',
        ]);
        $v6->calculateActivePrice();
        $this->assertTrue($v6->purchasable);

        // Variation with manage_stock=parent — relies on product
        $product->update(['stock_quantity' => 5, 'track_stock' => true, 'stock_status' => 'instock', 'backorders' => 'no']);
        $v7 = $product->variations()->create([
            'price' => 60, 'is_active' => true,
            'manage_stock' => 'parent',
        ]);
        $v7->calculateActivePrice();
        $this->assertTrue($v7->purchasable);
    }

    public function test_visible_column_behavior(): void
    {
        $this->actingAs($this->admin);
        $a = $this->createColorSizeAttributes();

        $product = Product::create(['name' => 'Visible Test', 'slug' => 'visible-test', 'price' => 20, 'type' => 'variable']);

        // Variation with visible=true + price set — is visible
        $v1 = $product->variations()->create(['price' => 30, 'is_active' => true, 'visible' => true]);
        $v1->calculateActivePrice();
        $this->assertTrue($v1->isVisible());

        // Variation with visible=false + price set — not visible
        $v2 = $product->variations()->create(['price' => 35, 'is_active' => true, 'visible' => false]);
        $v2->calculateActivePrice();
        $this->assertFalse($v2->isVisible());

        // Variation with visible=true, no price — not visible
        $v3 = $product->variations()->create(['is_active' => true, 'visible' => true]);
        $v3->calculateActivePrice();
        $this->assertNull($v3->active_price);
        $this->assertFalse($v3->isVisible());

        // Verify is_visible attribute via JSON response
        $indexResponse = $this->getJson(route('dashboard.shop.products.variations.index', $product->id));
        $indexResponse->assertOk();
        $data = $indexResponse->json();
        $this->assertTrue($data[0]['is_visible']);
        $this->assertFalse($data[1]['is_visible']);
        $this->assertFalse($data[2]['is_visible']);

        // Toggle visible via update API
        $this->putJson(
            route('dashboard.shop.products.variations.update', [$product->id, $v2->id]),
            ['visible' => true]
        )->assertOk();
        $v2->refresh();
        $this->assertTrue($v2->visible);
        $this->assertTrue($v2->isVisible());
    }

    public function test_stock_status_auto_calculation(): void
    {
        $this->actingAs($this->admin);
        $a = $this->createColorSizeAttributes();

        $product = Product::create(['name' => 'Stock Status', 'slug' => 'stock-status', 'price' => 20, 'type' => 'variable']);

        // manage_stock=true, stock > 0, backorders=no → instock
        $v1 = $product->variations()->create([
            'price' => 30, 'stock_quantity' => 10, 'manage_stock' => 'true', 'backorders' => 'no',
            'is_active' => true,
        ]);
        $this->assertEquals('instock', $v1->stock_status);

        // manage_stock=true, stock = 0, backorders=notify → onbackorder
        $v2 = $product->variations()->create([
            'price' => 35, 'stock_quantity' => 0, 'manage_stock' => 'true', 'backorders' => 'notify',
            'is_active' => true,
        ]);
        $this->assertEquals('onbackorder', $v2->stock_status);

        // manage_stock=true, stock = 0, backorders=no → outofstock
        $v3 = $product->variations()->create([
            'price' => 40, 'stock_quantity' => 0, 'manage_stock' => 'true', 'backorders' => 'no',
            'is_active' => true,
        ]);
        $this->assertEquals('outofstock', $v3->stock_status);

        // manage_stock=parent → no auto status (inherits from product)
        $v4 = $product->variations()->create([
            'price' => 45, 'stock_quantity' => 0, 'manage_stock' => 'parent', 'backorders' => 'no',
            'is_active' => true,
        ]);
        $this->assertNull($v4->stock_status);
    }

    public function test_price_range_sync_on_variation_save_delete(): void
    {
        $this->actingAs($this->admin);
        $a = $this->createColorSizeAttributes();

        $product = Product::create(['name' => 'Price Range', 'slug' => 'price-range', 'price' => 20, 'type' => 'variable']);

        // Initially no variations — min/max should be null
        $product->syncPriceRange();
        $product->refresh();
        $this->assertNull($product->min_variation_price);
        $this->assertNull($product->max_variation_price);

        // Create variations with different prices
        $v1 = $product->variations()->create(['price' => 30, 'is_active' => true]);
        $v2 = $product->variations()->create(['price' => 50, 'is_active' => true]);
        $v3 = $product->variations()->create(['price' => 40, 'is_active' => true]);

        // syncPriceRange is called on saved event
        $product->refresh();
        $this->assertEquals(30.00, (float) $product->min_variation_price);
        $this->assertEquals(50.00, (float) $product->max_variation_price);

        // Delete v1 (price = 30) — min should become 40
        $v1->delete();
        $product->refresh();
        $this->assertEquals(40.00, (float) $product->min_variation_price);
        $this->assertEquals(50.00, (float) $product->max_variation_price);

        // Delete all — min/max should be null again
        $v2->delete();
        $v3->delete();
        $product->refresh();
        $this->assertNull($product->min_variation_price);
        $this->assertNull($product->max_variation_price);
    }

    public function test_default_attributes_on_product(): void
    {
        $this->actingAs($this->admin);
        $a = $this->createColorSizeAttributes();

        $product = Product::create(['name' => 'Defaults', 'slug' => 'defaults', 'price' => 20, 'type' => 'variable']);

        // Create variations
        $vRedS = $product->variations()->create(['price' => 30, 'is_active' => true]);
        $vRedS->syncAttributes([
            ['attribute_id' => $a['color']->id, 'attribute_term_id' => $a['red']->id, 'name' => 'Red'],
            ['attribute_id' => $a['size']->id, 'attribute_term_id' => $a['s']->id, 'name' => 'S'],
        ]);

        $vBlueM = $product->variations()->create(['price' => 35, 'is_active' => true]);
        $vBlueM->syncAttributes([
            ['attribute_id' => $a['color']->id, 'attribute_term_id' => $a['blue']->id, 'name' => 'Blue'],
            ['attribute_id' => $a['size']->id, 'attribute_term_id' => $a['m']->id, 'name' => 'M'],
        ]);

        // Set default_attributes on product (key-value format)
        $product->update([
            'default_attributes' => ['color' => 'red', 'size' => 's'],
        ]);

        // Product's default_attributes should be set
        $product->refresh();
        $this->assertEquals(['color' => 'red', 'size' => 's'], $product->default_attributes);
    }

    public function test_cartesian_generate_then_update_individual(): void
    {
        $this->actingAs($this->admin);
        $a = $this->createColorSizeAttributes();

        $product = Product::create(['name' => 'Generate Full', 'slug' => 'generate-full', 'price' => 20, 'status' => 'published']);
        $this->assignVariationAttributes($product, [$a['color']->id, $a['size']->id]);

        // Generate all 4 combinations (2 colors × 2 sizes)
        $this->postJson(route('dashboard.shop.products.variations.generate', $product->id), [
            'attribute_ids' => [$a['color']->id, $a['size']->id],
        ])->assertOk();

        $this->assertCount(4, $product->fresh()->variations);

        // Update each generated variation with price and stock
        $variations = $product->fresh()->variations;
        $expected = [
            ['color' => 'Red', 'size' => 'S', 'price' => 29.99, 'stock' => 15],
            ['color' => 'Red', 'size' => 'M', 'price' => 31.99, 'stock' => 10],
            ['color' => 'Blue', 'size' => 'S', 'price' => 32.99, 'stock' => 8],
            ['color' => 'Blue', 'size' => 'M', 'price' => 34.99, 'stock' => 5],
        ];

        foreach ($variations as $i => $v) {
            $this->putJson(
                route('dashboard.shop.products.variations.update', [$product->id, $v->id]),
                ['price' => $expected[$i]['price'], 'stock_quantity' => $expected[$i]['stock']]
            )->assertOk();
            $v->refresh();
            $this->assertEquals($expected[$i]['price'], (float) $v->price);
            $this->assertEquals($expected[$i]['stock'], $v->stock_quantity);
        }

        // Verify price range is synced
        $product->refresh();
        $this->assertEquals(29.99, (float) $product->min_variation_price);
        $this->assertEquals(34.99, (float) $product->max_variation_price);
    }

    public function test_bulk_store_basic_with_visible_and_defaults(): void
    {
        $this->actingAs($this->admin);
        $a = $this->createColorSizeAttributes();

        $product = Product::create(['name' => 'Bulk Visible', 'slug' => 'bulk-visible', 'price' => 20, 'type' => 'variable']);

        $response = $this->postJson(route('dashboard.shop.products.variations.bulk', $product->id), [
            'variations' => [
                [
                    'attributes' => [
                        ['attribute_id' => $a['color']->id, 'attribute_term_id' => $a['red']->id, 'name' => 'Red'],
                    ],
                    'price' => 30.00,
                    'stock_quantity' => 10,
                    'is_active' => true,
                    'visible' => true,
                ],
                [
                    'attributes' => [
                        ['attribute_id' => $a['color']->id, 'attribute_term_id' => $a['blue']->id, 'name' => 'Blue'],
                    ],
                    'price' => 35.00,
                    'stock_quantity' => 5,
                    'is_active' => true,
                    'visible' => false,
                ],
            ],
        ]);
        $response->assertOk();

        $variations = $product->fresh()->variations;
        $this->assertCount(2, $variations);

        $redVar = $variations->firstWhere('price', 30.00);
        $blueVar = $variations->firstWhere('price', 35.00);

        $this->assertTrue($redVar->visible);
        $this->assertFalse($blueVar->visible);

        // Verify via API that is_visible reflects visible column
        $indexResponse = $this->getJson(route('dashboard.shop.products.variations.index', $product->id));
        $indexResponse->assertOk();
        $data = $indexResponse->json();
        $redData = collect($data)->firstWhere('price', '30');
        $blueData = collect($data)->firstWhere('price', '35');
        $this->assertTrue($redData['is_visible']);
        $this->assertFalse($blueData['is_visible']);
    }

    public function test_sale_price_with_single_date_endpoint(): void
    {
        $this->actingAs($this->admin);
        $a = $this->createColorSizeAttributes();

        $product = Product::create(['name' => 'Sale Single', 'slug' => 'sale-single', 'price' => 20, 'type' => 'variable']);

        // Create variation with only sale_from
        $v = $product->variations()->create(['price' => 50, 'is_active' => true]);

        $updateResponse = $this->putJson(
            route('dashboard.shop.products.variations.update', [$product->id, $v->id]),
            [
                'sale_price' => 35,
                'sale_from' => now()->subDay()->toDateString(), // started yesterday
            ]
        );
        $updateResponse->assertOk();
        $v->refresh();
        $this->assertEquals(35.00, (float) $v->active_price);
        $this->assertTrue($v->on_sale);

        // Now update with expired sale_to only
        $updateResponse2 = $this->putJson(
            route('dashboard.shop.products.variations.update', [$product->id, $v->id]),
            [
                'sale_price' => 25,
                'sale_from' => null,
                'sale_to' => now()->subDay()->toDateString(), // ended yesterday
            ]
        );
        $updateResponse2->assertOk();
        $v->refresh();
        $this->assertEquals(50.00, (float) $v->active_price);
        $this->assertFalse($v->on_sale);
    }

    public function test_variation_attributes_via_pivot(): void
    {
        $this->actingAs($this->admin);
        $a = $this->createColorSizeAttributes();

        $product = Product::create(['name' => 'Pivot Test', 'slug' => 'pivot-test', 'price' => 20, 'type' => 'variable']);
        $product->update(['type' => 'variable']);

        // Create variation with attributes via store endpoint
        $response = $this->postJson(route('dashboard.shop.products.variations.store', $product->id), [
            'attributes' => [
                ['attribute_id' => $a['color']->id, 'attribute_term_id' => $a['red']->id, 'name' => 'Red'],
                ['attribute_id' => $a['size']->id, 'attribute_term_id' => $a['m']->id, 'name' => 'M'],
            ],
            'price' => 40,
        ]);
        $response->assertOk();

        $variation = $product->fresh()->variations->first();
        $this->assertNotNull($variation);

        // Check pivot table entries
        $this->assertDatabaseHas('product_variation_attribute_term', [
            'product_variation_id' => $variation->id,
            'attribute_term_id' => $a['red']->id,
            'attribute_id' => $a['color']->id,
        ]);
        $this->assertDatabaseHas('product_variation_attribute_term', [
            'product_variation_id' => $variation->id,
            'attribute_term_id' => $a['m']->id,
            'attribute_id' => $a['size']->id,
        ]);

        // Check JSON attributes cache column
        $this->assertCount(2, $variation->attributes);
        $this->assertEquals('Red', $variation->attributes[0]['name']);
        $this->assertEquals('M', $variation->attributes[1]['name']);

        // Check attributeTerms relation
        $this->assertCount(2, $variation->attributeTerms);
        $this->assertTrue($variation->attributeTerms->contains($a['red']->id));
        $this->assertTrue($variation->attributeTerms->contains($a['m']->id));
    }

    public function test_product_type_set_to_variable_on_variation_creation(): void
    {
        $this->actingAs($this->admin);
        $a = $this->createColorSizeAttributes();

        // Start as simple product
        $product = Product::create(['name' => 'Type Change', 'slug' => 'type-change', 'price' => 20, 'type' => 'simple']);
        $product->variations()->create(['price' => 30, 'is_active' => true]);

        // Product type should be updated when variation added via store endpoint
        $this->postJson(route('dashboard.shop.products.variations.store', $product->id), [
            'attributes' => [
                ['attribute_id' => $a['color']->id, 'attribute_term_id' => $a['red']->id, 'name' => 'Red'],
            ],
            'price' => 35,
        ])->assertOk();
        $this->assertEquals('variable', $product->fresh()->type);

        // Product type set to variable on generate
        $product2 = Product::create(['name' => 'Type Change 2', 'slug' => 'type-change-2', 'price' => 25, 'type' => 'simple']);
        $this->assignVariationAttributes($product2, [$a['color']->id]);
        $this->postJson(route('dashboard.shop.products.variations.generate', $product2->id), [
            'attribute_ids' => [$a['color']->id],
        ])->assertOk();
        $this->assertEquals('variable', $product2->fresh()->type);
    }

    public function test_manage_stock_parent_inherits_product_stock(): void
    {
        $this->actingAs($this->admin);
        $a = $this->createColorSizeAttributes();

        $product = Product::create([
            'name' => 'Parent Stock', 'slug' => 'parent-stock',
            'price' => 20, 'type' => 'variable',
            'stock_quantity' => 15, 'track_stock' => true,
            'backorders' => 'no', 'stock_status' => 'instock',
        ]);

        // Variation with manage_stock=parent
        $v = $product->variations()->create([
            'price' => 30, 'is_active' => true,
            'manage_stock' => 'parent',
            'stock_quantity' => 0,
        ]);

        $this->assertTrue($v->usesParentStock());
        $this->assertNull($v->stock_status); // not managed directly
        $this->assertTrue($v->isInStock()); // inherits from product (stock=15 > 0)

        // Product goes out of stock
        $product->update(['stock_quantity' => 0, 'stock_status' => 'outofstock']);
        $v->refresh();
        $v->load('product');
        $this->assertFalse($v->isInStock()); // inherits from product (stock=0)

        // Product on backorder
        $product->update(['backorders' => 'notify', 'stock_status' => 'onbackorder']);
        $v->refresh();
        $v->load('product');
        $this->assertTrue($v->isInStock()); // backorders allowed
        $this->assertTrue($v->isOnBackorder());
    }
}
