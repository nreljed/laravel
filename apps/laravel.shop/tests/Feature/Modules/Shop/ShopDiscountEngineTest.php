<?php

namespace Tests\Feature\Modules\Shop;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Nreljed\ShopModule\Models\Cart;
use Nreljed\ShopModule\Models\Coupon;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ShippingMethod;
use Nreljed\ShopModule\Services\CouponService;
use Tests\BootsFeatures;
use Tests\TestCase;

class ShopDiscountEngineTest extends TestCase
{
    use BootsFeatures, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFeatures();
        $this->admin = $this->createUserWithRole('admin');
    }

    private function lineItems(array $items): Collection
    {
        return new Collection(array_map(fn ($i) => (object) $i, $items));
    }

    private function makeProduct(string $name, float $price): Product
    {
        return Product::create([
            'name' => $name,
            'slug' => strtolower($name).'-'.uniqid(),
            'price' => $price,
            'status' => 'published',
            'type' => 'simple',
        ]);
    }

    // ─── BOGOF (Buy X Get Y Free) ────────────────────────────────────

    public function test_bogof_coupon_grants_free_cheapest_items(): void
    {
        $coupon = Coupon::create([
            'code' => 'BOGOF', 'type' => 'buy_x_get_y',
            'buy_qty' => 2, 'free_qty' => 1,
            'is_active' => true, 'created_by' => $this->admin->id,
        ]);

        $items = $this->lineItems([
            ['product_id' => 1, 'unit_price' => 10, 'quantity' => 2],
            ['product_id' => 2, 'unit_price' => 5, 'quantity' => 1],
        ]);

        // 3 eligible units = one (2+1) group → 1 free unit, drawn from the cheapest (€5)
        $this->assertSame(5.0, $coupon->calculateDiscount(25, null, $items));
    }

    public function test_bogof_coupon_scales_with_groups(): void
    {
        $coupon = Coupon::create([
            'code' => 'BOGOF2', 'type' => 'buy_x_get_y',
            'buy_qty' => 2, 'free_qty' => 1,
            'is_active' => true, 'created_by' => $this->admin->id,
        ]);

        $items = $this->lineItems([
            ['product_id' => 1, 'unit_price' => 10, 'quantity' => 6],
        ]);

        // 6 units = two (2+1) groups → 2 free units × €10
        $this->assertSame(20.0, $coupon->calculateDiscount(60, null, $items));
    }

    public function test_bogof_coupon_with_free_product_restricts_to_that_product(): void
    {
        $product = $this->makeProduct('Freebie', 7);
        $coupon = Coupon::create([
            'code' => 'BOGOFP', 'type' => 'buy_x_get_y',
            'buy_qty' => 2, 'free_qty' => 1,
            'free_product_id' => $product->id,
            'is_active' => true, 'created_by' => $this->admin->id,
        ]);

        $items = $this->lineItems([
            ['product_id' => $product->id, 'unit_price' => 7, 'quantity' => 3],
            ['product_id' => 999, 'unit_price' => 50, 'quantity' => 3],
        ]);

        // Only the free product line is eligible: 3 units = one group → 1 free × €7
        $this->assertSame(7.0, $coupon->calculateDiscount(171, null, $items));
    }

    public function test_bogof_returns_zero_when_insufficient_quantity(): void
    {
        $coupon = Coupon::create([
            'code' => 'BOGOF3', 'type' => 'buy_x_get_y',
            'buy_qty' => 3, 'free_qty' => 1,
            'is_active' => true, 'created_by' => $this->admin->id,
        ]);

        $items = $this->lineItems([
            ['product_id' => 1, 'unit_price' => 10, 'quantity' => 2],
        ]);

        $this->assertSame(0.0, $coupon->calculateDiscount(20, null, $items));
    }

    // ─── Product-Specific Discounts ──────────────────────────────────

    public function test_fixed_product_coupon_limits_discount_to_selected_products(): void
    {
        $p1 = $this->makeProduct('Alpha', 10);
        $this->makeProduct('Beta', 50);

        $coupon = Coupon::create([
            'code' => 'FIXP', 'type' => 'fixed_product',
            'value' => 2, 'is_active' => true, 'created_by' => $this->admin->id,
        ]);
        $coupon->products()->sync([$p1->id]);

        $items = $this->lineItems([
            ['product_id' => $p1->id, 'unit_price' => 10, 'quantity' => 3],
            ['product_id' => 999, 'unit_price' => 50, 'quantity' => 1],
        ]);

        // €2 off per unit of the selected product only → €6
        $this->assertSame(6.0, $coupon->calculateDiscount(80, null, $items));
    }

    public function test_percentage_product_coupon_limits_discount_to_selected_products(): void
    {
        $p1 = $this->makeProduct('Alpha', 100);

        $coupon = Coupon::create([
            'code' => 'PCTP', 'type' => 'percentage_product',
            'value' => 10, 'is_active' => true, 'created_by' => $this->admin->id,
        ]);
        $coupon->products()->sync([$p1->id]);

        $items = $this->lineItems([
            ['product_id' => $p1->id, 'unit_price' => 100, 'quantity' => 1],
            ['product_id' => 999, 'unit_price' => 100, 'quantity' => 1],
        ]);

        // 10% of the selected product line only → €10
        $this->assertSame(10.0, $coupon->calculateDiscount(200, null, $items));
    }

    // ─── HTTP flows ──────────────────────────────────────────────────

    public function test_apply_bogof_coupon_via_cart_endpoint(): void
    {
        $product = $this->makeProduct('Widget', 10);

        $cart = Cart::create(['user_id' => $this->admin->id]);
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 10,
        ]);

        Coupon::create([
            'code' => 'BOGOF', 'type' => 'buy_x_get_y',
            'buy_qty' => 2, 'free_qty' => 1,
            'is_active' => true, 'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('store.cart.apply-coupon'), ['code' => 'BOGOF'])
            ->assertStatus(200);

        $this->assertDatabaseHas('carts', [
            'id' => $cart->id,
            'discount' => 10.0,
        ]);
    }

    public function test_store_bogof_coupon_via_admin_form(): void
    {
        $this->actingAs($this->admin)
            ->post(route('dashboard.shop.coupons.store'), [
                'code' => 'BOGOFX',
                'type' => 'buy_x_get_y',
                'buy_qty' => 2,
                'free_qty' => 1,
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard.shop.coupons.index'));

        $this->assertDatabaseHas('coupons', [
            'code' => 'BOGOFX',
            'type' => 'buy_x_get_y',
            'buy_qty' => 2,
            'free_qty' => 1,
        ]);
    }

    public function test_store_product_specific_coupon_syncs_products(): void
    {
        $p1 = $this->makeProduct('Alpha', 10);

        $this->actingAs($this->admin)
            ->post(route('dashboard.shop.coupons.store'), [
                'code' => 'FIXP',
                'type' => 'fixed_product',
                'value' => 2,
                'product_ids' => [$p1->id],
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard.shop.coupons.index'));

        $coupon = Coupon::where('code', 'FIXP')->firstOrFail();
        $this->assertTrue($coupon->products()->where('products.id', $p1->id)->exists());
    }

    public function test_bogof_coupon_without_buy_quantity_defaults_to_buy_two(): void
    {
        $coupon = Coupon::create([
            'code' => 'BOGOFDEF', 'type' => 'buy_x_get_y',
            'free_qty' => 1, 'is_active' => true, 'created_by' => $this->admin->id,
        ]);

        $items = $this->lineItems([
            ['product_id' => 1, 'unit_price' => 10, 'quantity' => 3],
        ]);

        $this->assertSame(10.0, $coupon->calculateDiscount(30, null, $items));
    }

    // ─── Tiered / Quantity-Break Discounts ──────────────────────────

    public function test_tiered_coupon_applies_highest_met_tier(): void
    {
        $coupon = Coupon::create([
            'code' => 'TIERS', 'type' => 'tiered',
            'tiers' => [
                ['min_qty' => 3, 'discount_percent' => 5],
                ['min_qty' => 5, 'discount_percent' => 10],
                ['min_qty' => 10, 'discount_percent' => 15],
            ],
            'is_active' => true, 'created_by' => $this->admin->id,
        ]);

        $items = $this->lineItems([
            ['product_id' => 1, 'unit_price' => 10, 'quantity' => 6],
        ]);

        // 6 units ≥ 5 → 10% of €60 = €6 (15% tier needs 10 units)
        $this->assertSame(6.0, $coupon->calculateDiscount(60, null, $items));
    }

    public function test_tiered_coupon_aggregates_multiple_line_items(): void
    {
        $coupon = Coupon::create([
            'code' => 'TIERS2', 'type' => 'tiered',
            'tiers' => [
                ['min_qty' => 5, 'discount_percent' => 10],
            ],
            'is_active' => true, 'created_by' => $this->admin->id,
        ]);

        $items = $this->lineItems([
            ['product_id' => 1, 'unit_price' => 10, 'quantity' => 3],
            ['product_id' => 2, 'unit_price' => 20, 'quantity' => 2],
        ]);

        // Combined qty 5 → 10% of (30 + 40) = €7
        $this->assertSame(7.0, $coupon->calculateDiscount(70, null, $items));
    }

    public function test_tiered_coupon_returns_zero_below_min_tier(): void
    {
        $coupon = Coupon::create([
            'code' => 'TIERS3', 'type' => 'tiered',
            'tiers' => [
                ['min_qty' => 5, 'discount_percent' => 10],
            ],
            'is_active' => true, 'created_by' => $this->admin->id,
        ]);

        $items = $this->lineItems([
            ['product_id' => 1, 'unit_price' => 10, 'quantity' => 2],
        ]);

        $this->assertSame(0.0, $coupon->calculateDiscount(20, null, $items));
    }

    public function test_tiered_coupon_can_be_product_scoped(): void
    {
        $p1 = $this->makeProduct('Alpha', 10);

        $coupon = Coupon::create([
            'code' => 'TIERSP', 'type' => 'tiered',
            'tiers' => [
                ['min_qty' => 3, 'discount_percent' => 10],
            ],
            'is_active' => true, 'created_by' => $this->admin->id,
        ]);
        $coupon->products()->sync([$p1->id]);

        $items = $this->lineItems([
            ['product_id' => $p1->id, 'unit_price' => 10, 'quantity' => 4],
            ['product_id' => 999, 'unit_price' => 100, 'quantity' => 4],
        ]);

        // Only the scoped product counts: qty 4 ≥ 3 → 10% of €40 = €4
        $this->assertSame(4.0, $coupon->calculateDiscount(440, null, $items));
    }

    public function test_tiered_coupon_respects_max_discount_cap(): void
    {
        $coupon = Coupon::create([
            'code' => 'TIERSCAP', 'type' => 'tiered',
            'tiers' => [
                ['min_qty' => 2, 'discount_percent' => 50],
            ],
            'max_discount' => 5,
            'is_active' => true, 'created_by' => $this->admin->id,
        ]);

        $items = $this->lineItems([
            ['product_id' => 1, 'unit_price' => 20, 'quantity' => 2],
        ]);

        // 50% of €40 = €20, capped at €5
        $this->assertSame(5.0, $coupon->calculateDiscount(40, null, $items));
    }

    public function test_store_tiered_coupon_via_admin_form(): void
    {
        $this->actingAs($this->admin)
            ->post(route('dashboard.shop.coupons.store'), [
                'code' => 'TIERSX',
                'type' => 'tiered',
                'tiers' => [
                    ['min_qty' => 3, 'discount_percent' => 5],
                    ['min_qty' => 10, 'discount_percent' => 12],
                ],
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard.shop.coupons.index'));

        $coupon = Coupon::where('code', 'TIERSX')->firstOrFail();
        $this->assertEqualsCanonicalizing([
            ['min_qty' => 3, 'discount_percent' => 5],
            ['min_qty' => 10, 'discount_percent' => 12],
        ], $coupon->tiers);
    }

    public function test_auto_tiered_discount_applies_at_checkout(): void
    {
        $product = Product::create([
            'name' => 'Widget',
            'slug' => 'widget-auto',
            'price' => 10,
            'status' => 'published',
            'type' => 'simple',
            'track_stock' => false,
        ]);
        ShippingMethod::create(['name' => 'Standard', 'price' => 5, 'is_active' => true, 'sort_order' => 1]);

        $cart = Cart::create(['user_id' => $this->admin->id]);
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 10,
        ]);

        Coupon::create([
            'code' => 'AUTOQTY', 'type' => 'tiered',
            'tiers' => [['min_qty' => 3, 'discount_percent' => 10]],
            'is_automatic' => true,
            'is_active' => true, 'created_by' => $this->admin->id,
        ]);

        $address = [
            'first_name' => 'Nemo',
            'last_name' => 'Client',
            'address_line1' => '12 Rue X',
            'city' => 'Tunis',
            'country' => 'TN',
            'postcode' => '1000',
        ];

        // Auto-discounts must be picked up at checkout even without a code.
        $this->actingAs($this->admin)
            ->post(route('store.checkout.store'), [
                'shipping_method_id' => 1,
                'payment_method' => 'manual',
                'billing_address' => $address,
                'shipping_address' => $address,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('orders', [
            'discount_total' => 5.0,
        ]);
    }

    public function test_cart_page_folds_auto_discount_into_displayed_total(): void
    {
        $product = Product::create([
            'name' => 'Widget',
            'slug' => 'widget-cartpage',
            'price' => 10,
            'status' => 'published',
            'type' => 'simple',
            'track_stock' => false,
        ]);

        $cart = Cart::create(['user_id' => $this->admin->id]);
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 10,
        ]);

        Coupon::create([
            'code' => 'AUTODISPLAY', 'type' => 'tiered',
            'tiers' => [['min_qty' => 3, 'discount_percent' => 10]],
            'is_automatic' => true,
            'is_active' => true, 'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('store.cart.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('cart.discount', '5.00')
                ->where('cart.total', 45)
                ->has('autoDiscounts', 1));
    }

    public function test_unscoped_tiered_stacks_against_running_subtotal_with_percentage(): void
    {
        $coupon = Coupon::create([
            'code' => 'STACK', 'type' => 'tiered',
            'tiers' => [
                ['min_qty' => 5, 'discount_percent' => 10],
            ],
            'is_active' => true, 'created_by' => $this->admin->id,
        ]);

        $percentage = Coupon::create([
            'code' => 'PCT20', 'type' => 'percentage',
            'value' => 20, 'is_active' => true, 'created_by' => $this->admin->id,
        ]);

        $items = $this->lineItems([
            ['product_id' => 1, 'unit_price' => 29, 'quantity' => 5],
        ]);

        // €145 subtotal. Percentage first: 20% of 145 = €29 → running 116.
        // Unscoped tiered respects the running subtotal: 10% of 116 = €11.60.
        $stack = app(CouponService::class)
            ->calculateStackedDiscount(new Collection([$percentage, $coupon]), 145, null, $items);

        $this->assertSame(40.6, round($stack['total_discount'], 2));
        $this->assertSame(29.0, $stack['breakdown'][0]['discount']);
        $this->assertEqualsWithDelta(11.6, $stack['breakdown'][1]['discount'], 0.0001);
    }

    public function test_product_scoped_tiered_is_independent_of_running_subtotal(): void
    {
        $p1 = $this->makeProduct('Alpha', 29);

        $coupon = Coupon::create([
            'code' => 'SCOPEDSTACK', 'type' => 'tiered',
            'tiers' => [
                ['min_qty' => 5, 'discount_percent' => 10],
            ],
            'is_active' => true, 'created_by' => $this->admin->id,
        ]);
        $coupon->products()->sync([$p1->id]);

        $percentage = Coupon::create([
            'code' => 'PCT20B', 'type' => 'percentage',
            'value' => 20, 'is_active' => true, 'created_by' => $this->admin->id,
        ]);

        $items = $this->lineItems([
            ['product_id' => $p1->id, 'unit_price' => 29, 'quantity' => 5],
        ]);

        // Product-scoped tiered is independent: 10% of its own €145 line = €14.50,
        // even though the percentage coupon already reduced the running subtotal.
        $stack = app(CouponService::class)
            ->calculateStackedDiscount(new Collection([$percentage, $coupon]), 145, null, $items);

        $this->assertSame(43.5, round($stack['total_discount'], 2));
        $this->assertSame(14.5, $stack['breakdown'][1]['discount']);
    }
}
