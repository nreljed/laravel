<?php

namespace Tests\Feature\Modules\Shop;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Models\OrderItem;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductReview;
use Tests\BootsFeatures;
use Tests\TestCase;

/**
 * Staff screens must actually render.
 *
 * Three actions resolved a relationship against the platform User model, which
 * has no `orders()`: the customer list behind Create Order and Edit Order, and
 * the verified-purchase lookup behind review submission. All three threw a
 * BadMethodCallException before doing any work, so every staff user met a 500.
 *
 * The package's own suite cannot see this class of fault: it runs on a bare
 * kernel where controller bodies never execute. These tests drive the real
 * routes so the fault has somewhere to show up.
 */
class ShopStaffFormAccessTest extends TestCase
{
    use BootsFeatures, RefreshDatabase;

    private User $shopOwner;

    private User $customer;

    private Product $product;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootFeatures();

        $this->shopOwner = $this->createUserWithRole('shop_owner');
        $this->customer = $this->createUserWithRole('customer');

        $this->product = Product::create([
            'name' => 'Staff Form Product',
            'slug' => 'staff-form-product',
            'status' => 'published',
            'price' => 40,
        ]);

        $this->order = Order::create([
            'order_number' => 'STAFF-FORM-001',
            'user_id' => $this->customer->id,
            'status' => 'processing',
            'payment_status' => 'paid',
            'subtotal' => 40,
            'total' => 40,
            'total_refunded' => 0,
            'currency' => 'EUR',
        ]);

        OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $this->product->id,
            'product_name' => 'Staff Form Product',
            'quantity' => 1,
            'unit_price' => 40,
            'subtotal' => 40,
            'tax_total' => 0,
            'total' => 40,
        ]);
    }

    public function test_the_create_order_screen_renders(): void
    {
        $this->actingAs($this->shopOwner)
            ->get(route('dashboard.shop.orders.create'))
            ->assertOk();
    }

    public function test_the_edit_order_screen_renders(): void
    {
        $this->actingAs($this->shopOwner)
            ->get(route('dashboard.shop.orders.edit', $this->order))
            ->assertOk();
    }

    public function test_the_create_order_screen_lists_a_customer_who_has_ordered(): void
    {
        $response = $this->actingAs($this->shopOwner)->get(route('dashboard.shop.orders.create'));

        $response->assertOk();

        // Inertia embeds the page props in the root view, so the rendered
        // document itself proves the customer list was populated rather than
        // merely that the query did not throw.
        $response->assertSee($this->customer->email, escape: false);
    }

    public function test_a_customer_cannot_reach_the_order_form(): void
    {
        $this->actingAs($this->customer)
            ->get(route('dashboard.shop.orders.create'))
            ->assertForbidden();
    }

    public function test_review_submission_resolves_the_qualifying_order_without_throwing(): void
    {
        $this->actingAs($this->shopOwner)
            ->post(route('dashboard.shop.reviews.store', $this->product), [
                'rating' => 4,
                'title' => 'Solid',
                'content' => 'Arrived quickly and does what it says.',
            ])
            ->assertRedirect();

        $review = ProductReview::where('product_id', $this->product->id)
            ->where('user_id', $this->shopOwner->id)
            ->first();

        $this->assertNotNull($review, 'The review was not recorded.');

        // The reviewer never completed an order containing the product, so the
        // lookup has to answer null rather than raise.
        $this->assertNull($review->order_id);
    }

    public function test_review_submission_links_a_completed_order_for_the_reviewer(): void
    {
        $order = Order::create([
            'order_number' => 'STAFF-FORM-002',
            'user_id' => $this->shopOwner->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 40,
            'total' => 40,
            'total_refunded' => 0,
            'currency' => 'EUR',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => 'Staff Form Product',
            'quantity' => 1,
            'unit_price' => 40,
            'subtotal' => 40,
            'tax_total' => 0,
            'total' => 40,
        ]);

        $this->actingAs($this->shopOwner)
            ->post(route('dashboard.shop.reviews.store', $this->product), [
                'rating' => 5,
                'title' => 'Exactly as described',
                'content' => 'Bought it, used it, no complaints.',
            ])
            ->assertRedirect();

        $review = ProductReview::where('product_id', $this->product->id)
            ->where('user_id', $this->shopOwner->id)
            ->first();

        $this->assertNotNull($review, 'The review was not recorded.');
        $this->assertSame(
            $order->id,
            $review->order_id,
            'A qualifying completed order was not attached to the review.'
        );
    }

    public function test_a_duplicate_review_is_refused_rather_than_stored_twice(): void
    {
        $this->actingAs($this->shopOwner)
            ->post(route('dashboard.shop.reviews.store', $this->product), [
                'rating' => 4,
                'title' => 'First',
                'content' => 'The original review.',
            ])
            ->assertRedirect();

        $this->actingAs($this->shopOwner)
            ->post(route('dashboard.shop.reviews.store', $this->product), [
                'rating' => 1,
                'title' => 'Second',
                'content' => 'A second attempt at the same product.',
            ])
            ->assertRedirect();

        $this->assertSame(
            1,
            ProductReview::where('product_id', $this->product->id)
                ->where('user_id', $this->shopOwner->id)
                ->count(),
            'A duplicate review was stored.'
        );
    }
}
