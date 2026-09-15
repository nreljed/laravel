<?php

namespace Tests\Feature\Modules\Shop;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\ShopModule\Models\Coupon;
use Nreljed\ShopModule\Models\Currency;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductCategory;
use Nreljed\ShopModule\Models\ProductReview;
use Nreljed\ShopModule\Models\ProductTag;
use Nreljed\ShopModule\Models\ShippingMethod;
use Nreljed\ShopModule\Models\ShippingZone;
use Nreljed\ShopModule\Models\TaxRate;
use Nreljed\ShopModule\Models\Webhook;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\BootsFeatures;
use Tests\TestCase;

class ShopRoleAccessTest extends TestCase
{
    use BootsFeatures, RefreshDatabase;

    private User $admin;

    private User $shopOwner;

    private User $shopManager;

    private User $customer;

    private User $guest;

    private Product $product;

    private Order $order;

    private ProductCategory $category;

    private ProductTag $tag;

    private Coupon $coupon;

    private ProductReview $review;

    private ShippingMethod $shippingMethod;

    private ShippingZone $shippingZone;

    private TaxRate $taxRate;

    private Currency $currency;

    private Webhook $webhook;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFeatures();

        $this->admin = $this->createUserWithRole('admin');
        $this->shopOwner = $this->createUserWithRole('shop_owner');
        $this->shopManager = $this->createUserWithRole('shop_manager');
        $this->customer = $this->createUserWithRole('customer');

