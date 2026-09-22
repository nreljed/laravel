<?php

namespace Tests\Feature\Modules\Shop;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Nreljed\Platform\Models\Setting;
use Nreljed\Platform\Services\SettingsService;
use Nreljed\ShopModule\Models\Cart;
use Nreljed\ShopModule\Models\CartItem;
use Nreljed\ShopModule\Models\Coupon;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ShippingMethod;
use Nreljed\ShopModule\Models\TaxRate;
use Nreljed\ShopModule\Services\ShopSettingsService;
use Tests\BootsFeatures;
use Tests\TestCase;

class ShopSettingsTest extends TestCase
{
    use BootsFeatures, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFeatures();
        $this->admin = $this->createUserWithRole('admin');
    }

    // ─── Settings page ────────────────────────────────────────────────

    public function test_admin_can_view_shop_settings_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.shop.settings.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('settings')
                ->has('countries')
                ->has('currencies'));
    }

    public function test_settings_page_requires_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.shop.settings.index'))
            ->assertForbidden();
    }

    // ─── Settings persistence ─────────────────────────────────────────

    public function test_settings_are_persisted_and_readable(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.shop.settings.update'), [
                'store_name' => 'Tunisia Boutique',
                'base_country' => 'TN',
                'selling_locations_mode' => 'specific',
                'selling_locations' => ['TN', 'FR'],
                'currency_position' => 'after_space',
                'price_decimals' => 2,
                'prices_include_tax' => true,
                'tax_based_on' => 'shipping',
                'tax_shipping' => true,
                'require_terms' => true,
                'require_company_field' => true,
                'enable_guest_checkout' => false,
            ])
            ->assertRedirect();

        $service = app(ShopSettingsService::class);

        $this->assertSame('Tunisia Boutique', $service->get('store_name'));
        $this->assertTrue($service->pricesIncludeTax());
        $this->assertTrue($service->taxShipping());
        $this->assertSame('shipping', $service->taxBasedOn());
        $this->assertTrue($service->requireTerms());
        $this->assertTrue($service->requireCompanyField());
        $this->assertFalse($service->guestCheckoutEnabled());
        $this->assertSame('after_space', $service->currencyFormat()['position']);
        $this->assertTrue($service->isCountryAllowed('TN'));
        $this->assertFalse($service->isCountryAllowed('US'));
    }

    public function test_settings_validation_rejects_invalid_values(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.shop.settings.update'), [
                'selling_locations_mode' => 'bananas',
                'base_country' => 'TUN', // must be 2 chars
            ])
            ->assertSessionHasErrors(['selling_locations_mode', 'base_country']);
    }

    // ─── Checkout guards ──────────────────────────────────────────────

    public function test_guest_checkout_redirects_when_disabled(): void
    {
        Setting::updateOrCreate(
            ['key' => 'enable_guest_checkout'],
            ['value' => '0', 'group' => 'shop'],
        );
        SettingsService::clearCache();

        $this->get(route('store.checkout.index'))
            ->assertRedirect(route('login'));
    }

    public function test_checkout_requires_terms_when_enabled(): void
    {
        Setting::updateOrCreate(
            ['key' => 'require_terms'],
            ['value' => '1', 'group' => 'shop'],
        );
        SettingsService::clearCache();

        $user = User::factory()->create();
        $cart = $this->makeCart($user);

        $this->actingAs($user)
            ->post(route('store.checkout.store'), $this->validPayload())
            ->assertSessionHasErrors('accepted_terms');
    }

    public function test_checkout_requires_company_when_enabled(): void
    {
        Setting::updateOrCreate(
            ['key' => 'require_company_field'],
            ['value' => '1', 'group' => 'shop'],
        );
        SettingsService::clearCache();

        $user = User::factory()->create();
        $cart = $this->makeCart($user);

        $payload = $this->validPayload();
        $payload['billing_address']['company'] = '';

        $this->actingAs($user)
            ->post(route('store.checkout.store'), $payload)
            ->assertSessionHasErrors('billing_address.company');
    }

    public function test_checkout_blocks_disallowed_selling_country(): void
    {
        Setting::updateOrCreate(
            ['key' => 'selling_locations_mode'],
            ['value' => 'specific', 'group' => 'shop'],
        );
        Setting::updateOrCreate(
            ['key' => 'selling_locations'],
            ['value' => json_encode(['TN']), 'group' => 'shop'],
        );
        SettingsService::clearCache();

        $user = User::factory()->create();
        $cart = $this->makeCart($user);

        $payload = $this->validPayload();
        $payload['billing_address']['country'] = 'US';
        $payload['shipping_address']['country'] = 'US';

        $this->actingAs($user)
            ->post(route('store.checkout.store'), $payload)
            ->assertSessionHasErrors('billing_address.country');
    }

    // ─── Checkout summary consistency ────────────────────────────────

    public function test_checkout_summary_folds_free_shipping_coupon(): void
    {
        $user = User::factory()->create();
        $cart = $this->makeCart($user);

        // Free shipping coupon applied to the cart (subtotal 50 < free_above 60).
        // The free_shipping coupon folds the shipping cost (5) into the discount,
        // so the taxable base is 50 - 5 = 45 (Decision A: tax on post-discount subtotal).
        ShippingMethod::query()->update(['free_above' => 60]);
        $coupon = Coupon::create([
            'code' => 'FREESHIP',
            'type' => 'free_shipping',
            'value' => 0,
            'is_active' => true,
        ]);
        $cart->update([
            'coupon_code' => 'FREESHIP',
            'applied_coupons' => [
                ['coupon_id' => $coupon->id, 'code' => 'FREESHIP', 'type' => 'free_shipping', 'discount' => 0],
            ],
        ]);

        $this->actingAs($user)
            ->get(route('store.checkout.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('freeShippingApplied', true)
                ->where('estimatedTax.base', 45));
    }

    public function test_checkout_summary_estimates_tax_with_shipping_when_enabled(): void
    {
        Setting::updateOrCreate(['key' => 'tax_shipping'], ['value' => '1', 'group' => 'shop']);
        SettingsService::clearCache();

        TaxRate::create([
            'name' => 'VAT',
            'rate' => 20,
            'type' => 'percentage',
            'country' => 'TN',
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $user->update(['billing_address' => ['country' => 'TN']]);
        $cart = $this->makeCart($user); // subtotal 50, shipping 5, no free_above

        $this->actingAs($user)
            ->get(route('store.checkout.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('estimatedTax.tax_total', 11)  // 20% of (50 + 5)
                ->where('estimatedTax.base', 55));
    }

    // ─── Helpers ──────────────────────────────────────────────────────

    private function makeCart(User $user): Cart
    {
        $product = Product::create([
            'name' => 'Widget',
            'slug' => 'widget-'.uniqid(),
            'price' => 50,
            'status' => 'published',
            'type' => 'simple',
        ]);

        $cart = Cart::create(['user_id' => $user->id]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50,
        ]);

        ShippingMethod::create([
            'name' => 'Standard',
            'price' => 5,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return $cart;
    }

    private function validPayload(): array
    {
        return [
            'payment_method' => 'manual',
            'shipping_method_id' => ShippingMethod::first()->id,
            'customer_email' => 'customer@example.com',
            'customer_name' => 'Test Customer',
            'billing_address' => [
                'first_name' => 'Test',
                'last_name' => 'Customer',
                'company' => 'ACME Ltd',
                'address_line1' => '123 Main St',
                'city' => 'Tunis',
                'country' => 'TN',
                'postcode' => '1001',
                'state' => '',
                'phone' => '',
                'address_line2' => '',
            ],
            'shipping_address' => [
                'first_name' => 'Test',
                'last_name' => 'Customer',
                'company' => 'ACME Ltd',
                'address_line1' => '123 Main St',
                'city' => 'Tunis',
                'country' => 'TN',
                'postcode' => '1001',
                'state' => '',
                'phone' => '',
                'address_line2' => '',
            ],
        ];
    }
}
