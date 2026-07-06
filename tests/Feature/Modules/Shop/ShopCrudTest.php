<?php

namespace Tests\Feature\Modules\Shop;

use App\Models\User;
use App\Modules\Shop\Models\Attribute;
use App\Modules\Shop\Models\AttributeTerm;
use App\Modules\Shop\Models\Order;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\ProductAttribute;
use App\Modules\Shop\Models\ProductCategory;
use App\Modules\Shop\Models\ProductTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\BootsModules;
use Tests\TestCase;

class ShopCrudTest extends TestCase
{
    use BootsModules, RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootModules();

        $this->admin = User::factory()->create(['email' => 'admin@test.com']);
        $this->admin->assignRole('admin');
    }

    public function test_admin_can_view_products_index(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('dashboard.shop.products.index'));

        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(403, $response->getStatusCode());
        $this->assertNotEquals(302, $response->getStatusCode());
    }

    public function test_admin_can_create_product(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('dashboard.shop.products.create'));

        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_admin_can_store_product(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('dashboard.shop.products.store'), [
            'name' => 'Test Product',
            'price' => 29.99,
            'status' => 'published',
        ]);

        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(403, $response->getStatusCode());
        $this->assertDatabaseHas('products', ['name' => 'Test Product']);
    }

    public function test_admin_can_edit_product(): void
    {
        $this->actingAs($this->admin);

        $product = Product::create([
            'name' => 'Editable Product',
            'slug' => 'editable-product',
            'price' => 9.99,
        ]);

        $response = $this->get(route('dashboard.shop.products.edit', $product->id));

        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_admin_can_update_product(): void
    {
        $this->actingAs($this->admin);

        $product = Product::create([
            'name' => 'Old Name',
            'slug' => 'old-name',
            'price' => 9.99,
        ]);

        $response = $this->put(route('dashboard.shop.products.update', $product->id), [
            'name' => 'Updated Name',
            'price' => 19.99,
        ]);

        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(403, $response->getStatusCode());
        $this->assertDatabaseHas('products', ['name' => 'Updated Name', 'price' => 19.99]);
    }

    public function test_admin_can_delete_product(): void
    {
        $this->actingAs($this->admin);

        $product = Product::create([
            'name' => 'Deletable Product',
            'slug' => 'deletable-product',
            'price' => 5.00,
        ]);

        $response = $this->delete(route('dashboard.shop.products.destroy', $product->id));

        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(403, $response->getStatusCode());
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_admin_can_view_orders_index(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('dashboard.shop.orders.index'));

        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(403, $response->getStatusCode());
        $this->assertNotEquals(302, $response->getStatusCode());
    }

    public function test_admin_can_view_order(): void
    {
        $this->actingAs($this->admin);

        $order = Order::create([
            'order_number' => 'ORD-TEST-001',
            'user_id' => $this->admin->id,
            'status' => 'pending',
            'total' => 99.99,
            'currency' => 'EUR',
        ]);

        $response = $this->get(route('dashboard.shop.orders.show', $order->id));

        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_admin_can_update_order_status(): void
    {
        $this->actingAs($this->admin);

        $order = Order::create([
            'order_number' => 'ORD-TEST-002',
            'user_id' => $this->admin->id,
            'status' => 'pending',
            'total' => 49.99,
            'currency' => 'EUR',
        ]);

        $response = $this->patch(route('dashboard.shop.orders.update-status', $order->id), [
            'status' => 'confirmed',
        ]);

        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(403, $response->getStatusCode());
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'confirmed']);
    }

    public function test_admin_can_view_categories_index(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('dashboard.shop.categories.index'));

        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(403, $response->getStatusCode());
        $this->assertNotEquals(302, $response->getStatusCode());
    }

    public function test_admin_can_create_category(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('dashboard.shop.categories.store'), [
            'name' => 'Test Category',
        ]);

        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(403, $response->getStatusCode());
        $this->assertDatabaseHas('product_categories', ['name' => 'Test Category']);
    }

    public function test_admin_can_update_category(): void
    {
        $this->actingAs($this->admin);

        $category = ProductCategory::create(['name' => 'Old Cat', 'slug' => 'old-cat']);

        $response = $this->put(route('dashboard.shop.categories.update', $category->id), [
            'name' => 'Updated Cat',
        ]);

        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(403, $response->getStatusCode());
        $this->assertDatabaseHas('product_categories', ['name' => 'Updated Cat']);
    }

    public function test_admin_can_delete_category(): void
    {
        $this->actingAs($this->admin);

        $category = ProductCategory::create(['name' => 'Deletable Cat', 'slug' => 'deletable-cat']);

        $response = $this->delete(route('dashboard.shop.categories.destroy', $category->id));

        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(403, $response->getStatusCode());
        $this->assertDatabaseMissing('product_categories', ['id' => $category->id]);
    }

    public function test_admin_can_view_attributes_index(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('dashboard.shop.attributes.index'));

        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(403, $response->getStatusCode());
        $this->assertNotEquals(302, $response->getStatusCode());
    }

    public function test_admin_can_create_attribute(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('dashboard.shop.attributes.store'), [
            'name' => 'Size',
        ]);

        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(403, $response->getStatusCode());
        $this->assertDatabaseHas('attributes', ['name' => 'Size']);
    }

    public function test_admin_can_update_attribute(): void
    {
        $this->actingAs($this->admin);

        $attr = Attribute::create(['name' => 'Old', 'slug' => 'old']);

        $response = $this->put(route('dashboard.shop.attributes.update', $attr->id), [
            'name' => 'Updated',
        ]);

        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(403, $response->getStatusCode());
        $this->assertDatabaseHas('attributes', ['name' => 'Updated']);
    }

    public function test_admin_can_delete_attribute(): void
    {
        $this->actingAs($this->admin);

        $attr = Attribute::create(['name' => 'Deletable', 'slug' => 'deletable']);

        $response = $this->delete(route('dashboard.shop.attributes.destroy', $attr->id));

        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(403, $response->getStatusCode());
        $this->assertDatabaseMissing('attributes', ['id' => $attr->id]);
    }

    public function test_admin_can_create_attribute_term(): void
    {
        $this->actingAs($this->admin);

        $attr = Attribute::create(['name' => 'Color', 'slug' => 'color']);

        $response = $this->post(route('dashboard.shop.attributes.terms.store', $attr->id), [
            'name' => 'Red',
        ]);

        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(403, $response->getStatusCode());
        $this->assertDatabaseHas('attribute_terms', ['name' => 'Red', 'attribute_id' => $attr->id]);
    }

    public function test_admin_can_view_customers_index(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('dashboard.shop.customers.index'));

        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(403, $response->getStatusCode());
        $this->assertNotEquals(302, $response->getStatusCode());
    }

    public function test_admin_can_view_customer(): void
    {
        $this->actingAs($this->admin);

        $customer = User::factory()->create();
        Order::create([
            'order_number' => 'ORD-TEST-CUST',
            'user_id' => $customer->id,
            'status' => 'completed',
            'total' => 99.99,
            'currency' => 'EUR',
        ]);

        $response = $this->get(route('dashboard.shop.customers.show', $customer->id));

        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_admin_can_view_reports(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('dashboard.shop.reports.index'));

        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(403, $response->getStatusCode());
        $this->assertNotEquals(302, $response->getStatusCode());
    }

    // ─── Comprehensive Product Creation Tests ───────────────────────────

    public function test_store_product_with_categories_and_tags(): void
    {
        $this->actingAs($this->admin);

        $category = ProductCategory::create(['name' => 'Electronics', 'is_active' => true]);
        $tag = ProductTag::create(['name' => 'New Arrival', 'is_active' => true]);

        $response = $this->post(route('dashboard.shop.products.store'), [
            'name' => 'Smartphone',
            'price' => 599.99,
            'status' => 'published',
            'categories' => [$category->id],
            'tags' => [$tag->id],
        ]);

        $response->assertSessionHas('status');
        $this->assertDatabaseHas('products', ['name' => 'Smartphone']);

        $product = Product::where('name', 'Smartphone')->first();
        $this->assertTrue($product->categories->contains($category->id));
        $this->assertTrue($product->tags->contains($tag->id));
    }

    public function test_store_product_with_global_attributes(): void
    {
        $this->actingAs($this->admin);

        $colorAttr = Attribute::create(['name' => 'Color']);
        $red = AttributeTerm::create(['attribute_id' => $colorAttr->id, 'name' => 'Red']);
        $blue = AttributeTerm::create(['attribute_id' => $colorAttr->id, 'name' => 'Blue']);

        $sizeAttr = Attribute::create(['name' => 'Size']);
        $s = AttributeTerm::create(['attribute_id' => $sizeAttr->id, 'name' => 'S']);
        $m = AttributeTerm::create(['attribute_id' => $sizeAttr->id, 'name' => 'M']);
        $l = AttributeTerm::create(['attribute_id' => $sizeAttr->id, 'name' => 'L']);

        $response = $this->post(route('dashboard.shop.products.store'), [
            'name' => 'T-Shirt with Attributes',
            'price' => 29.99,
            'status' => 'published',
            'type' => 'variable',
            'product_attributes' => [
                [
                    'attribute_id' => $colorAttr->id,
                    'name' => 'Color',
                    'position' => 0,
                    'visible' => true,
                    'variation' => true,
                    'options' => ['Red', 'Blue'],
                ],
                [
                    'attribute_id' => $sizeAttr->id,
                    'name' => 'Size',
                    'position' => 1,
                    'visible' => true,
                    'variation' => true,
                    'options' => ['S', 'M', 'L'],
                ],
            ],
        ]);

        $response->assertSessionHas('status');
        $product = Product::where('name', 'T-Shirt with Attributes')->first();
        $this->assertNotNull($product);

        $productAttributes = $product->productAttributes;
        $this->assertCount(2, $productAttributes);

        $colorPA = $productAttributes->firstWhere('name', 'Color');
        $this->assertNotNull($colorPA);
        $this->assertEquals($colorAttr->id, $colorPA->attribute_id);
        $this->assertTrue($colorPA->variation);

        $sizePA = $productAttributes->firstWhere('name', 'Size');
        $this->assertNotNull($sizePA);
        $this->assertEquals($sizeAttr->id, $sizePA->attribute_id);
        $this->assertTrue($sizePA->variation);

        $this->assertTrue($product->globalAttributes->contains($colorAttr->id));
        $this->assertTrue($product->globalAttributes->contains($sizeAttr->id));
    }

    public function test_store_product_with_variations_using_bulk_endpoint(): void
    {
        $this->actingAs($this->admin);

        $colorAttr = Attribute::create(['name' => 'Color']);
        $red = AttributeTerm::create(['attribute_id' => $colorAttr->id, 'name' => 'Red']);
        $blue = AttributeTerm::create(['attribute_id' => $colorAttr->id, 'name' => 'Blue']);

        $sizeAttr = Attribute::create(['name' => 'Size']);
        $s = AttributeTerm::create(['attribute_id' => $sizeAttr->id, 'name' => 'S']);
        $m = AttributeTerm::create(['attribute_id' => $sizeAttr->id, 'name' => 'M']);

        // Create product
        $product = Product::create([
            'name' => 'Variable T-Shirt',
            'slug' => 'variable-t-shirt',
            'price' => 29.99,
            'status' => 'published',
            'type' => 'variable',
        ]);

        // Bulk create variations
        $response = $this->postJson(route('dashboard.shop.products.variations.bulk', $product->id), [
            'variations' => [
                [
                    'attributes' => [
                        ['attribute_id' => $colorAttr->id, 'attribute_term_id' => $red->id, 'name' => 'Red'],
                        ['attribute_id' => $sizeAttr->id, 'attribute_term_id' => $s->id, 'name' => 'S'],
                    ],
                    'price' => 29.99,
                    'stock_quantity' => 10,
                    'is_active' => true,
                ],
                [
                    'attributes' => [
                        ['attribute_id' => $colorAttr->id, 'attribute_term_id' => $red->id, 'name' => 'Red'],
                        ['attribute_id' => $sizeAttr->id, 'attribute_term_id' => $m->id, 'name' => 'M'],
                    ],
                    'price' => 31.99,
                    'stock_quantity' => 5,
                    'is_active' => true,
                ],
                [
                    'attributes' => [
                        ['attribute_id' => $colorAttr->id, 'attribute_term_id' => $blue->id, 'name' => 'Blue'],
                        ['attribute_id' => $sizeAttr->id, 'attribute_term_id' => $s->id, 'name' => 'S'],
                    ],
                    'price' => 32.99,
                    'stock_quantity' => 8,
                    'is_active' => true,
                ],
                [
                    'attributes' => [
                        ['attribute_id' => $colorAttr->id, 'attribute_term_id' => $blue->id, 'name' => 'Blue'],
                        ['attribute_id' => $sizeAttr->id, 'attribute_term_id' => $m->id, 'name' => 'M'],
                    ],
                    'price' => 33.99,
                    'stock_quantity' => 3,
                    'is_active' => false,
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertCount(4, $product->fresh()->variations);

        // Verify data integrity of first variation
        $firstVar = $product->fresh()->variations->first();
        $this->assertEquals(10, $firstVar->stock_quantity);
        $this->assertCount(2, $firstVar->attributes);
        $this->assertEquals('Red', $firstVar->attributes[0]['name']);
        $this->assertEquals('S', $firstVar->attributes[1]['name']);

        // Verify product type was set to variable
        $this->assertEquals('variable', $product->fresh()->type);

        // Update a variation's stock
        $variations = $product->fresh()->variations;
        $target = $variations->firstWhere('is_active', false);
        $this->assertNotNull($target);

        $updateResponse = $this->putJson(
            route('dashboard.shop.products.variations.update', [$product->id, $target->id]),
            ['stock_quantity' => 15, 'is_active' => true]
        );
        $updateResponse->assertOk();

        $target->refresh();
        $this->assertEquals(15, $target->stock_quantity);
        $this->assertTrue($target->is_active);

        // Delete one variation
        $deleteTarget = $product->fresh()->variations->last();
        $deleteResponse = $this->deleteJson(
            route('dashboard.shop.products.variations.destroy', [$product->id, $deleteTarget->id])
        );
        $deleteResponse->assertOk();
        $this->assertCount(3, $product->fresh()->variations);
    }

    public function test_store_product_with_scheduling_auto_publishes(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('dashboard.shop.products.store'), [
            'name' => 'Scheduled Product',
            'price' => 19.99,
            'status' => 'future',
            'publish_date' => now()->subHour()->toDateTimeString(),
        ]);

        $response->assertSessionHas('status');
        $this->assertDatabaseHas('products', [
            'name' => 'Scheduled Product',
            'status' => 'published',
        ]);
    }

    public function test_store_product_with_sale_pricing(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('dashboard.shop.products.store'), [
            'name' => 'Discounted Product',
            'price' => 100.00,
            'compare_price' => 150.00,
            'sale_price' => 75.00,
            'sale_from' => now()->subDay()->toDateString(),
            'sale_to' => now()->addMonth()->toDateString(),
            'status' => 'published',
        ]);

        $response->assertSessionHas('status');
        $this->assertDatabaseHas('products', [
            'name' => 'Discounted Product',
            'price' => 100.00,
            'sale_price' => 75.00,
        ]);
    }

    public function test_update_product_with_changed_relationships(): void
    {
        $this->actingAs($this->admin);

        $cat1 = ProductCategory::create(['name' => 'Category A', 'is_active' => true]);
        $cat2 = ProductCategory::create(['name' => 'Category B', 'is_active' => true]);
        $tag1 = ProductTag::create(['name' => 'Tag A', 'is_active' => true]);
        $tag2 = ProductTag::create(['name' => 'Tag B', 'is_active' => true]);

        $attr = Attribute::create(['name' => 'Material']);
        $term = AttributeTerm::create(['attribute_id' => $attr->id, 'name' => 'Cotton']);

        $product = Product::create([
            'name' => 'Original Title',
            'slug' => 'original-title',
            'price' => 10.00,
            'status' => 'draft',
        ]);
        $product->categories()->sync([$cat1->id]);
        $product->tags()->sync([$tag1->id]);

        // Update: new name, swap category, add tag, add attribute
        $response = $this->put(route('dashboard.shop.products.update', $product->id), [
            'name' => 'Updated Title',
            'price' => 25.00,
            'status' => 'published',
            'categories' => [$cat2->id],
            'tags' => [$tag1->id, $tag2->id],
            'product_attributes' => [
                [
                    'attribute_id' => $attr->id,
                    'name' => 'Material',
                    'position' => 0,
                    'visible' => true,
                    'variation' => false,
                    'options' => ['Cotton', 'Polyester'],
                ],
            ],
        ]);

        $response->assertSessionHas('status');
        $product->refresh();

        $this->assertEquals('Updated Title', $product->name);
        $this->assertEquals(25.00, (float) $product->price);
        $this->assertEquals('published', $product->status);

        // Categories replaced
        $this->assertFalse($product->categories->contains($cat1->id));
        $this->assertTrue($product->categories->contains($cat2->id));

        // Tags added (tag1 kept, tag2 added)
        $this->assertTrue($product->tags->contains($tag1->id));
        $this->assertTrue($product->tags->contains($tag2->id));

        // Product attribute created
        $this->assertCount(1, $product->productAttributes);
        $this->assertEquals('Material', $product->productAttributes->first()->name);
        $this->assertTrue($product->globalAttributes->contains($attr->id));
    }

    public function test_filter_products_index(): void
    {
        $this->actingAs($this->admin);

        $cat1 = ProductCategory::create(['name' => 'Cats', 'is_active' => true]);
        $cat2 = ProductCategory::create(['name' => 'Dogs', 'is_active' => true]);

        $p1 = Product::create(['name' => 'Cat Food', 'slug' => 'cat-food', 'price' => 5.00, 'status' => 'published']);
        $p2 = Product::create(['name' => 'Cat Toy', 'slug' => 'cat-toy', 'price' => 3.00, 'status' => 'published']);
        $p3 = Product::create(['name' => 'Dog Food', 'slug' => 'dog-food', 'price' => 8.00, 'status' => 'draft']);

        $p1->categories()->sync([$cat1->id]);
        $p2->categories()->sync([$cat1->id]);
        $p3->categories()->sync([$cat2->id]);

        // Search filter
        $searchResponse = $this->get(route('dashboard.shop.products.index', ['search' => 'Cat']));
        $searchResponse->assertOk();
        $searchResponse->assertSee('Cat Food');
        $searchResponse->assertSee('Cat Toy');
        $searchResponse->assertDontSee('Dog Food');

        // Status filter
        $statusResponse = $this->get(route('dashboard.shop.products.index', ['status' => 'draft']));
        $statusResponse->assertOk();
        $statusResponse->assertSee('Dog Food');
        $statusResponse->assertDontSee('Cat Food');

        // Category filter
        $catResponse = $this->get(route('dashboard.shop.products.index', ['category_id' => $cat1->id]));
        $catResponse->assertOk();
        $catResponse->assertSee('Cat Food');
        $catResponse->assertSee('Cat Toy');
        $catResponse->assertDontSee('Dog Food');
    }

    public function test_variation_generate_endpoint(): void
    {
        $this->actingAs($this->admin);

        $colorAttr = Attribute::create(['name' => 'Color']);
        $red = AttributeTerm::create(['attribute_id' => $colorAttr->id, 'name' => 'Red']);
        $blue = AttributeTerm::create(['attribute_id' => $colorAttr->id, 'name' => 'Blue']);
        $green = AttributeTerm::create(['attribute_id' => $colorAttr->id, 'name' => 'Green']);

        $sizeAttr = Attribute::create(['name' => 'Size']);
        $s = AttributeTerm::create(['attribute_id' => $sizeAttr->id, 'name' => 'S']);
        $m = AttributeTerm::create(['attribute_id' => $sizeAttr->id, 'name' => 'M']);

        $product = Product::create([
            'name' => 'Generate Test',
            'slug' => 'generate-test',
            'price' => 20.00,
            'status' => 'published',
        ]);

        // Assign both attributes to the product with variation=true
        ProductAttribute::create(['product_id' => $product->id, 'attribute_id' => $colorAttr->id, 'name' => 'Color', 'position' => 0, 'visible' => true, 'variation' => true]);
        ProductAttribute::create(['product_id' => $product->id, 'attribute_id' => $sizeAttr->id, 'name' => 'Size', 'position' => 1, 'visible' => true, 'variation' => true]);
        $product->globalAttributes()->sync([$colorAttr->id, $sizeAttr->id]);

        // Generate all combinations via the generate endpoint
        $response = $this->postJson(
            route('dashboard.shop.products.variations.generate', $product->id),
            ['attribute_ids' => [$colorAttr->id, $sizeAttr->id]]
        );

        $response->assertOk();
        $response->assertJson(['success' => true, 'created' => 6, 'total' => 6]);

        $this->assertCount(6, $product->fresh()->variations);

        // Calling again should create 0 (all already exist)
        $response2 = $this->postJson(
            route('dashboard.shop.products.variations.generate', $product->id),
            ['attribute_ids' => [$colorAttr->id, $sizeAttr->id]]
        );

        $response2->assertOk();
        $response2->assertJson(['created' => 0, 'total' => 6]);
        $this->assertCount(6, $product->fresh()->variations);
    }

    public function test_store_product_with_custom_attributes(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('dashboard.shop.products.store'), [
            'name' => 'Custom Attribute Product',
            'price' => 15.00,
            'status' => 'published',
            'type' => 'variable',
            'product_attributes' => [
                [
                    'name' => 'Custom Color',
                    'position' => 0,
                    'visible' => true,
                    'variation' => true,
                    'options' => ['Gold', 'Silver'],
                ],
            ],
        ]);

        $response->assertSessionHas('status');
        $product = Product::where('name', 'Custom Attribute Product')->first();
        $this->assertNotNull($product);

        $this->assertCount(1, $product->productAttributes);
        $pa = $product->productAttributes->first();
        $this->assertNull($pa->attribute_id); // custom, not global
        $this->assertEquals('Custom Color', $pa->name);
        $this->assertEquals(['Gold', 'Silver'], $pa->options);
    }

    public function test_cannot_create_product_without_required_fields(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('dashboard.shop.products.store'), [
            'price' => 10.00,
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_cannot_create_product_with_invalid_category(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('dashboard.shop.products.store'), [
            'name' => 'Invalid Cat',
            'price' => 10.00,
            'categories' => [99999],
        ]);

        $response->assertSessionHasErrors('categories.0');
    }
}
