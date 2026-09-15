<?php

namespace Tests\Feature\Modules\Shop;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\ShopModule\Models\Attribute;
use Nreljed\ShopModule\Models\AttributeTerm;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Models\OrderNote;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductAttribute;
use Nreljed\ShopModule\Models\ProductCategory;
use Nreljed\ShopModule\Models\ProductTag;
use Tests\BootsFeatures;
use Tests\TestCase;

class ShopCrudTest extends TestCase
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

    public function test_admin_can_refund_order(): void
    {
        $this->actingAs($this->admin);

        $order = Order::create([
            'order_number' => 'ORD-REFUND-001',
            'user_id' => $this->admin->id,
            'status' => 'completed',
            'total' => 199.99,
            'subtotal' => 199.99,
            'currency' => 'EUR',
        ]);
        $product = Product::create(['name' => 'Refund Product', 'price' => 199.99, 'stock_quantity' => 5, 'track_stock' => true]);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 199.99,
            'subtotal' => 199.99,
            'total' => 199.99,
        ]);

        $response = $this->post(route('dashboard.shop.orders.refund', $order->id), [
            'items' => [['order_item_id' => $item->id, 'quantity' => 1, 'amount' => 199.99]],
            'reason' => 'Test refund',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertStatus(302);
        $order->refresh();
        $this->assertEquals('refunded', $order->status);
        $product->refresh();
        $this->assertEquals(6, $product->stock_quantity);
    }

    public function test_admin_can_add_order_note(): void
    {
        $this->actingAs($this->admin);

        $order = Order::create([
            'order_number' => 'ORD-NOTE-001',
            'user_id' => $this->admin->id,
            'status' => 'pending',
            'total' => 49.99,
            'currency' => 'EUR',
        ]);

        $response = $this->post(route('dashboard.shop.orders.notes.store', $order->id), [
            'content' => 'This is a test note',
            'is_public' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertStatus(302);
        $this->assertDatabaseHas('order_notes', [
            'order_id' => $order->id,
            'content' => 'This is a test note',
            'is_public' => true,
        ]);
    }

    public function test_admin_can_delete_order_note(): void
    {
        $this->actingAs($this->admin);

        $order = Order::create([
            'order_number' => 'ORD-NOTE-002',
            'user_id' => $this->admin->id,
            'status' => 'pending',
            'total' => 29.99,
            'currency' => 'EUR',
        ]);
        $note = OrderNote::create([
            'order_id' => $order->id,
            'user_id' => $this->admin->id,
            'content' => 'Delete me',
            'is_public' => false,
        ]);

        $response = $this->delete(route('dashboard.shop.orders.notes.destroy', [$order->id, $note->id]));

        $response->assertStatus(302);
        $this->assertDatabaseMissing('order_notes', ['id' => $note->id]);
    }

    public function test_admin_can_create_draft_order(): void
    {
        $this->actingAs($this->admin);

        $product = Product::create(['name' => 'Draft Product', 'price' => 25.00, 'stock_quantity' => 10, 'track_stock' => true]);

        $response = $this->post(route('dashboard.shop.orders.store'), [
            'is_draft' => true,
            'customer_type' => 'guest',
            'customer_name' => 'Draft Customer',
            'customer_email' => 'draft@test.com',
            'billing_address' => [
                'first_name' => 'Draft',
                'last_name' => 'Customer',
                'address_line1' => '123 Draft St',
                'city' => 'Draft City',
                'country' => 'US',
            ],
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 25.00],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertStatus(302);
        $this->assertDatabaseHas('orders', ['customer_email' => 'draft@test.com', 'status' => 'draft']);
    }

    public function test_admin_can_convert_draft_order(): void
    {
        $this->actingAs($this->admin);

        $order = Order::create([
            'order_number' => 'ORD-CONVERT-001',
            'user_id' => $this->admin->id,
            'status' => 'draft',
            'total' => 75.00,
            'currency' => 'EUR',
        ]);

        $response = $this->post(route('dashboard.shop.orders.convert-from-draft', $order->id));

        $response->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertEquals('pending', $order->status);
    }

    public function test_admin_can_view_order_invoice(): void
    {
        $this->actingAs($this->admin);

        $order = Order::create([
            'order_number' => 'ORD-INV-001',
            'user_id' => $this->admin->id,
            'status' => 'completed',
            'total' => 149.99,
            'currency' => 'EUR',
        ]);

        $response = $this->get(route('dashboard.shop.orders.invoice', $order->id));

        $response->assertStatus(200);
        $response->assertSee($order->order_number);
    }

    public function test_admin_can_download_order_invoice_pdf(): void
    {
        $this->actingAs($this->admin);

        $order = Order::create([
            'order_number' => 'ORD-PDF-001',
            'user_id' => $this->admin->id,
            'status' => 'completed',
            'total' => 199.99,
            'currency' => 'EUR',
        ]);

        $response = $this->get(route('dashboard.shop.orders.invoice', [$order->id, 'download' => 1]));

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_admin_can_transition_order_status(): void
    {
        $this->actingAs($this->admin);

        $order = Order::create([
            'order_number' => 'ORD-TRANS-001',
            'user_id' => $this->admin->id,
            'status' => 'pending',
            'total' => 59.99,
            'currency' => 'EUR',
        ]);

        $response = $this->patch(route('dashboard.shop.orders.update-status', $order->id), [
            'status' => 'confirmed',
        ]);

        $response->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertEquals('confirmed', $order->status);
    }

    public function test_admin_cannot_make_invalid_status_transition(): void
    {
        $this->actingAs($this->admin);

        $order = Order::create([
            'order_number' => 'ORD-BADTRANS-001',
            'user_id' => $this->admin->id,
            'status' => 'pending',
            'total' => 10.00,
            'currency' => 'EUR',
        ]);

        $response = $this->patch(route('dashboard.shop.orders.update-status', $order->id), [
            'status' => 'completed',
        ]);

        $response->assertSessionHasErrors('status');
    }

    public function test_admin_can_edit_order(): void
    {
        $this->actingAs($this->admin);

        $product = Product::create(['name' => 'Edit Product', 'price' => 15.00, 'stock_quantity' => 20, 'track_stock' => true]);
        $order = Order::create([
            'order_number' => 'ORD-EDIT-001',
            'user_id' => $this->admin->id,
            'status' => 'pending',
            'total' => 15.00,
            'subtotal' => 15.00,
            'currency' => 'EUR',
            'customer_name' => 'Old Name',
            'customer_email' => 'old@test.com',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 15.00,
            'subtotal' => 15.00,
            'total' => 15.00,
        ]);

        $response = $this->put(route('dashboard.shop.orders.update', $order->id), [
            'status' => 'pending',
            'customer_name' => 'Updated Name',
            'customer_email' => 'updated@test.com',
            'customer_type' => 'guest',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 15.00],
            ],
            'billing_address' => [
                'first_name' => 'Updated',
                'last_name' => 'Name',
                'address_line1' => '123 Test St',
                'city' => 'Test City',
                'country' => 'US',
            ],
            'notes' => '',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertStatus(302);
        $order->refresh();
        $this->assertEquals('Updated Name', $order->customer_name);
        $this->assertEquals('updated@test.com', $order->customer_email);
    }

    public function test_order_editing_logs_action_with_notes(): void
    {
        $this->actingAs($this->admin);

        $product = Product::create(['name' => 'Action Log Product', 'price' => 20.00, 'stock_quantity' => 10, 'track_stock' => true]);
        $order = Order::create([
            'order_number' => 'ORD-ACTLOG-001',
            'user_id' => $this->admin->id,
            'status' => 'pending',
            'total' => 20.00,
            'subtotal' => 20.00,
            'currency' => 'EUR',
            'customer_name' => 'Log Test',
            'customer_email' => 'log@test.com',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 20.00,
            'subtotal' => 20.00,
            'total' => 20.00,
        ]);

        $response = $this->put(route('dashboard.shop.orders.update', $order->id), [
            'status' => 'pending',
            'customer_name' => 'Log Test',
            'customer_email' => 'log@test.com',
            'customer_type' => 'guest',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 20.00],
            ],
            'billing_address' => [
                'first_name' => 'Log',
                'last_name' => 'Test',
                'address_line1' => '123 St',
                'city' => 'Log City',
                'country' => 'US',
            ],
            'notes' => '',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertStatus(302);

        $this->assertDatabaseHas('order_actions', [
            'order_id' => $order->id,
            'action_type' => 'edited',
        ]);
    }
}
