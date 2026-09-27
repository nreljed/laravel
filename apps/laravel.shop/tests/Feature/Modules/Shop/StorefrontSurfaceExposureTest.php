<?php

namespace Tests\Feature\Modules\Shop;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Nreljed\ShopModule\Models\Cart;
use Nreljed\ShopModule\Models\CartItem;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductCategory;
use Nreljed\ShopModule\Models\ProductVariation;
use Nreljed\ShopModule\Models\ShippingMethod;
use Nreljed\ShopModule\Models\ShippingZone;
use Tests\BootsFeatures;
use Tests\TestCase;

/**
 * The surfaces `StorefrontCatalogVisibilityTest` deliberately did not cover.
 *
 * That suite is about one product's journey: the grid, the search box, its own
 * URL, the cart, checkout. Those are the paths a shopper walks to a product.
 *
 * These are the paths that answer a question *about* a product or about the
 * catalog around it, and every one of them either leaked or was untested:
 *
 *  - `GET /api/orders/{order}` bound any order off the URL and returned it, so
 *    any signed-in customer could read any other customer's order - names,
 *    email, phone, both addresses, every line.
 *  - `GET /store/cart` and `/store/cart/json` are public and both serialised
 *    `items.product` straight off the relation. `Product` declares no
 *    `$hidden` (so the admin form can still bind `cost_price`), which made one
 *    cart line a complete dump of the merchant's own figures.
 *  - the sitemap, the category/brand/attribute facets, the price bounds, the
 *    bundle children, and three write endpoints that accepted a product id and
 *    behaved differently for a hidden product than for one that never existed.
 *
 * Package-level tests cover the queries; these drive the routes, because only a
 * route can say whether a request is a 403, a 404, a 422, or a 200 carrying
 * someone else's address.
 */
class StorefrontSurfaceExposureTest extends TestCase
{
    use BootsFeatures, RefreshDatabase;

    private User $shopper;

    private User $stranger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootFeatures();

