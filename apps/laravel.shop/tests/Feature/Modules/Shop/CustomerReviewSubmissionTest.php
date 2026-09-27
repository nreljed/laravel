<?php

namespace Tests\Feature\Modules\Shop;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Nreljed\Platform\Models\Setting;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Models\OrderItem;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductReview;
use Tests\BootsFeatures;
use Tests\TestCase;

/**
 * A shopper must be able to review a product they bought.
 *
 * Review submission had exactly one route, `dashboard.shop.reviews.store`,
 * declared in `Routes/web.php` - a file the provider loads behind
 * `permission:shop.manage`. The storefront rendered the approved reviews list
 * but no way to add to it, so the one action the page implied was a route no
 * customer could reach: a 403 for every shopper, and a rating nobody outside
 * staff could ever earn.
 *
 * These tests drive the real routes, because the package suite runs on a bare
 * kernel where middleware and route groups are never resolved.
 */
class CustomerReviewSubmissionTest extends TestCase
{
    use BootsFeatures, RefreshDatabase;

    private User $customer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootFeatures();

        $this->customer = $this->createUserWithRole('customer');

        $this->product = Product::create([
            'name' => 'Reviewable Product',
            'slug' => 'reviewable-product',
            'status' => 'published',
            'price' => 25,
        ]);
    }

    public function test_the_customer_review_route_is_not_staff_gated(): void
    {
        $this->actingAs($this->customer)
            ->post(route('store.reviews.store', $this->product), [
                'rating' => 5,
                'title' => 'Worth every penny',
                'content' => 'Used it for a month and it has held up well.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('product_reviews', [
            'product_id' => $this->product->id,
            'user_id' => $this->customer->id,
            'rating' => 5,
        ]);
    }

    public function test_a_customer_review_waits_for_moderation(): void
    {
        $this->actingAs($this->customer)
            ->post(route('store.reviews.store', $this->product), [
                'rating' => 4,
                'title' => 'Good',
                'content' => 'No complaints at all.',
            ])
            ->assertRedirect();

        $review = ProductReview::where('product_id', $this->product->id)
            ->where('user_id', $this->customer->id)
            ->first();

        $this->assertNotNull($review, 'The review was not recorded.');
        $this->assertFalse(
            $review->is_approved,
            'A customer review must not publish itself; it has to pass moderation first.'
        );
    }

    public function test_a_customer_review_links_the_completed_order(): void
    {
        $order = Order::create([
            'order_number' => 'CUST-REVIEW-001',
            'user_id' => $this->customer->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 25,
            'total' => 25,
            'total_refunded' => 0,
            'currency' => 'EUR',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => 'Reviewable Product',
            'quantity' => 1,
            'unit_price' => 25,
            'subtotal' => 25,
            'tax_total' => 0,
            'total' => 25,
        ]);

        $this->actingAs($this->customer)
            ->post(route('store.reviews.store', $this->product), [
                'rating' => 5,
                'title' => 'Exactly what I wanted',
                'content' => 'Arrived two days early.',
            ])
            ->assertRedirect();

        $review = ProductReview::where('product_id', $this->product->id)
            ->where('user_id', $this->customer->id)
            ->first();

        $this->assertNotNull($review, 'The review was not recorded.');
        $this->assertSame(
            $order->id,
            $review->order_id,
            'The qualifying completed order was not attached to the review.'
        );
    }

    public function test_a_guest_is_sent_to_log_in_rather_than_stored(): void
    {
        $this->post(route('store.reviews.store', $this->product), [
            'rating' => 5,
            'title' => 'Anonymous',
            'content' => 'A review from nobody in particular.',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('product_reviews', 0);
    }

    public function test_an_unverified_customer_cannot_review(): void
    {
        // `User::hasVerifiedEmail()` answers true for everyone unless the
        // `require_email_verification` setting is on, so the `verified`
        // middleware is a no-op without it (and the review would be stored).
        Setting::set('require_email_verification', '1');

        $this->customer->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($this->customer->fresh())
            ->post(route('store.reviews.store', $this->product), [
                'rating' => 5,
                'title' => 'Too early',
                'content' => 'An account that never proved its address.',
            ])
            ->assertRedirect(route('verification.notice'));

        $this->assertDatabaseCount('product_reviews', 0);
    }

    public function test_a_rating_is_required(): void
    {
        $this->actingAs($this->customer)
            ->from(route('store.show', $this->product->slug))
            ->post(route('store.reviews.store', $this->product), [
                'rating' => 0,
                'title' => 'No stars',
                'content' => 'Submitted without choosing a rating.',
            ])
            ->assertRedirect(route('store.show', $this->product->slug))
            ->assertSessionHasErrors('rating');

        $this->assertDatabaseCount('product_reviews', 0);
    }

    public function test_a_customer_cannot_review_the_same_product_twice(): void
    {
        $this->actingAs($this->customer)
            ->post(route('store.reviews.store', $this->product), [
                'rating' => 5,
                'title' => 'First',
                'content' => 'The original review.',
            ])
            ->assertRedirect();

        $this->actingAs($this->customer)
            ->post(route('store.reviews.store', $this->product), [
                'rating' => 1,
                'title' => 'Second',
                'content' => 'A second attempt at the same product.',
            ])
            ->assertRedirect();

        $this->assertSame(
            1,
            ProductReview::where('product_id', $this->product->id)
                ->where('user_id', $this->customer->id)
                ->count(),
            'A duplicate review was stored.'
        );
    }

    /**
     * `component()`'s second argument turns off the `ensure_pages_exist` lookup.
     * It is off deliberately: this app resolves Inertia pages from the frontend
     * `page-config.ts` map, and the framework's server-side finder still points at
     * a default `resources/js/pages` directory that does not exist here, so the
     * existence check fails for every page in the suite. The component name is
     * still asserted, which is the part that catches a controller renaming the
     * page its frontend registers.
     */
    public function test_the_storefront_tells_a_reviewer_their_review_exists(): void
    {
        $review = ProductReview::create([
            'product_id' => $this->product->id,
            'user_id' => $this->customer->id,
            'rating' => 4,
            'title' => 'Already here',
            'content' => 'Submitted on an earlier visit.',
            'is_approved' => false,
        ]);

        // The page can only hide the form from someone who already reviewed, and
        // say it is still awaiting moderation, if the controller hands it their
        // own review. Those facts live in the `userReview` prop; the sentences a
        // shopper reads are rendered by React, so asserting on the response body
        // proves nothing either way.
        $this->actingAs($this->customer)
            ->get(route('store.show', $this->product->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('shop/storefront/productdetail', false)
                ->where('userReview.id', $review->id)
                ->where('userReview.rating', 4)
                ->where('userReview.title', 'Already here')
                ->where('userReview.is_approved', false));
    }

    public function test_the_storefront_keeps_unmoderated_reviews_and_reviewer_state_off_guest_pages(): void
    {
        ProductReview::create([
            'product_id' => $this->product->id,
            'user_id' => $this->customer->id,
            'rating' => 5,
            'title' => 'Not for the public',
            'content' => 'A pending review that must never reach a guest.',
            'is_approved' => false,
        ]);

        $this->get(route('store.show', $this->product->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('shop/storefront/productdetail', false)
                ->where('userReview', null)
                ->has('product.reviews', 0))
            ->assertDontSee('A pending review that must never reach a guest.');
    }
}
