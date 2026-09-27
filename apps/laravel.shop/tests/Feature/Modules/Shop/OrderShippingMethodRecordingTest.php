<?php

namespace Tests\Feature\Modules\Shop;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Nreljed\ShopModule\Models\Cart;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ShippingMethod;
use Nreljed\ShopModule\Models\ShippingZone;
use Tests\BootsFeatures;
use Tests\TestCase;

/**
 * An order records which shipping method it was bought with.
 *
 * `shipping_total` says how much shipping was charged, but not what was bought
 * for it. "Which carrier went wrong?" therefore had to be answered by matching
 * amounts against the *current* shipping table - a guess that fails the moment a
 * method is deleted, and silently misattributes the order as soon as one is
 * renamed or repriced. Neither is acceptable for a record of a sale that already
 * happened.
 *
 * This suite drives the real `POST /store/checkout` route rather than the
 * pipeline, because the point is that the value survives the whole journey
 * (offered -> resolved -> priced -> persisted), and it lives at the app level
 * for one specific reason: the package's own harness runs the real migrations
 * with `foreign_key_constraints => false`, so the `nullOnDelete` behaviour of
 * the foreign key is inert there. The app runs on `:memory:` with constraints
 * enabled, so this is the only place the delete semantics can be proven.
 */
class OrderShippingMethodRecordingTest extends TestCase
{
    use BootsFeatures, RefreshDatabase;

    private User $shopper;

    private ShippingMethod $method;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootFeatures();

        $this->shopper = $this->createUserWithRole('customer');

        $this->method = ShippingMethod::create([
            'name' => 'Express 2-Day',
            'slug' => 'express-2-day',
            'cost' => 9.50,
            'is_active' => true,
        ]);

        $this->fillCart();
    }

    // ---------------------------------------------------------------------
    // Recording
    // ---------------------------------------------------------------------

    public function test_checkout_records_the_chosen_shipping_method_on_the_order(): void
    {
        $order = $this->placeOrder();

        // The shopper's browser chose this from the methods the address was
        // offered, so this is the method that was priced - not whatever a bare
        // id lookup would have found.
        $this->assertSame($this->method->id, $order->shipping_method_id);
        $this->assertSame('Express 2-Day', $order->shipping_method_name);
        $this->assertTrue($order->shippingMethod->is($this->method));
    }

    public function test_the_recorded_method_is_the_offered_one_with_its_override_price(): void
    {
        // A zone override changes the price the shopper was shown. The order
        // must name the method that was actually bought, so the override is
        // what gets recorded and charged, not the method's base cost.
        $zone = ShippingZone::create([
            'name' => 'United Kingdom',
            'countries' => ['GB'],
            'is_active' => true,
        ]);
        $zone->methods()->attach($this->method->id, ['override_price' => 3.25]);

        $order = $this->placeOrder();

        $this->assertSame($this->method->id, $order->shipping_method_id);
        $this->assertEqualsWithDelta(3.25, (float) $order->shipping_total, 0.001);
    }

    // ---------------------------------------------------------------------
    // Deletion semantics
    // ---------------------------------------------------------------------

    public function test_deleting_a_shipping_method_nulls_the_reference_without_touching_the_order(): void
    {
        $order = $this->placeOrder();
        $orderId = $order->id;
        $shippingTotal = (float) $order->shipping_total;

        $this->method->delete();

        $order->refresh();

        // The reference is the join, not the fact, so it is allowed to go
        // dangling. A sale must never be cascade-deleted by a catalogue
        // tidy-up, so `nullOnDelete` is the only safe referential action here.
        $this->assertNotNull($order);
        $this->assertSame($orderId, $order->id);
        $this->assertNull($order->shipping_method_id);
        $this->assertNull($order->shippingMethod);

        // The name snapshot is what still answers the question, and the money
        // the shopper was charged is untouched by the catalogue change.
        $this->assertSame('Express 2-Day', $order->shipping_method_name);
        $this->assertEqualsWithDelta($shippingTotal, (float) $order->shipping_total, 0.001);
    }

    public function test_a_renamed_shipping_method_does_not_rewrite_the_order_history(): void
    {
        $order = $this->placeOrder();

        $this->method->update(['name' => 'Overnight (renamed)']);
        $order->refresh();

        // The FK resolves live, so the order still joins to the method it was
        // bought with, but the recorded name is the one on the invoice. Without
        // the snapshot, every past order silently changes its label the moment
        // a merchant edits the catalogue.
        $this->assertSame('Express 2-Day', $order->shipping_method_name);
        $this->assertTrue($order->shippingMethod->is($this->method));
    }

    // ---------------------------------------------------------------------
    // Legacy rows
    // ---------------------------------------------------------------------

    public function test_an_order_with_no_shipping_method_is_still_valid(): void
    {
        // Orders placed before the columns existed have neither value, and a
        // non-null column could not describe them honestly. This is the same
        // shape `nullOnDelete` produces, so the read path has to handle one
        // shape rather than two.
        $order = Order::create([
            'order_number' => 'LEGACY-'.strtoupper(Str::random(6)),
            'user_id' => $this->shopper->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'currency' => 'EUR',
            'total' => 0,
        ]);

        $this->assertNull($order->fresh()->shipping_method_id);
        $this->assertNull($order->fresh()->shipping_method_name);
        $this->assertNull($order->fresh()->shippingMethod);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function fillCart(): void
    {
        $product = new Product([
            'name' => 'Shipping Subject Widget',
            'type' => 'simple',
            'price' => 40.00,
            'track_stock' => false,
        ]);
        $product->status = 'published';
        $product->save();

        $cart = Cart::create(['user_id' => $this->shopper->id]);
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 40.00,
        ]);
    }

    private function placeOrder(): Order
    {
        $response = $this->actingAs($this->shopper)->post(route('store.checkout.store'), [
            'payment_method' => 'manual',
            'shipping_method_id' => $this->method->id,
            'billing_address' => $this->address(),
            'shipping_address' => $this->address(),
        ]);

        $response->assertRedirect();

        return Order::query()
            ->where('user_id', $this->shopper->id)
            ->latest('id')
            ->firstOrFail();
    }

    /**
     * @return array<string, string>
     */
    private function address(): array
    {
        return [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'address_line1' => '12 Analytical Way',
            'city' => 'London',
            'country' => 'GB',
            'postcode' => 'EC1A 1BB',
        ];
    }
}