        $this->shopper = $this->createUserWithRole('customer');
        $this->stranger = $this->createUserWithRole('customer');
    }

    // ---------------------------------------------------------------------
    // Order ownership.
    // ---------------------------------------------------------------------

    public function test_a_customer_can_read_their_own_order_over_the_api(): void
    {
        $order = $this->order($this->shopper);

        $this->actingAs($this->shopper, 'sanctum')
            ->getJson(route('api.orders.show', $order))
            ->assertOk()
            ->assertJsonPath('data.id', $order->id);
    }

    public function test_a_customer_cannot_read_another_customs_order(): void
    {
        $order = $this->order($this->shopper);

        // The IDOR this pins was reachable by incrementing the id: the route
        // model bound whatever the URL named and returned it on a route that
        // only required a session.
        $this->actingAs($this->stranger, 'sanctum')
            ->getJson(route('api.orders.show', $order))
            ->assertForbidden();
    }

    public function test_the_api_order_stays_closed_to_staff_who_are_not_the_buyer(): void
    {
        $order = $this->order($this->shopper);
        $staff = $this->createUserWithRole('shop_owner');

        // Deliberate, and the reason `viewOwn` exists separately from `view`.
        // This is a "my orders" resource, so the only question it asks is
        // "is this yours". Staff have `dashboard.shop.orders.show` for the
        // merchant view, which is a different route with a different rule -
        // folding the two together would make a 403 here ambiguous about which
        // permission was missing.
        $this->actingAs($staff, 'sanctum')
            ->getJson(route('api.orders.show', $order))
            ->assertForbidden();
    }

    public function test_the_guest_confirmation_route_still_works_after_the_ownership_gate(): void
    {
        $order = $this->order($this->shopper);
        $signed = URL::temporarySignedRoute(
            'store.checkout.confirmation',
            now()->addMinutes(15),
            ['order' => $order->id],
        );

        $this->actingAs($this->shopper)
            ->get($signed)
            ->assertOk();
    }

    public function test_a_customer_cannot_open_another_customs_order_from_their_account(): void
    {
        $order = $this->order($this->shopper);

        $this->actingAs($this->stranger)
            ->get(route('dashboard.my.orders.show', $order))
            ->assertForbidden();
    }

    public function test_an_order_with_no_owner_is_not_readable_by_guessing_its_id(): void
    {
        $order = $this->order($this->shopper);

        // `user_id` is nullOnDelete, so a deleted account leaves the order
        // behind. A null owner must not read as "nobody owns it, so anyone may".
        $order->update(['user_id' => null]);

        $this->actingAs($this->stranger, 'sanctum')
            ->getJson(route('api.orders.show', $order))
            ->assertForbidden();
    }

    /**
     * The confirmation page is behind the token/owner gate, so the exposure is
     * not "anyone can read any order" - it is that the page published the
     * columns it has no business showing. The sharpest is
     * `confirmation_token`: the emailed credential for opening this very page,
     * rendered into the response of the page it unlocks.
     *
     * Asserted against the prop rather than the response body: the body echoes
     * the request URL, so passing the token as a query string would make this
     * assertion pass or fail for reasons that have nothing to do with the prop.
     */
    public function test_the_confirmation_page_does_not_publish_the_orders_internal_columns(): void
    {
        $order = $this->order($this->shopper);
        $order->update([
            'confirmation_token' => 'tok-supersecret',
            'payment_intent_id' => 'pi_1234567890',
            'notes' => 'Customer flagged by the fraud team',
            'refund_notes' => 'Refund approved by finance, do not re-issue',
            // A real user: `processed_by` is a foreign key, and the point is
            // the key reaching the payload, not whose id it holds.
            'processed_by' => $this->shopper->id,
        ]);
        $order->items()->create([
            'product_name' => 'Kettle',
            'quantity' => 1,
            'unit_price' => 20,
            'subtotal' => 20,
            'total' => 20,
        ]);

        $prop = $this->orderConfirmationProp($order, 'tok-supersecret');

        foreach (['confirmation_token', 'payment_intent_id', 'notes', 'refund_notes', 'processed_by'] as $internal) {
            $this->assertArrayNotHasKey(
                $internal,
                $prop,
                "`{$internal}` reached the shopper's confirmation page."
            );
        }

        $this->assertStringNotContainsString('tok-supersecret', json_encode($prop));
        $this->assertStringNotContainsString('pi_1234567890', json_encode($prop));
        $this->assertStringNotContainsString('fraud team', json_encode($prop));
    }

    /**
     * The same exclusion, proven on the path the token actually exists for.
     *
     * `orderConfirmationProp()` signs the shopper in, so it exercises the owner
     * half of `authorizeOrderView()`. The token is the other half, and the one
     * that matters most: a guest order has no account, so the emailed token is
     * the *only* thing standing between the shopper's own order and a stranger.
     * Excluding the internal columns is therefore load-bearing on this path and
     * has to be asserted here, with no authenticated user at all - otherwise
     * `confirmation_token` is excluded only on the one route where the caller
     * already had rights.
     */
    public function test_the_confirmation_page_excludes_internals_for_a_token_only_guest(): void
    {
        $order = $this->guestOrder();
        $order->update([
            'confirmation_token' => 'tok-guest-only',
            'payment_intent_id' => 'pi_guest_123',
            'notes' => 'Guest flagged for review',
            'refund_notes' => 'Internal refund rationale',
        ]);
        $order->items()->create([
            'product_name' => 'Travel Kettle',
            'quantity' => 1,
            'unit_price' => 25,
            'subtotal' => 25,
            'total' => 25,
        ]);

        // Deliberately no `actingAs()`: the token is the sole credential.
        $prop = null;

        $this->get(route('store.checkout.confirmation', $order).'?token=tok-guest-only')
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$prop) {
                $prop = $page->toArray()['props']['order'];
            });

        $this->assertIsArray($prop, 'The order prop did not arrive; the privacy assertions would pass vacuously.');

        $this->assertSame($order->id, $prop['id']);
        $this->assertSame('Travel Kettle', $prop['items'][0]['product_name']);

        foreach (['confirmation_token', 'payment_intent_id', 'notes', 'refund_notes'] as $internal) {
            $this->assertArrayNotHasKey(
                $internal,
                $prop,
                "`{$internal}` reached a token-only guest's confirmation page."
            );
        }

        $this->assertStringNotContainsString('tok-guest-only', json_encode($prop));
        $this->assertStringNotContainsString('Guest flagged for review', json_encode($prop));
    }

    public function test_a_token_only_guest_without_the_token_is_rejected(): void
    {
        $order = $this->guestOrder();
        $order->update(['confirmation_token' => 'tok-guest-only']);
        $order->items()->create([
            'product_name' => 'Travel Kettle',
            'quantity' => 1,
            'unit_price' => 25,
            'subtotal' => 25,
            'total' => 25,
        ]);

        $url = route('store.checkout.confirmation', $order);

        // No account and no token, and no signature either - the same anonymous
        // request, minus the one thing that was supposed to open it. A 403 here
        // is what proves the previous test passed *because* of the token, and
        // not because guest orders are simply readable.
        $this->get($url)->assertForbidden();
        $this->get($url.'?token=tok-wrong')->assertForbidden();

        // A signed-in stranger is still a stranger: owning an account is not
        // ownership of somebody else's order.
        $this->actingAs($this->stranger)->get($url)->assertForbidden();
    }

    /**
     * The trimmed page still has to be a working page.
     */
    public function test_the_confirmation_page_still_shows_the_order(): void
    {
        $order = $this->order($this->shopper);
        $order->items()->create([
            'product_name' => 'Copper Kettle',
            'quantity' => 2,
            'unit_price' => 20,
            'subtotal' => 40,
            'total' => 40,
        ]);

        $prop = $this->orderConfirmationProp($order);

        $this->assertSame($order->id, $prop['id']);
        $this->assertSame($order->order_number, $prop['order_number']);
        $this->assertEqualsWithDelta((float) $order->total, (float) $prop['total'], 0.001);
        $this->assertSame('Copper Kettle', $prop['items'][0]['product_name']);
        $this->assertSame(2, $prop['items'][0]['quantity']);
    }

    /**
     * The cart page and the cart JSON feed were trimmed; the checkout page,
     * which is where the money is actually spent, rendered the same cart raw
     * with `items.product` loaded. Guest-reachable either way.
     */
    public function test_the_checkout_page_does_not_publish_the_merchants_figures(): void
    {
        $this->cartWith($this->product(['cost_price' => 3.1, 'price_hash' => 'deadbeefcafe']));
        ShippingMethod::create(['name' => 'Standard', 'price' => 5, 'is_active' => true, 'sort_order' => 1]);

        $cart = $this->checkoutCartProp();

        $this->assertIsArray($cart, 'The cart prop did not arrive; the assertions below would pass vacuously.');
        $this->assertNotEmpty($cart['items']);
        $this->assertNotContains('cost_price', $this->productKeys($cart));
        $this->assertNotContains('price_hash', $this->productKeys($cart));
        $this->assertStringNotContainsString('deadbeefcafe', json_encode($cart));
    }

    public function test_the_checkout_page_still_shows_the_cart(): void
    {
        $this->cartWith($this->product(['name' => 'Copper Kettle']));
        ShippingMethod::create(['name' => 'Standard', 'price' => 5, 'is_active' => true, 'sort_order' => 1]);

        $cart = $this->checkoutCartProp();

        $this->assertIsArray($cart, 'The cart prop did not arrive; the assertions below would pass vacuously.');
        $this->assertSame('Copper Kettle', $cart['items'][0]['product']['name']);
    }

    /**
     * @return array<string, mixed>
     */
    private function orderConfirmationProp(Order $order, ?string $token = null): array
    {
        $url = route('store.checkout.confirmation', $order);

        if ($token !== null) {
            $url .= '?token='.$token;
        }

        $prop = null;

        $this->actingAs($this->shopper)
            ->get($url)
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$prop) {
                $prop = $page->toArray()['props']['order'];
            });

        $this->assertIsArray($prop, 'The order prop did not arrive; the privacy assertions would pass vacuously.');

        return $prop;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function checkoutCartProp(): ?array
    {
        $cart = null;

        $this->actingAs($this->shopper)
            ->get(route('store.checkout.index'))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$cart) {
                $cart = $page->toArray()['props']['cart'];
            });

        return $cart;
    }

    // ---------------------------------------------------------------------
    // Cart privacy.
    // ---------------------------------------------------------------------

    public function test_the_cart_page_does_not_publish_the_merchants_figures(): void
    {
        $this->cartWith($this->product(['cost_price' => 3.1, 'price_hash' => 'deadbeefcafe']));

        $payload = $this->cartProps();

        $this->assertNotContains('cost_price', $this->productKeys($payload));
        $this->assertNotContains('price_hash', $this->productKeys($payload));
        $this->assertStringNotContainsString('deadbeefcafe', json_encode($payload));
    }

    public function test_the_cart_json_feed_does_not_publish_them_either(): void
    {
        $this->cartWith($this->product(['cost_price' => 3.1, 'price_hash' => 'deadbeefcafe']));

        // The badge polls this endpoint, so it is the widest-reaching product
        // payload the app emits - and it is unauthenticated.
        $body = $this->getJson(route('store.cart.json'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('cost_price', $body);
        $this->assertStringNotContainsString('price_hash', $body);
        $this->assertStringNotContainsString('deadbeefcafe', $body);
        $this->assertStringNotContainsString('3.1', $body);
    }

    public function test_the_cart_still_carries_what_the_page_renders(): void
    {
        $this->cartWith($this->product(['name' => 'Kettle', 'sku' => 'K-1']));

        $product = $this->cartProps()['items'][0]['product'];

        foreach (['id', 'name', 'slug', 'price', 'sku', 'track_stock', 'is_in_stock'] as $key) {
            $this->assertArrayHasKey($key, $product, "`{$key}` is read by ShoppingCart.tsx; dropping it white-screens the cart.");
        }
    }

    public function test_a_withdrawn_line_stays_in_the_cart_and_is_flagged_unavailable(): void
    {
        $product = $this->product(['name' => 'Withdrawn Kettle']);
        $this->cartWith($product);
        $product->update(['catalog_visibility' => 'hidden']);

        // The line is not hidden: a shopper who added it should be able to see
        // what is stopping them, and the cart pipeline already refuses to sell
        // it. The flag is how the page stops pretending the basket is fine.
        $line = $this->cartProps()['items'][0];

        $this->assertFalse($line['available'], 'A withdrawn product cannot be bought and the cart has to say so.');
        $this->assertSame('Withdrawn Kettle', $line['product']['name']);
    }

    public function test_a_search_only_line_is_still_available(): void
    {
        $product = $this->product(['name' => 'Findable Kettle', 'catalog_visibility' => 'search']);
        $this->cartWith($product);

        $this->assertTrue(
            $this->cartProps()['items'][0]['available'],
            'A search-only product is reachable and buyable; flagging it would block a legitimate order.'
        );
    }

    // ---------------------------------------------------------------------
    // Bundle children.
    // ---------------------------------------------------------------------

    public function test_a_withdrawn_bundle_child_is_not_rendered_on_its_parents_page(): void
    {
        $bundle = $this->product(['name' => 'Plain Cable', 'price' => 10]);
        $withdrawn = $this->product(['name' => 'Withdrawn Deluxe Cable', 'price' => 400, 'catalog_visibility' => 'hidden']);
        $parent = $this->product(['name' => 'Cable Kit', 'type' => 'grouped', 'price' => 0]);

        $parent->groupedItems()->attach([
            $bundle->id => ['quantity' => 1],
            $withdrawn->id => ['quantity' => 1],
        ]);

        $this->get(route('store.show', $parent))
            ->assertOk()
            ->assertDontSee('Withdrawn Deluxe Cable', false)
            ->assertSee('Plain Cable', false);
    }

    // ---------------------------------------------------------------------
    // Sitemap.
    // ---------------------------------------------------------------------

    public function test_the_sitemap_submits_no_withdrawn_product(): void
    {
        $shown = $this->product(['name' => 'Listed Kettle']);
        $withdrawn = $this->product(['name' => 'Secret Kettle', 'catalog_visibility' => 'hidden']);

        $xml = $this->get(route('sitemap'))->assertOk()->getContent();

        $this->assertStringContainsString($shown->slug, $xml);
        $this->assertStringNotContainsString($withdrawn->slug, $xml);
    }

    // ---------------------------------------------------------------------
    // Existence oracles.
    // ---------------------------------------------------------------------

    public function test_back_in_stock_signups_answer_the_same_for_a_hidden_product_as_for_nothing(): void
    {
        $hidden = $this->product(['name' => 'Secret Kettle', 'catalog_visibility' => 'hidden']);
        $missing = Product::max('id') + 1000;

        $hiddenResponse = $this->post(route('store.notify-back-in-stock'), ['product_id' => $hidden->id, 'email' => 'nobody@example.test']);
        $missingResponse = $this->post(route('store.notify-back-in-stock'), ['product_id' => $missing, 'email' => 'nobody@example.test']);

        // Identical status, so walking the ids cannot tell "withdrawn" from
        // "never existed" - which is the whole catalogue, leaked one probe at
        // a time.
        $this->assertSame(
            $missingResponse->getStatusCode(),
            $hiddenResponse->getStatusCode(),
            'A different status for a hidden id is a product-existence oracle.'
        );

        $this->assertNotSame(422, $hiddenResponse->getStatusCode());
    }

    public function test_a_review_cannot_be_written_for_a_withdrawn_product(): void
    {
        $hidden = $this->product(['name' => 'Secret Kettle', 'catalog_visibility' => 'hidden']);

        $this->actingAs($this->shopper)
            ->post(route('store.reviews.store', $hidden), [
                'rating' => 5,
                'title' => 'Great',
                'comment' => 'Would recommend to anybody.',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('product_reviews', 0);
    }

    public function test_a_rating_only_review_is_stored_instead_of_erroring(): void
    {
        $product = $this->product(['name' => 'Honest Kettle']);

        // `title` and `content` are both `nullable`, and `validated()` omits a
        // nullable key that was never sent - so the controller reached
        // `ProductReview::create()` with neither one present and died on an
        // undefined array key. A shopper who typed only a star rating got a 500
        // off the review form. Both columns are nullable; absent is a
        // legitimate review, so the row should just be written.
        $this->actingAs($this->shopper)
            ->from(route('store.show', $product))
            ->post(route('store.reviews.store', $product), ['rating' => 4])
            ->assertRedirect(route('store.show', $product))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_reviews', [
            'product_id' => $product->id,
            'user_id' => $this->shopper->id,
            'rating' => 4,
            'title' => null,
            'content' => null,
        ]);
    }

    public function test_a_withdrawn_product_cannot_be_saved_to_a_wishlist(): void
    {
        $hidden = $this->product(['name' => 'Secret Kettle', 'catalog_visibility' => 'hidden']);

        $this->actingAs($this->shopper)
            ->post(route('store.wishlist.toggle', $hidden))
            ->assertNotFound();

        $this->assertDatabaseCount('wishlists', 0);
    }

    // ---------------------------------------------------------------------
    // Variation ownership.
    // ---------------------------------------------------------------------

    public function test_a_subscription_to_one_product_cannot_carry_another_products_variation(): void
    {
        $wanted = $this->product(['name' => 'Wanted Kettle']);
        $other = $this->product(['name' => 'Other Kettle']);
        $foreign = $this->variation($other);

        $this->post(route('store.notify-back-in-stock'), [
            'email' => 'shopper@example.test',
            'product_id' => $wanted->id,
            'variant_id' => $foreign->id,
        ])->assertNotFound();

        // The subscription table carries two independent foreign keys and no
        // constraint that they agree, so a row pairing product A with product
        // B's variation is storable - and then B's restock mails people who
        // asked about A.
        $this->assertDatabaseCount('back_in_stock_subscriptions', 0);
    }

    public function test_a_variation_that_does_not_exist_is_indistinguishable_from_a_foreign_one(): void
    {
        $wanted = $this->product(['name' => 'Wanted Kettle']);
        $other = $this->product(['name' => 'Other Kettle']);
        $foreign = $this->variation($other);
        $nowhere = $foreign->id + 1000;

        $payload = fn (int $variantId) => [
            'email' => 'shopper@example.test',
            'product_id' => $wanted->id,
            'variant_id' => $variantId,
        ];

        $foreignResponse = $this->post(route('store.notify-back-in-stock'), $payload($foreign->id));
        $missingResponse = $this->post(route('store.notify-back-in-stock'), $payload($nowhere));

        // `exists:product_variations,id` used to answer this pair differently:
        // 422 for the one that exists, validation failure for the one that does
        // not. On an unauthenticated form that walks the ids straight out of
        // every product's variant structure.
        $this->assertSame(
            $missingResponse->getStatusCode(),
            $foreignResponse->getStatusCode(),
            'A foreign variation and a nonexistent one must be the same answer.'
        );
        $this->assertSame(404, $foreignResponse->getStatusCode());
    }

    public function test_a_variation_of_a_hidden_product_cannot_be_probed(): void
    {
        $visible = $this->product(['name' => 'Listed Kettle']);
        $hidden = $this->product(['name' => 'Secret Kettle', 'catalog_visibility' => 'hidden']);
        $secret = $this->variation($hidden);

        // Naming the listed product and the hidden product's variation is the
        // probe: if the pairing is checked, the answer cannot depend on the
        // hidden product existing at all.
        $this->post(route('store.notify-back-in-stock'), [
            'email' => 'shopper@example.test',
            'product_id' => $visible->id,
            'variant_id' => $secret->id,
        ])->assertNotFound();

        $this->assertDatabaseCount('back_in_stock_subscriptions', 0);
    }

    public function test_subscribing_to_your_own_products_variation_still_works(): void
    {
        $product = $this->product(['name' => 'Wanted Kettle']);
        $own = $this->variation($product);

        $this->post(route('store.notify-back-in-stock'), [
            'email' => 'shopper@example.test',
            'product_id' => $product->id,
            'variant_id' => $own->id,
        ])->assertCreated();

        $this->assertDatabaseHas('back_in_stock_subscriptions', [
            'product_id' => $product->id,
            'product_variation_id' => $own->id,
            'email' => 'shopper@example.test',
        ]);
    }

    public function test_a_deactivated_variation_cannot_be_subscribed_to(): void
    {
        $product = $this->product(['name' => 'Wanted Kettle']);
        $deactivated = $this->variation($product);
        $deactivated->update(['is_active' => false]);

        $post = fn (int $variantId) => $this->post(route('store.notify-back-in-stock'), [
            'email' => 'shopper@example.test',
            'product_id' => $product->id,
            'variant_id' => $variantId,
        ]);

        $deactivatedResponse = $post($deactivated->id);
        $missingResponse = $post($deactivated->id + 1000);

        // The cart already refuses a deactivated variation in `ValidateVariant`.
        // The form did not, so it would accept a subscription the checkout could
        // never honour - and the gap also told a caller which ids are live
        // variants behind the product.
        $this->assertSame(404, $deactivatedResponse->getStatusCode());
        $this->assertSame(
            $missingResponse->getStatusCode(),
            $deactivatedResponse->getStatusCode(),
            'A deactivated variation and a nonexistent one must be the same answer.'
        );
        $this->assertDatabaseCount('back_in_stock_subscriptions', 0);
    }

    public function test_the_cart_answers_a_withdrawn_product_the_same_as_one_that_does_not_exist(): void
    {
        $withdrawn = $this->product(['name' => 'Withdrawn Kettle', 'catalog_visibility' => 'hidden']);
        $nowhere = $withdrawn->id + 1000;

        $post = fn (int $productId) => $this->actingAs($this->shopper)
            ->post(route('store.cart.add-item'), [
                'product_id' => $productId,
                'quantity' => 1,
            ]);

        $withdrawnResponse = $post($withdrawn->id);
        $missingResponse = $post($nowhere);

        // `exists:products,id` used to make this a catalogue oracle: the rule
        // refused a product that never existed, while a withdrawn one cleared it
        // and was refused later by the pipeline with different wording. Two
        // different 422s is enough to walk the ids and learn what is hidden.
        $this->assertSame(404, $withdrawnResponse->getStatusCode());
        $this->assertSame(
            $missingResponse->getStatusCode(),
            $withdrawnResponse->getStatusCode(),
            'A withdrawn product and a nonexistent one must be the same answer.'
        );
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_a_search_only_product_can_still_be_added_to_the_cart(): void
    {
        $product = $this->product(['name' => 'Findable Kettle', 'catalog_visibility' => 'search']);

        $this->actingAs($this->shopper)
            ->post(route('store.cart.add-item'), [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertOk();

        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id]);
    }

    public function test_the_cart_mutations_do_not_publish_the_merchants_figures(): void
    {
        $product = $this->product(['cost_price' => 3.1, 'price_hash' => 'deadbeefcafe']);
        $this->cartWith($product);
        $item = CartItem::where('product_id', $product->id)->firstOrFail();

        // The read surfaces were trimmed but the five mutations kept answering
        // with `$cart->fresh()->load('items.product', ...)`, so the leak survived
        // the first fix on exactly the responses a shopper triggers by clicking.
        $bodies = [
            'addItem' => $this->actingAs($this->shopper)->post(route('store.cart.add-item'), [
                'product_id' => $this->product(['name' => 'Second Kettle'])->id,
                'quantity' => 1,
            ])->getContent(),
            'updateItem' => $this->actingAs($this->shopper)
                ->put(route('store.cart.update-item', $item->id), ['quantity' => 3])->getContent(),
            'removeItem' => $this->actingAs($this->shopper)
                ->delete(route('store.cart.remove-item', $item->id))->getContent(),
        ];

        foreach ($bodies as $route => $body) {
            $this->assertIsString($body, "{$route} did not return a body to inspect.");
            $this->assertStringNotContainsString('cost_price', $body, "{$route} published cost_price.");
            $this->assertStringNotContainsString('price_hash', $body, "{$route} published price_hash.");
            $this->assertStringNotContainsString('deadbeefcafe', $body, "{$route} published the hash value.");
        }
    }

    public function test_a_cart_mutation_still_answers_with_the_updated_cart(): void
    {
        $product = $this->product(['name' => 'Kettle']);
        $this->cartWith($product);
        $item = CartItem::where('product_id', $product->id)->firstOrFail();

        $body = $this->actingAs($this->shopper)
            ->put(route('store.cart.update-item', $item->id), ['quantity' => 3])
            ->assertOk()
            ->getContent();

        $payload = json_decode($body, true);

        $this->assertIsArray($payload, 'The mutation response is not JSON; the assertions below would pass vacuously.');
        $this->assertSame(3, $payload['items'][0]['quantity']);
        $this->assertSame('Kettle', $payload['items'][0]['product']['name']);
    }

    public function test_the_cart_refuses_a_variation_belonging_to_another_product(): void
    {
        $wanted = $this->product(['name' => 'Wanted Kettle']);
        $other = $this->product(['name' => 'Other Kettle']);
        $foreign = $this->variation($other);

        $this->actingAs($this->shopper)
            ->post(route('store.cart.add-item'), [
                'product_id' => $wanted->id,
                'quantity' => 1,
                'variant_id' => $foreign->id,
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_the_cart_answers_a_foreign_variation_the_same_as_one_that_does_not_exist(): void
    {
        $product = $this->product(['name' => 'Wanted Kettle']);
        $foreign = $this->variation($this->product(['name' => 'Other Kettle']));
        $nowhere = $foreign->id + 1000;

        $post = fn (int $variantId) => $this->actingAs($this->shopper)
            ->post(route('store.cart.add-item'), [
                'product_id' => $product->id,
                'quantity' => 1,
                'variant_id' => $variantId,
            ]);

        $foreignResponse = $post($foreign->id);
        $missingResponse = $post($nowhere);

        // The add-to-cart form is guest-reachable, so the same rule applies:
        // `exists:product_variations,id` leaked whether the id was a variation
        // anywhere in the shop.
        $this->assertSame(
            $missingResponse->getStatusCode(),
            $foreignResponse->getStatusCode(),
            'A foreign variation and a nonexistent one must be the same answer.'
        );
        $this->assertSame(422, $foreignResponse->getStatusCode());
    }

    public function test_adding_your_own_products_variation_still_works(): void
    {
        $product = $this->product(['name' => 'Wanted Kettle']);
        $own = $this->variation($product);

        $this->actingAs($this->shopper)
            ->post(route('store.cart.add-item'), [
                'product_id' => $product->id,
                'quantity' => 2,
                'variant_id' => $own->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'product_variation_id' => $own->id,
            'quantity' => 2,
        ]);
    }

    // ---------------------------------------------------------------------
    // Shipping method submitted at checkout.
    // ---------------------------------------------------------------------

    public function test_checkout_refuses_a_shipping_method_that_has_been_switched_off(): void
    {
        $retired = ShippingMethod::create([
            'name' => 'Retired Carrier',
            'price' => 0,
            'is_active' => false,
            'sort_order' => 1,
        ]);

        $this->cartWith($this->product(['name' => 'Kettle']));

        $this->postCheckout($retired->id)->assertSessionHasErrors('shipping_method_id');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_refuses_a_shipping_method_the_address_was_never_offered(): void
    {
        $domestic = ShippingMethod::create([
            'name' => 'Domestic',
            'price' => 5,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $zone = ShippingZone::create([
            'name' => 'Tunisia',
            'countries' => ['TN'],
            'is_active' => true,
        ]);
        $zone->methods()->attach($domestic->id);

        // Active, and a genuine method - it just is not served to a Tunisian
        // address. Submitting its id used to work, and the order was then
        // priced from it.
        $elsewhere = ShippingMethod::create([
            'name' => 'Overseas Only',
            'price' => 0,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $this->cartWith($this->product(['name' => 'Kettle']));

        $this->postCheckout($elsewhere->id)->assertSessionHasErrors('shipping_method_id');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_still_accepts_the_shipping_method_it_offered(): void
    {
        $method = ShippingMethod::create([
            'name' => 'Standard',
            'price' => 5,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->cartWith($this->product(['name' => 'Kettle']));

        $this->postCheckout($method->id)->assertSessionHasNoErrors();

        // `orders` records what the method *cost*, not its id, so the money
        // column is the only way to see that the offered method is the one that
        // was priced.
        $this->assertDatabaseHas('orders', ['shipping_total' => 5]);
    }

    /**
     * The step resolves the method out of the offered collection, because that
     * is where the zone's `override_price` is applied. A plain lookup would
     * charge the list price instead, so the shopper would be billed a different
     * amount from the one the checkout page quoted.
     */
    public function test_checkout_prices_shipping_from_the_zones_override(): void
    {
        $method = ShippingMethod::create([
            'name' => 'Domestic',
            'price' => 9,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $zone = ShippingZone::create([
            'name' => 'Tunisia',
            'countries' => ['TN'],
            'is_active' => true,
        ]);
        $zone->methods()->attach($method->id, ['override_price' => 3]);

        $this->cartWith($this->product(['name' => 'Kettle']));

        $this->postCheckout($method->id)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('orders', ['shipping_total' => 3]);
    }

    private function postCheckout(int $shippingMethodId)
    {
        $address = [
            'first_name' => 'Nemo',
            'last_name' => 'Client',
            'address_line1' => '12 Rue X',
            'city' => 'Tunis',
            'country' => 'TN',
            'postcode' => '1000',
        ];

        return $this->actingAs($this->shopper)
            ->post(route('store.checkout.store'), [
                'shipping_method_id' => $shippingMethodId,
                'payment_method' => 'manual',
                'billing_address' => $address,
                'shipping_address' => $address,
            ]);
    }

    // ---------------------------------------------------------------------
    // Facets.
    // ---------------------------------------------------------------------

    public function test_a_category_whose_products_are_all_withdrawn_is_not_counted(): void
    {
        $category = ProductCategory::create([
            'name' => 'Discontinued',
            'slug' => 'discontinued-'.uniqid(),
            'is_active' => true,
        ]);

        $category->products()->attach($this->product(['catalog_visibility' => 'hidden'])->id);

        $this->get(route('store.products'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('categories', fn (Collection $categories) => $categories
                    ->pluck('id')
                    ->doesntContain($category->id))
            );
    }

    // ---------------------------------------------------------------------
    // Helpers.
    // ---------------------------------------------------------------------

    private function cartProps(): array
    {
        $props = null;

        $this->actingAs($this->shopper)
            ->get(route('store.cart.index'))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$props) {
                $props = $page->toArray()['props']['cart'];
            });

        $this->assertIsArray($props, 'The cart prop did not arrive; the privacy assertions below would pass vacuously.');

        return $props;
    }

    /**
     * @return list<string>
     */
    private function productKeys(array $cart): array
    {
        return array_keys($cart['items'][0]['product'] ?? []);
    }

    private function cartWith(Product $product): void
    {
        $cart = Cart::create(['user_id' => $this->shopper->id]);
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 20,
        ]);
    }

    private function order(User $owner): Order
    {
        return Order::create([
            'user_id' => $owner->id,
            'order_number' => 'ORD-'.uniqid(),
            'status' => 'processing',
            'subtotal' => 20,
            'total' => 20,
        ]);
    }

    /**
     * A guest order: no `user_id` at all, which is the case the confirmation
     * token exists for. `order()` takes a `User` because every other order here
     * belongs to one, and forcing a nullable parameter through it would invite
     * a null dereference into every existing call site.
     */
    private function guestOrder(): Order
    {
        return Order::create([
            'user_id' => null,
            'order_number' => 'ORD-'.uniqid(),
            'status' => 'processing',
            'subtotal' => 25,
            'total' => 25,
        ]);
    }

    private function variation(Product $product): ProductVariation
    {
        return ProductVariation::create([
            'product_id' => $product->id,
            'sku' => 'VAR-'.uniqid(),
            'price' => 30,
            'stock_quantity' => 5,
            'is_active' => true,
            'manage_stock' => 'own',
        ]);
    }

    private function product(array $attributes = []): Product
    {
        return Product::create($attributes + [
            'name' => 'Kettle',
            'slug' => 'kettle-'.uniqid(),
            'status' => 'published',
            'type' => 'simple',
            'catalog_visibility' => 'visible',
            'price' => 20,
            'cost_price' => 8,
            'stock_quantity' => 10,
            'track_stock' => false,
        ]);
    }
}
