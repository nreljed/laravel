<?php

namespace Tests\Feature\Modules\Shop;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\ShopModule\Events\WebhookEvents;
use Nreljed\ShopModule\Models\Coupon;
use Nreljed\ShopModule\Models\Currency;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductBrand;
use Nreljed\ShopModule\Models\ProductCategory;
use Nreljed\ShopModule\Models\ProductReview;
use Nreljed\ShopModule\Models\ProductTag;
use Nreljed\ShopModule\Models\ShippingMethod;
use Nreljed\ShopModule\Models\ShippingZone;
use Nreljed\ShopModule\Models\TaxRate;
use Nreljed\ShopModule\Models\Webhook;
use Tests\BootsFeatures;
use Tests\TestCase;

class ShopExtendedCrudTest extends TestCase
{
    use BootsFeatures, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFeatures();
        $this->admin = $this->createUserWithRole('admin');
    }

    // ─── Coupons ─────────────────────────────────────────────────────

    public function test_coupon_index_shows_all_coupons(): void
    {
        Coupon::create(['code' => 'CODE1', 'type' => 'percentage', 'value' => 10, 'is_active' => true, 'created_by' => $this->admin->id]);
        Coupon::create(['code' => 'CODE2', 'type' => 'fixed', 'value' => 5, 'is_active' => true, 'created_by' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->get(route('dashboard.shop.coupons.index'))
            ->assertStatus(200)
            ->assertSeeInOrder(['CODE1', 'CODE2']);
    }

    public function test_coupon_store_validates_required_fields(): void
    {
        $this->actingAs($this->admin)
            ->post(route('dashboard.shop.coupons.store'), [])
            ->assertSessionHasErrors(['code', 'type']);
    }

    public function test_coupon_store_creates_coupon(): void
    {
        $this->actingAs($this->admin)
            ->post(route('dashboard.shop.coupons.store'), [
                'code' => 'SUMMER20',
                'type' => 'percentage',
                'value' => 20,
                'min_order_amount' => 50,
                'max_discount' => 30,
                'usage_limit' => 100,
                'is_active' => true,
            ]);

        $this->assertDatabaseHas('coupons', [
            'code' => 'SUMMER20',
            'type' => 'percentage',
            'value' => 20.0,
            'is_active' => true,
        ]);
    }

    public function test_coupon_update_modifies_coupon(): void
    {
        $coupon = Coupon::create(['code' => 'ORIG', 'type' => 'percentage', 'value' => 10, 'is_active' => true, 'created_by' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->put(route('dashboard.shop.coupons.update', $coupon->id), [
                'code' => 'UPDATED',
                'type' => 'fixed',
                'value' => 25,
            ]);

        $this->assertDatabaseHas('coupons', ['id' => $coupon->id, 'code' => 'UPDATED', 'value' => 25.0]);
    }

    public function test_coupon_toggle_active(): void
    {
        $coupon = Coupon::create(['code' => 'TOGGLE', 'type' => 'percentage', 'value' => 10, 'is_active' => true, 'created_by' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->post(route('dashboard.shop.coupons.toggle-active', $coupon->id));

        $this->assertDatabaseHas('coupons', ['id' => $coupon->id, 'is_active' => false]);
    }

    public function test_coupon_destroy_deletes_coupon(): void
    {
        $coupon = Coupon::create(['code' => 'DELETE', 'type' => 'percentage', 'value' => 10, 'is_active' => true, 'created_by' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->delete(route('dashboard.shop.coupons.destroy', $coupon->id));

        $this->assertDatabaseMissing('coupons', ['id' => $coupon->id]);
    }

    // ─── Shipping Methods ────────────────────────────────────────────

    public function test_shipping_method_index(): void
    {
        ShippingMethod::create(['name' => 'Express', 'price' => 15, 'is_active' => true, 'sort_order' => 1]);

        $this->actingAs($this->admin)
            ->get(route('dashboard.shop.shipping-methods.index'))
            ->assertStatus(200)
            ->assertSee('Express');
    }

    public function test_shipping_method_store(): void
    {
        $this->actingAs($this->admin)
            ->post(route('dashboard.shop.shipping-methods.store'), [
                'name' => 'Standard',
                'price' => 5.99,
                'estimated_days_min' => 3,
                'estimated_days_max' => 7,
                'free_above' => 100,
                'sort_order' => 1,
                'is_active' => true,
            ]);

        $this->assertDatabaseHas('shipping_methods', ['name' => 'Standard', 'price' => 5.99]);
    }

    public function test_shipping_method_update(): void
    {
        $method = ShippingMethod::create(['name' => 'Old Name', 'price' => 5, 'is_active' => true, 'sort_order' => 1]);

        $this->actingAs($this->admin)
            ->put(route('dashboard.shop.shipping-methods.update', $method->id), [
                'name' => 'New Name', 'price' => 10, 'sort_order' => 2,
            ]);

        $this->assertDatabaseHas('shipping_methods', ['id' => $method->id, 'name' => 'New Name', 'price' => 10.0]);
    }

    public function test_shipping_method_toggle_active(): void
    {
        $method = ShippingMethod::create(['name' => 'Toggle Ship', 'price' => 5, 'is_active' => true, 'sort_order' => 1]);

        $this->actingAs($this->admin)
            ->post(route('dashboard.shop.shipping-methods.toggle-active', $method->id));

        $this->assertDatabaseHas('shipping_methods', ['id' => $method->id, 'is_active' => false]);
    }

    public function test_shipping_method_destroy(): void
    {
        $method = ShippingMethod::create(['name' => 'Del Ship', 'price' => 5, 'is_active' => true, 'sort_order' => 1]);

        $this->actingAs($this->admin)
            ->delete(route('dashboard.shop.shipping-methods.destroy', $method->id));

        $this->assertDatabaseMissing('shipping_methods', ['id' => $method->id]);
    }

    // ─── Shipping Zones ──────────────────────────────────────────────

    public function test_shipping_zone_index(): void
    {
        ShippingZone::create(['name' => 'North America', 'countries' => ['US', 'CA'], 'is_active' => true]);

        $this->actingAs($this->admin)
            ->get(route('dashboard.shop.shipping-zones.index'))
            ->assertStatus(200)
            ->assertSee('North America');
    }

    public function test_shipping_zone_store(): void
    {
        $method = ShippingMethod::create(['name' => 'Zone Ship', 'price' => 5, 'is_active' => true, 'sort_order' => 1]);

        $this->actingAs($this->admin)
            ->post(route('dashboard.shop.shipping-zones.store'), [
                'name' => 'Europe',
                'countries' => ['DE', 'FR', 'IT'],
                'methods' => [['id' => $method->id, 'override_price' => 8]],
            ]);

        $this->assertDatabaseHas('shipping_zones', ['name' => 'Europe']);
    }

    public function test_shipping_zone_update(): void
    {
        $zone = ShippingZone::create(['name' => 'Old Zone', 'countries' => ['US'], 'is_active' => true]);

        $this->actingAs($this->admin)
            ->put(route('dashboard.shop.shipping-zones.update', $zone->id), [
                'name' => 'Updated Zone', 'countries' => ['GB'], 'methods' => [],
            ]);

        $this->assertDatabaseHas('shipping_zones', ['id' => $zone->id, 'name' => 'Updated Zone']);
    }

    public function test_shipping_zone_destroy(): void
    {
        $zone = ShippingZone::create(['name' => 'Del Zone', 'countries' => ['US'], 'is_active' => true]);

        $this->actingAs($this->admin)
            ->delete(route('dashboard.shop.shipping-zones.destroy', $zone->id));

        $this->assertDatabaseMissing('shipping_zones', ['id' => $zone->id]);
    }

    // ─── Tax Rates ───────────────────────────────────────────────────

    public function test_tax_rate_index(): void
    {
        TaxRate::create(['name' => 'VAT', 'rate' => 20, 'type' => 'percentage', 'country' => 'GB', 'is_active' => true]);

        $this->actingAs($this->admin)
            ->get(route('dashboard.shop.tax-rates.index'))
            ->assertStatus(200)
            ->assertSee('VAT');
    }

    public function test_tax_rate_store(): void
    {
        $this->actingAs($this->admin)
            ->post(route('dashboard.shop.tax-rates.store'), [
                'name' => 'Sales Tax',
                'rate' => 8.5,
                'type' => 'percentage',
                'country' => 'US',
                'is_active' => true,
            ]);

        $this->assertDatabaseHas('tax_rates', ['name' => 'Sales Tax', 'rate' => 8.5]);
    }

    public function test_tax_rate_update(): void
    {
        $tax = TaxRate::create(['name' => 'Old Tax', 'rate' => 10, 'type' => 'percentage', 'country' => 'US', 'is_active' => true]);

        $this->actingAs($this->admin)
            ->put(route('dashboard.shop.tax-rates.update', $tax->id), [
                'name' => 'New Tax', 'rate' => 12, 'type' => 'percentage',
            ]);

        $this->assertDatabaseHas('tax_rates', ['id' => $tax->id, 'name' => 'New Tax', 'rate' => 12.0]);
    }

    public function test_tax_rate_toggle_active(): void
    {
        $tax = TaxRate::create(['name' => 'Toggle Tax', 'rate' => 10, 'type' => 'percentage', 'country' => 'US', 'is_active' => true]);

        $this->actingAs($this->admin)
            ->post(route('dashboard.shop.tax-rates.toggle-active', $tax->id));

        $this->assertDatabaseHas('tax_rates', ['id' => $tax->id, 'is_active' => false]);
    }

    public function test_tax_rate_destroy(): void
    {
        $tax = TaxRate::create(['name' => 'Del Tax', 'rate' => 10, 'type' => 'percentage', 'country' => 'US', 'is_active' => true]);

        $this->actingAs($this->admin)
            ->delete(route('dashboard.shop.tax-rates.destroy', $tax->id));

        $this->assertDatabaseMissing('tax_rates', ['id' => $tax->id]);
    }

    // ─── Currencies ──────────────────────────────────────────────────

    public function test_currency_index(): void
    {
        Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'exchange_rate' => 1.0, 'decimal_places' => 2, 'is_active' => true]);

        $this->actingAs($this->admin)
            ->get(route('dashboard.shop.currencies.index'))
            ->assertStatus(200)
            ->assertSee('USD');
    }

    public function test_currency_store(): void
    {
        $this->actingAs($this->admin)
            ->post(route('dashboard.shop.currencies.store'), [
                'code' => 'EUR',
                'name' => 'Euro',
                'symbol' => '€',
                'exchange_rate' => 0.92,
                'decimal_places' => 2,
                'is_active' => true,
            ]);

        $this->assertDatabaseHas('currencies', ['code' => 'EUR', 'exchange_rate' => 0.92]);
    }

    public function test_currency_update(): void
    {
        $currency = Currency::create(['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'exchange_rate' => 0.78, 'decimal_places' => 2, 'is_active' => true]);

        $this->actingAs($this->admin)
            ->put(route('dashboard.shop.currencies.update', $currency->id), [
                'code' => 'GBP',
                'name' => 'British Pound Sterling',
                'symbol' => '£',
                'exchange_rate' => 0.79,
                'decimal_places' => 2,
            ]);

        $this->assertDatabaseHas('currencies', ['id' => $currency->id, 'exchange_rate' => 0.79]);
    }

    public function test_currency_toggle_active(): void
    {
        $currency = Currency::create(['code' => 'JPY', 'name' => 'Yen', 'symbol' => '¥', 'exchange_rate' => 150, 'decimal_places' => 0, 'is_active' => true]);

        $this->actingAs($this->admin)
            ->post(route('dashboard.shop.currencies.toggle-active', $currency->id));

        $this->assertDatabaseHas('currencies', ['id' => $currency->id, 'is_active' => false]);
    }

    public function test_currency_destroy(): void
    {
        $currency = Currency::create(['code' => 'CHF', 'name' => 'Franc', 'symbol' => '₣', 'exchange_rate' => 0.9, 'decimal_places' => 2, 'is_active' => true]);

        $this->actingAs($this->admin)
            ->delete(route('dashboard.shop.currencies.destroy', $currency->id));

        $this->assertDatabaseMissing('currencies', ['id' => $currency->id]);
    }

    // ─── Webhooks ────────────────────────────────────────────────────

    public function test_webhook_index(): void
    {
        Webhook::create(['name' => 'Test Webhook', 'url' => 'https://test.com/webhook', 'secret' => 'sec', 'events' => ['order.placed'], 'is_active' => true]);

        $this->actingAs($this->admin)
            ->get(route('dashboard.shop.webhooks.index'))
            ->assertStatus(200)
            ->assertSee('Test Webhook');
    }

    public function test_webhook_store(): void
    {
        $this->actingAs($this->admin)
            ->post(route('dashboard.shop.webhooks.store'), [
                'name' => 'API Hook',
                'url' => 'https://api.example.com/hook',
                'secret' => 'my-secret-key',
                'events' => ['order.placed', 'order.updated'],
                'is_active' => true,
            ]);

        $this->assertDatabaseHas('webhooks', ['url' => 'https://api.example.com/hook']);
    }

    public function test_webhook_store_rejects_invalid_events(): void
    {
        // This test used to post the bad payload and assert nothing, so it was
        // green whether the rule existed or not. `events.*` is closed with
        // `Rule::in(WebhookEvents::all())` precisely so a typo or an invented
        // event cannot register a hook that silently never fires (or, worse,
        // one whose name collides with a real event added later). Pin both
        // halves: the error names the offending entry, and nothing is stored.
        $response = $this->actingAs($this->admin)
            ->post(route('dashboard.shop.webhooks.store'), [
                'name' => 'Invalid Hook',
                'url' => 'https://example.com/hook',
                'secret' => 'sec',
                'events' => ['invalid.event'],
            ]);

        $response->assertSessionHasErrors('events.0');

        $this->assertDatabaseMissing('webhooks', ['url' => 'https://example.com/hook']);
    }

    public function test_webhook_store_rejects_a_mix_of_valid_and_invalid_events(): void
    {
        // The mixed case is the one `Rule::in` on `events.*` is easy to get
        // wrong - a whole-array rule would reject the pair, but so would a rule
        // that only ever looked at the first entry. A partially valid payload
        // has to be refused outright: a hook registered against one real event
        // and one imaginary one looks configured and is not.
        $this->actingAs($this->admin)
            ->post(route('dashboard.shop.webhooks.store'), [
                'name' => 'Half Hook',
                'url' => 'https://example.com/half',
                'secret' => 'sec',
                'events' => [WebhookEvents::ORDER_PLACED, 'not.a.real.event'],
            ])
            ->assertSessionHasErrors('events.1');

        $this->assertDatabaseMissing('webhooks', ['url' => 'https://example.com/half']);
    }

    public function test_webhook_update(): void
    {
        $webhook = Webhook::create(['name' => 'Old Webhook', 'url' => 'https://old.com/hook', 'secret' => 'old', 'events' => ['order.placed'], 'is_active' => true]);

        $this->actingAs($this->admin)
            ->put(route('dashboard.shop.webhooks.update', $webhook->id), [
                'name' => 'Updated Webhook',
                'url' => 'https://new.com/hook', 'secret' => 'new', 'events' => ['order.placed', 'order.refunded'],
            ]);

        $this->assertDatabaseHas('webhooks', ['id' => $webhook->id, 'url' => 'https://new.com/hook']);
    }

    public function test_webhook_toggle_active(): void
    {
        $webhook = Webhook::create(['name' => 'Toggle Hook', 'url' => 'https://toggle.com/hook', 'secret' => 's', 'events' => ['order.placed'], 'is_active' => true]);

        $this->actingAs($this->admin)
            ->post(route('dashboard.shop.webhooks.toggle-active', $webhook->id));

        $this->assertDatabaseHas('webhooks', ['id' => $webhook->id, 'is_active' => false]);
    }

    public function test_webhook_destroy(): void
    {
        $webhook = Webhook::create(['name' => 'Del Hook', 'url' => 'https://del.com/hook', 'secret' => 's', 'events' => ['order.placed'], 'is_active' => true]);

        $this->actingAs($this->admin)
            ->delete(route('dashboard.shop.webhooks.destroy', $webhook->id));

        $this->assertDatabaseMissing('webhooks', ['id' => $webhook->id]);
    }

    // ─── Brands ──────────────────────────────────────────────────────

    public function test_brand_index(): void
    {
        ProductBrand::create(['name' => 'Nike', 'slug' => 'nike', 'is_active' => true]);

        $this->actingAs($this->admin)
            ->get(route('dashboard.shop.brands.index'))
            ->assertStatus(200)
            ->assertSee('Nike');
    }

    public function test_brand_store(): void
    {
        $this->actingAs($this->admin)
            ->post(route('dashboard.shop.brands.store'), [
                'name' => 'Adidas',
                'description' => 'Sportswear brand',
                'sort_order' => 1,
                'is_active' => true,
            ]);

        $this->assertDatabaseHas('product_brands', ['name' => 'Adidas']);
    }

    public function test_brand_update(): void
    {
        $brand = ProductBrand::create(['name' => 'Old Brand', 'slug' => 'old-brand', 'is_active' => true]);

        $this->actingAs($this->admin)
            ->put(route('dashboard.shop.brands.update', $brand->id), [
                'name' => 'Updated Brand',
                'description' => 'Updated description',
            ]);

        $this->assertDatabaseHas('product_brands', ['id' => $brand->id, 'name' => 'Updated Brand']);
    }

    public function test_brand_destroy(): void
    {
        $brand = ProductBrand::create(['name' => 'Del Brand', 'slug' => 'del-brand', 'is_active' => true]);

        $this->actingAs($this->admin)
            ->delete(route('dashboard.shop.brands.destroy', $brand->id));

        $this->assertDatabaseMissing('product_brands', ['id' => $brand->id]);
    }

    // ─── Product Tags ────────────────────────────────────────────────

    public function test_tag_index(): void
    {
        ProductTag::create(['name' => 'Eco', 'slug' => 'eco']);
        ProductTag::create(['name' => 'Premium', 'slug' => 'premium']);

        $this->actingAs($this->admin)
            ->get(route('dashboard.shop.tags.index'))
            ->assertStatus(200)
            ->assertSeeInOrder(['Eco', 'Premium']);
    }

    public function test_tag_store(): void
    {
        $this->actingAs($this->admin)
            ->post(route('dashboard.shop.tags.store'), [
                'name' => 'Handmade',
            ]);

        $this->assertDatabaseHas('product_tags', ['name' => 'Handmade']);
    }

    public function test_tag_store_returns_json_when_wants_json(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('dashboard.shop.tags.store'), [
                'name' => 'Artisan',
            ]);

        $response->assertOk()
            ->assertJsonStructure(['id', 'name']);
    }

    public function test_tag_update(): void
    {
        $tag = ProductTag::create(['name' => 'Old', 'slug' => 'old']);

        $this->actingAs($this->admin)
            ->put(route('dashboard.shop.tags.update', $tag->id), [
                'name' => 'Renamed',
            ]);

        $this->assertDatabaseHas('product_tags', ['id' => $tag->id, 'name' => 'Renamed']);
    }

    public function test_tag_update_returns_json_when_wants_json(): void
    {
        $tag = ProductTag::create(['name' => 'Orig', 'slug' => 'orig']);

        $response = $this->actingAs($this->admin)
            ->putJson(route('dashboard.shop.tags.update', $tag->id), [
                'name' => 'Updated JSON',
            ]);

        $response->assertOk()
            ->assertJsonStructure(['id', 'name']);
    }

    public function test_tag_destroy(): void
    {
        $tag = ProductTag::create(['name' => 'Del Tag', 'slug' => 'del-tag']);

        $this->actingAs($this->admin)
            ->delete(route('dashboard.shop.tags.destroy', $tag->id));

        $this->assertDatabaseMissing('product_tags', ['id' => $tag->id]);
    }

    // ─── Product Reviews ─────────────────────────────────────────────

    public function test_review_index_shows_pending_reviews(): void
    {
        $product = Product::create(['name' => 'Reviewed Product', 'slug' => 'reviewed', 'price' => 10, 'status' => 'published']);
        $user = User::factory()->create();

        ProductReview::create(['product_id' => $product->id, 'user_id' => $user->id, 'rating' => 4, 'title' => 'Good', 'content' => 'Nice', 'is_approved' => false]);

        $this->actingAs($this->admin)
            ->get(route('dashboard.shop.reviews.index'))
            ->assertStatus(200)
            ->assertSee('Good');
    }

    public function test_review_store_creates_unapproved_review(): void
    {
        $product = Product::create(['name' => 'Prod', 'slug' => 'prod', 'price' => 10, 'status' => 'published']);

        $this->actingAs($this->admin)
            ->post(route('dashboard.shop.reviews.store', $product), [
                'rating' => 5,
                'title' => 'Amazing',
                'content' => 'Really great product!',
            ]);

        $this->assertDatabaseHas('product_reviews', [
            'product_id' => $product->id,
            'user_id' => $this->admin->id,
            'rating' => 5,
            'is_approved' => false,
        ]);
    }

    public function test_review_reject_sets_not_approved(): void
    {
        $product = Product::create(['name' => 'Prod', 'slug' => 'prod', 'price' => 10, 'status' => 'published']);
        $user = User::factory()->create();
        $review = ProductReview::create(['product_id' => $product->id, 'user_id' => $user->id, 'rating' => 2, 'title' => 'Bad', 'content' => 'Poor quality', 'is_approved' => true]);

        $this->actingAs($this->admin)
            ->post(route('dashboard.shop.reviews.reject', $review->id));

        $this->assertDatabaseHas('product_reviews', ['id' => $review->id, 'is_approved' => false]);
    }

    public function test_review_destroy(): void
    {
        $product = Product::create(['name' => 'Prod', 'slug' => 'prod', 'price' => 10, 'status' => 'published']);
        $user = User::factory()->create();
        $review = ProductReview::create(['product_id' => $product->id, 'user_id' => $user->id, 'rating' => 3, 'title' => 'OK', 'content' => 'Fine', 'is_approved' => false]);

        $this->actingAs($this->admin)
            ->delete(route('dashboard.shop.reviews.destroy', $review->id));

        $this->assertDatabaseMissing('product_reviews', ['id' => $review->id]);
    }

    // ─── Product (categories sync) ───────────────────────────────────

    public function test_product_store_syncs_categories(): void
    {
        $cat1 = ProductCategory::create(['name' => 'Electronics', 'slug' => 'electronics']);
        $cat2 = ProductCategory::create(['name' => 'Gadgets', 'slug' => 'gadgets']);

        $this->actingAs($this->admin)
            ->post(route('dashboard.shop.products.store'), [
                'name' => 'Gadget',
                'price' => 99,
                'status' => 'draft',
                'categories' => [$cat1->id, $cat2->id],
            ]);

        $product = Product::where('name', 'Gadget')->first();
        $this->assertNotNull($product);
        $this->assertCount(2, $product->categories);
    }

    // ─── Product Brands (sync) ───────────────────────────────────────

    public function test_product_store_syncs_brands(): void
    {
        $brand = ProductBrand::create(['name' => 'Sony', 'slug' => 'sony', 'is_active' => true]);

        $this->actingAs($this->admin)
            ->post(route('dashboard.shop.products.store'), [
                'name' => 'Sony Camera',
                'price' => 499,
                'status' => 'draft',
                'brands' => [$brand->id],
            ]);

        $product = Product::where('name', 'Sony Camera')->first();
        $this->assertNotNull($product);
        $this->assertCount(1, $product->brands);
    }

    // ─── Product Tags (sync) ─────────────────────────────────────────

    public function test_product_store_syncs_tags(): void
    {
        $tag = ProductTag::create(['name' => 'New Arrival', 'slug' => 'new-arrival']);

        $this->actingAs($this->admin)
            ->post(route('dashboard.shop.products.store'), [
                'name' => 'Tagged Product',
                'price' => 25,
                'status' => 'draft',
                'tags' => [$tag->id],
            ]);

        $product = Product::where('name', 'Tagged Product')->first();
        $this->assertNotNull($product);
        $this->assertCount(1, $product->tags);
    }

    // ─── Storefront ──────────────────────────────────────────────────

    public function test_storefront_product_list_is_public(): void
    {
        Product::create(['name' => 'Public Product', 'slug' => 'public', 'price' => 10, 'status' => 'published']);

        $this->get(route('store.products'))
            ->assertStatus(200)
            ->assertSee('Public Product');
    }

    public function test_storefront_product_detail_is_public(): void
    {
        $product = Product::create(['name' => 'Detail Prod', 'slug' => 'detail', 'price' => 20, 'status' => 'published']);

        $this->get(route('store.show', $product->id))
            ->assertStatus(200)
            ->assertSee('Detail Prod');
    }

    // ─── Export ──────────────────────────────────────────────────────

    public function test_product_export_returns_csv(): void
    {
        Product::create(['name' => 'Export Me', 'slug' => 'export-me', 'price' => 15, 'status' => 'published']);

        $this->actingAs($this->admin)
            ->get(route('dashboard.shop.products.export'))
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
}