        $this->seedTestData();
    }

    private function seedTestData(): void
    {
        $this->product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'price' => 19.99,
            'status' => 'published',
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD-ROLE-TEST',
            'user_id' => $this->customer->id,
            'status' => 'pending',
            'total' => 19.99,
            'currency' => 'EUR',
        ]);

        $this->category = ProductCategory::create(['name' => 'Test Category', 'slug' => 'test-category']);

        $this->tag = ProductTag::create(['name' => 'Test Tag', 'slug' => 'test-tag']);

        $this->coupon = Coupon::create([
            'code' => 'TEST10',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $this->review = ProductReview::create([
            'product_id' => $this->product->id,
            'user_id' => $this->customer->id,
            'rating' => 5,
            'title' => 'Great!',
            'content' => 'Love it',
            'is_approved' => false,
        ]);

        $this->shippingMethod = ShippingMethod::create([
            'name' => 'Test Shipping',
            'price' => 5.00,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->shippingZone = ShippingZone::create([
            'name' => 'Test Zone',
            'countries' => ['US'],
            'is_active' => true,
        ]);

        $this->taxRate = TaxRate::create([
            'name' => 'Test Tax',
            'rate' => 10,
            'type' => 'percentage',
            'country' => 'US',
            'is_active' => true,
        ]);

        $this->currency = Currency::create([
            'code' => 'GBP',
            'name' => 'British Pound',
            'symbol' => '£',
            'exchange_rate' => 0.86,
            'is_active' => true,
        ]);

        $this->webhook = Webhook::create([
            'name' => 'Test Webhook',
            'url' => 'https://example.com/webhook',
            'secret' => 'test-secret',
            'events' => ['order.placed'],
            'is_active' => true,
        ]);
    }

    // ─── Role-based access test provider ─────────────────────────────

    /** @return array<string, array{0: string, 1: string, 2: int, 3: string|null}> */
    public static function shopEndpointAccessProvider(): array
    {
        return [
            // Products
            'admin can view products list' => ['admin', 'dashboard.shop.products.index', 200, null],
            'shop_owner can view products list' => ['shop_owner', 'dashboard.shop.products.index', 200, null],
            'shop_manager can view products list' => ['shop_manager', 'dashboard.shop.products.index', 200, null],
            'customer blocked from products list' => ['customer', 'dashboard.shop.products.index', 403, null],
            'guest redirect from products list' => ['guest', 'dashboard.shop.products.index', 302, null],

            'admin can view product create form' => ['admin', 'dashboard.shop.products.create', 200, null],
            'shop_owner can view product create form' => ['shop_owner', 'dashboard.shop.products.create', 200, null],
            'shop_manager blocked from product create' => ['shop_manager', 'dashboard.shop.products.create', 403, null],

            'admin can view product edit' => ['admin', 'dashboard.shop.products.edit', 200, 'product'],
            'shop_owner can view product edit' => ['shop_owner', 'dashboard.shop.products.edit', 200, 'product'],

            'admin can view product show' => ['admin', 'dashboard.shop.products.show', 200, 'product'],
            'shop_owner can view product show' => ['shop_owner', 'dashboard.shop.products.show', 200, 'product'],

            // Orders
            'admin can view orders list' => ['admin', 'dashboard.shop.orders.index', 200, null],
            'shop_owner can view orders list' => ['shop_owner', 'dashboard.shop.orders.index', 200, null],
            'shop_manager can view orders list' => ['shop_manager', 'dashboard.shop.orders.index', 200, null],

            'admin can view order detail' => ['admin', 'dashboard.shop.orders.show', 200, 'order'],
            'shop_owner can view order detail' => ['shop_owner', 'dashboard.shop.orders.show', 200, 'order'],

            // Categories
            'admin can view categories list' => ['admin', 'dashboard.shop.categories.index', 200, null],
            'shop_owner can view categories list' => ['shop_owner', 'dashboard.shop.categories.index', 200, null],

            // Tags
            'admin can view tags list' => ['admin', 'dashboard.shop.tags.index', 200, null],
            'shop_owner can view tags list' => ['shop_owner', 'dashboard.shop.tags.index', 200, null],

            // Coupons
            'admin can view coupons list' => ['admin', 'dashboard.shop.coupons.index', 200, null],
            'shop_owner can view coupons list' => ['shop_owner', 'dashboard.shop.coupons.index', 200, null],

            'admin can view coupon create form' => ['admin', 'dashboard.shop.coupons.create', 200, null],
            'shop_owner can view coupon create form' => ['shop_owner', 'dashboard.shop.coupons.create', 200, null],

            'admin can view coupon edit' => ['admin', 'dashboard.shop.coupons.edit', 200, 'coupon'],
            'shop_owner can view coupon edit' => ['shop_owner', 'dashboard.shop.coupons.edit', 200, 'coupon'],

            // Reviews
            'admin can view reviews list' => ['admin', 'dashboard.shop.reviews.index', 200, null],
            'shop_owner can view reviews list' => ['shop_owner', 'dashboard.shop.reviews.index', 200, null],

            // Shipping Methods
            'admin can view shipping methods list' => ['admin', 'dashboard.shop.shipping-methods.index', 200, null],
            'shop_owner can view shipping methods list' => ['shop_owner', 'dashboard.shop.shipping-methods.index', 200, null],

            // Shipping Zones
            'admin can view shipping zones list' => ['admin', 'dashboard.shop.shipping-zones.index', 200, null],
            'shop_owner can view shipping zones list' => ['shop_owner', 'dashboard.shop.shipping-zones.index', 200, null],

            // Tax Rates
            'admin can view tax rates list' => ['admin', 'dashboard.shop.tax-rates.index', 200, null],
            'shop_owner can view tax rates list' => ['shop_owner', 'dashboard.shop.tax-rates.index', 200, null],

            // Currencies
            'admin can view currencies list' => ['admin', 'dashboard.shop.currencies.index', 200, null],
            'shop_owner can view currencies list' => ['shop_owner', 'dashboard.shop.currencies.index', 200, null],

            // Webhooks
            'admin can view webhooks list' => ['admin', 'dashboard.shop.webhooks.index', 200, null],
            'shop_owner can view webhooks list' => ['shop_owner', 'dashboard.shop.webhooks.index', 200, null],

            // Reports
            'admin can view reports' => ['admin', 'dashboard.shop.reports.index', 200, null],
            'shop_owner can view reports' => ['shop_owner', 'dashboard.shop.reports.index', 200, null],

            // Customers
            'admin can view customers list' => ['admin', 'dashboard.shop.customers.index', 200, null],
            'shop_owner can view customers list' => ['shop_owner', 'dashboard.shop.customers.index', 200, null],

            // Dashboard
            'admin can view shop dashboard' => ['admin', 'dashboard.shop.dashboard', 200, null],
            'shop_owner can view shop dashboard' => ['shop_owner', 'dashboard.shop.dashboard', 200, null],
            'shop_manager can view shop dashboard' => ['shop_manager', 'dashboard.shop.dashboard', 200, null],
        ];
    }

    #[DataProvider('shopEndpointAccessProvider')]
    public function test_shop_get_endpoint_access(string $role, string $route, int $expectedStatus, ?string $param): void
    {
        $params = [];
        if ($param && isset($this->{$param})) {
            $params = [$this->{$param}->id];
        }

        if ($role === 'guest') {
            $response = $this->get(route($route, $params));
            $response->assertStatus($expectedStatus);

            return;
        }

        $roleProp = str_replace('_', '', ucwords($role, '_'));
        $roleProp = lcfirst($roleProp);
        $this->actingAs($this->{$roleProp});

        $response = $this->get(route($route, $params));
        $response->assertStatus($expectedStatus);
    }

    // ─── POST mutation tests ────────────────────────────────────────

    public function test_admin_can_create_product(): void
    {
        $this->actingAs($this->admin);
        $response = $this->post(route('dashboard.shop.products.store'), [
            'name' => 'New Product', 'price' => 10, 'status' => 'draft',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['name' => 'New Product']);
    }

    public function test_shop_owner_can_create_product(): void
    {
        $this->actingAs($this->shopOwner);
        $response = $this->post(route('dashboard.shop.products.store'), [
            'name' => 'Owner Product', 'price' => 15, 'status' => 'draft',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['name' => 'Owner Product']);
    }

    public function test_shop_manager_cannot_create_product(): void
    {
        $this->actingAs($this->shopManager);
        $response = $this->post(route('dashboard.shop.products.store'), [
            'name' => 'Should Not Exist', 'price' => 10, 'status' => 'draft',
        ]);
        $response->assertStatus(403);
    }

    public function test_customer_cannot_create_product(): void
    {
        $this->actingAs($this->customer);
        $response = $this->post(route('dashboard.shop.products.store'), [
            'name' => 'Should Not Exist', 'price' => 10, 'status' => 'draft',
        ]);
        $response->assertStatus(403);
    }

    public function test_admin_can_delete_product(): void
    {
        $this->actingAs($this->admin);
        $response = $this->delete(route('dashboard.shop.products.destroy', $this->product->id));
        $response->assertSessionHasNoErrors();
        $this->assertSoftDeleted('products', ['id' => $this->product->id]);
    }

    public function test_shop_owner_can_delete_product(): void
    {
        $this->actingAs($this->shopOwner);
        $response = $this->delete(route('dashboard.shop.products.destroy', $this->product->id));
        $response->assertSessionHasNoErrors();
        $this->assertSoftDeleted('products', ['id' => $this->product->id]);
    }

    public function test_shop_manager_cannot_delete_product(): void
    {
        $this->actingAs($this->shopManager);
        $response = $this->delete(route('dashboard.shop.products.destroy', $this->product->id));
        $response->assertStatus(403);
    }

    // ─── Coupon ──────────────────────────────────────────────────────

    public function test_admin_can_create_coupon(): void
    {
        $this->actingAs($this->admin);
        $response = $this->post(route('dashboard.shop.coupons.store'), [
            'code' => 'SAVE20', 'type' => 'percentage', 'value' => 20,
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('coupons', ['code' => 'SAVE20']);
    }

    public function test_shop_owner_can_create_coupon(): void
    {
        $this->actingAs($this->shopOwner);
        $response = $this->post(route('dashboard.shop.coupons.store'), [
            'code' => 'OWNER10', 'type' => 'fixed', 'value' => 10,
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('coupons', ['code' => 'OWNER10']);
    }

    public function test_shop_manager_cannot_create_coupon(): void
    {
        $this->actingAs($this->shopManager);
        $response = $this->post(route('dashboard.shop.coupons.store'), [
            'code' => 'HACKED', 'type' => 'percentage', 'value' => 100,
        ]);
        $response->assertStatus(403);
    }

    // ─── Review moderation ───────────────────────────────────────────

    public function test_admin_can_approve_review(): void
    {
        $this->actingAs($this->admin);
        $response = $this->post(route('dashboard.shop.reviews.approve', $this->review->id));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('product_reviews', ['id' => $this->review->id, 'is_approved' => 1]);
    }

    public function test_shop_owner_can_approve_review(): void
    {
        $this->actingAs($this->shopOwner);
        $response = $this->post(route('dashboard.shop.reviews.approve', $this->review->id));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('product_reviews', ['id' => $this->review->id, 'is_approved' => 1]);
    }

    public function test_shop_manager_cannot_approve_review(): void
    {
        $this->actingAs($this->shopManager);
        $response = $this->post(route('dashboard.shop.reviews.approve', $this->review->id));
        $response->assertStatus(403);
    }

    // ─── Shipping ────────────────────────────────────────────────────

    public function test_admin_can_create_shipping_method(): void
    {
        $this->actingAs($this->admin);
        $response = $this->post(route('dashboard.shop.shipping-methods.store'), [
            'name' => 'Overnight', 'price' => 25, 'sort_order' => 5,
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('shipping_methods', ['name' => 'Overnight']);
    }

    public function test_shop_owner_can_create_shipping_method(): void
    {
        $this->actingAs($this->shopOwner);
        $response = $this->post(route('dashboard.shop.shipping-methods.store'), [
            'name' => 'Owner Ship', 'price' => 10, 'sort_order' => 2,
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('shipping_methods', ['name' => 'Owner Ship']);
    }

    public function test_shop_manager_cannot_create_shipping_method(): void
    {
        $this->actingAs($this->shopManager);
        $response = $this->post(route('dashboard.shop.shipping-methods.store'), [
            'name' => 'Hacked Ship', 'price' => 0, 'sort_order' => 1,
        ]);
        $response->assertStatus(403);
    }

    // ─── Tax ─────────────────────────────────────────────────────────

    public function test_admin_can_create_tax_rate(): void
    {
        $this->actingAs($this->admin);
        $response = $this->post(route('dashboard.shop.tax-rates.store'), [
            'name' => 'VAT 15%', 'rate' => 15, 'type' => 'percentage',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tax_rates', ['name' => 'VAT 15%']);
    }

    public function test_shop_owner_can_create_tax_rate(): void
    {
        $this->actingAs($this->shopOwner);
        $response = $this->post(route('dashboard.shop.tax-rates.store'), [
            'name' => 'Owner Tax', 'rate' => 5, 'type' => 'percentage',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tax_rates', ['name' => 'Owner Tax']);
    }

    public function test_shop_manager_cannot_create_tax_rate(): void
    {
        $this->actingAs($this->shopManager);
        $response = $this->post(route('dashboard.shop.tax-rates.store'), [
            'name' => 'Hacked Tax', 'rate' => 0, 'type' => 'percentage',
        ]);
        $response->assertStatus(403);
    }

    // ─── Webhook ─────────────────────────────────────────────────────

    public function test_admin_can_create_webhook(): void
    {
        $this->actingAs($this->admin);
        $response = $this->post(route('dashboard.shop.webhooks.store'), [
            'name' => 'API Hook', 'url' => 'https://example.com/hook', 'secret' => 's3cr3t', 'events' => ['order.placed'],
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('webhooks', ['url' => 'https://example.com/hook']);
    }

    public function test_shop_owner_can_create_webhook(): void
    {
        $this->actingAs($this->shopOwner);
        $response = $this->post(route('dashboard.shop.webhooks.store'), [
            'name' => 'Owner Hook', 'url' => 'https://owner.example.com/hook', 'secret' => 'sec', 'events' => ['order.placed'],
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('webhooks', ['url' => 'https://owner.example.com/hook']);
    }

    public function test_shop_manager_cannot_create_webhook(): void
    {
        $this->actingAs($this->shopManager);
        $response = $this->post(route('dashboard.shop.webhooks.store'), [
            'name' => 'Evil Hook', 'url' => 'https://evil.example.com/hook', 'secret' => 'x', 'events' => ['order.placed'],
        ]);
        $response->assertStatus(403);
    }

    // ─── Currency ────────────────────────────────────────────────────

    public function test_admin_can_create_currency(): void
    {
        $this->actingAs($this->admin);
        $response = $this->post(route('dashboard.shop.currencies.store'), [
            'code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥', 'exchange_rate' => 150, 'decimal_places' => 0,
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('currencies', ['code' => 'JPY']);
    }

    public function test_shop_owner_can_create_currency(): void
    {
        $this->actingAs($this->shopOwner);
        $response = $this->post(route('dashboard.shop.currencies.store'), [
            'code' => 'AUD', 'name' => 'Australian Dollar', 'symbol' => 'A$', 'exchange_rate' => 1.5, 'decimal_places' => 2,
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('currencies', ['code' => 'AUD']);
    }

    public function test_shop_manager_cannot_create_currency(): void
    {
        $this->actingAs($this->shopManager);
        $response = $this->post(route('dashboard.shop.currencies.store'), [
            'code' => 'XXX', 'name' => 'Fake', 'symbol' => 'X', 'exchange_rate' => 1, 'decimal_places' => 2,
        ]);
        $response->assertStatus(403);
    }

    // ─── Admin-only routes ───────────────────────────────────────────

    public function test_non_admin_cannot_access_shop_settings(): void
    {
        $nonAdminRoles = ['shop_owner', 'shop_manager', 'customer'];
        foreach ($nonAdminRoles as $role) {
            $roleProp = str_replace('_', '', ucwords($role, '_'));
            $roleProp = lcfirst($roleProp);
            $this->actingAs($this->{$roleProp});
            $response = $this->get(route('dashboard.admin.settings.index'));
            $response->assertStatus(403, "Role {$role} should get 403 on admin settings");
        }
    }

    public function test_guest_redirected_from_all_shop_pages(): void
    {
        $guestRoutes = [
            'dashboard.shop.products.index',
            'dashboard.shop.orders.index',
            'dashboard.shop.coupons.index',
            'dashboard.shop.reports.index',
        ];

        foreach ($guestRoutes as $route) {
            $response = $this->get(route($route));
            $response->assertRedirect(route('login', absolute: false));
        }
    }
}
