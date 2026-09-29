<?php

namespace Nreljed\ShopModule\Tests;

use Illuminate\Http\Request;
use Nreljed\Platform\Models\User;
use Nreljed\ShopModule\Http\Controllers\Api\OrdersController;
use Nreljed\ShopModule\Http\Controllers\CartController;
use Nreljed\ShopModule\Http\Controllers\CheckoutController;
use Nreljed\ShopModule\Http\Controllers\MyOrderController;
use Nreljed\ShopModule\Http\Resources\CartItemResource;
use Nreljed\ShopModule\Http\Resources\CartResource;
use Nreljed\ShopModule\Http\Resources\StorefrontOrderResource;
use Nreljed\ShopModule\Models\Cart;
use Nreljed\ShopModule\Models\CartItem;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Policies\OrderPolicy;
use Nreljed\ShopModule\ShopServiceProvider;
use Nreljed\ShopModule\Tests\Concerns\BootsShopSchema;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Two properties of the customer-facing order and cart surfaces.
 *
 * **The order surfaces leaked other people's orders.** `Api\OrdersController`
 * route-model-bound `{order}` straight off the URL and returned it, on a route
 * only requiring `auth:sanctum`. Any signed-in customer could read any other
 * customer's order by incrementing the id: name, email, phone, both addresses,
 * the item lines. `index()` was already scoped to the caller, so the two
 * endpoints of the same resource disagreed about who a customer may see.
 *
 * **The cart published the merchant's own figures.** `GET /store/cart` and
 * `GET /store/cart/json` both returned the cart with `items.product` loaded
 * raw, and both are public. `Product` declares no `$hidden` - deliberately, so
 * the admin form can still bind `cost_price` - so one line item published
 * `cost_price`, `price_hash`, `status`, `catalog_visibility` and the rest of
 * the row to an anonymous visitor. The storefront trim existed for exactly
 * this and the cart was never routed through it.
 */
final class CustomerOrderAndCartExposureTest extends TestCase
{
    use BootsShopSchema;

    private Request $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootShopSchema();
        $this->resetShopTables();
        $this->request = Request::create('/store/cart');
    }

    // ---------------------------------------------------------------------
    // Order ownership.
    // ---------------------------------------------------------------------

    public function test_a_customer_can_read_their_own_order(): void
    {
        $user = $this->user();
        $order = $this->order($user->id);

        $this->assertTrue(
            (new OrderPolicy)->viewOwn($user, $order),
            'The account page and the API both have to keep working for the order\'s own buyer.'
        );
    }

    public function test_a_customer_cannot_read_someone_elses_order(): void
    {
        $owner = $this->user();
        $stranger = $this->user();

        $this->assertFalse(
            (new OrderPolicy)->viewOwn($stranger, $this->order($owner->id)),
            'The API bound any order off the URL, so this was an IDOR across the whole order history.'
        );
    }

    /**
     * A guest order has no owner to compare against.
     *
     * It is reachable through the confirmation token the shopper's own browser
     * and inbox carry (`CheckoutController::authorizeOrderView`), so it must not
     * be readable by "is there a matching user_id" - there is no match, but
     * neither is there a stranger to be refused.
     */
    public function test_a_guest_order_is_not_readable_as_someone_s_own(): void
    {
        $this->assertFalse(
            (new OrderPolicy)->viewOwn($this->user(), $this->order(null)),
            'A null user_id must not match, and must certainly not match a null on the user.'
        );
    }

    /**
     * `user_id` is `nullOnDelete`, so deleting an account leaves the order
     * behind with a null owner - which is exactly how a private order quietly
     * became a world-readable one under the old inline `if ($order->user_id
     * && ...)` guard.
     */
    public function test_an_order_orphaned_by_a_deleted_account_is_not_readable(): void
    {
        $this->assertFalse(
            (new OrderPolicy)->viewOwn($this->user(), $this->order(null)),
            'A deleted account must not leave its orders readable by the next person to guess the id.'
        );
    }

    /**
     * `view` stays the merchant's ability and must not quietly widen.
     *
     * `viewOwn` deliberately answers "is this my order?" and not "is this my
     * order or am I staff". The dashboard routes behind it sit under
     * `permission:shop.manage`, so widening `view` to include owners would be
     * inert - but it would also mean the staff ability and the customer ability
     * were the same question, and the next reader could not tell which rule a
     * given `authorize()` call was relying on.
     */
    public function test_the_staff_ability_is_kept_separate_from_the_own_order_ability(): void
    {
        $policy = new OrderPolicy;

        $user = new User;
        $user->forceFill(['id' => 1]);

        // `view` asks a permission question; with no permission loaded the
        // answer is false regardless of ownership.
        $this->assertFalse(
            $policy->view($user, $this->order(1)),
            'Owning an order must not grant the merchant ability.'
        );
    }

    public function test_both_order_surfaces_go_through_the_one_ownership_rule(): void
    {
        foreach ([OrdersController::class, MyOrderController::class] as $controller) {
            $this->assertStringContainsString(
                "authorize('viewOwn'",
                $this->source($controller),
                "{$controller} must resolve order ownership through the policy. Two hand-written comparisons is how the API ended up with none."
            );
        }

        $this->assertDoesNotMatchRegularExpression(
            '/user_id\s*!==?\s*\$?request\(\)->user\(\)->id/',
            $this->source(MyOrderController::class),
            'The inline comparison is what the policy replaced; leaving it behind means two rules again.'
        );
    }

    // ---------------------------------------------------------------------
    // Cart exposure.
    // ---------------------------------------------------------------------

    public function test_a_cart_line_does_not_publish_the_merchants_figures(): void
    {
        $product = $this->product([
            'cost_price' => 3.10,
            'price_hash' => 'deadbeefcafe',
            'hold_stock_minutes' => 45,
            'menu_order' => 9,
            'tax_class' => 'standard',
            'created_by' => 42,
            'status' => 'published',
            'catalog_visibility' => 'visible',
        ]);

        $payload = $this->cartItemPayload($product);

        foreach ([
            'cost_price' => 'what the shop pays - the whole basis of its pricing',
            'price_hash' => 'the hash its pricing logic compares against',
            'hold_stock_minutes' => 'how long a cart hold survives',
            'menu_order' => 'a merchandising control',
            'tax_class' => 'internal tax configuration',
            'created_by' => 'which staff account authored the product',
            'status' => 'workflow state',
            'catalog_visibility' => 'an editorial decision about the shop',
        ] as $field => $why) {
            $this->assertArrayNotHasKey($field, $payload['product'], "`{$field}` is not cart data - {$why}.");
        }

        $this->assertStringNotContainsString('3.1', json_encode($payload, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('deadbeefcafe', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function test_the_cart_line_still_carries_what_the_cart_page_renders(): void
    {
        $payload = $this->cartItemPayload($this->product([
            'name' => 'Charger',
            'sku' => 'SKU-1',
            'thumbnail_url' => 'https://cdn.test/a.png',
            'stock_quantity' => 3,
        ]));

        foreach ([
            'id', 'name', 'slug', 'price', 'compare_price', 'sku',
            'thumbnail_url', 'stock_quantity', 'track_stock', 'is_in_stock',
        ] as $field) {
            $this->assertArrayHasKey($field, $payload['product'], "`ShoppingCart.tsx` reads `product.{$field}`; dropping it white-screens the cart page.");
        }

        foreach (['id', 'product_id', 'quantity', 'unit_price', 'variant_label', 'product', 'available'] as $field) {
            $this->assertArrayHasKey($field, $payload);
        }
    }

    /**
     * A product withdrawn while it sat in the basket has to stay visible.
     *
     * Making the line disappear reads as the shop losing the shopper's basket,
     * and it hides the reason the checkout is refusing. The cart and checkout
     * pipelines already refuse to sell it; the flag is how the UI says so.
     */
    public function test_a_withdrawn_line_is_kept_and_flagged_rather_than_hidden(): void
    {
        $payload = $this->cartItemPayload($this->product(['catalog_visibility' => Product::HIDDEN]));

        $this->assertFalse($payload['available'], 'A hidden product cannot be bought, and the cart has to say so.');
        $this->assertNotNull($payload['product'], 'The line is still rendered. A shopper who added it should not watch it disappear.');
    }

    public function test_a_sellable_line_is_flagged_available(): void
    {
        $this->assertTrue(
            $this->cartItemPayload($this->product(['catalog_visibility' => Product::SEARCH]))['available'],
            'A search-only product is reachable and buyable, so it must not be flagged as unavailable.'
        );
    }

    /**
     * A soft-deleted product resolves the `belongsTo` to null.
     *
     * The cart page dereferenced `item.product.*` unconditionally, so one
     * deleted catalogue entry was enough to blank the page. The payload has to
     * survive it, and the frontend has to render around it.
     */
    public function test_a_line_whose_product_row_is_gone_does_not_blow_up(): void
    {
        $payload = $this->cartItemPayload(null);

        $this->assertNull($payload['product']);
        $this->assertFalse($payload['available']);
        $this->assertSame(1, $payload['quantity'], 'The line itself is still addressable, so it can still be removed.');
    }

    public function test_both_cart_routes_go_through_the_allowlist(): void
    {
        $source = $this->source(CartController::class);

        $this->assertStringContainsString(
            'CartResource::make($cart)',
            $source,
            'The cart payload must be built from the resource. Handing `$cart` to Inertia or `response()->json()` republishes the product row.'
        );

        $this->assertMatchesRegularExpression(
            '/publicCart\(\$request, \$cart\)/',
            $source,
            'Both the page and the JSON feed must build the cart the same way, or the header badge and the cart page disagree about what a cart is.'
        );

        $this->assertDoesNotMatchRegularExpression(
            "/'cart' => \\\$cart,/",
            $source,
            'A raw cart on a public route is the exposure this replaced.'
        );
    }

    /**
     * The cart page and the cart JSON feed were trimmed while the checkout page
     * kept rendering the same cart raw, with `items.product` loaded. Both ends
     * of the journey now build the payload from one resource, so the page that
     * spends the money cannot be the one that leaks.
     */
    public function test_the_checkout_page_uses_the_same_cart_allowlist(): void
    {
        $this->assertStringContainsString(
            'CartResource::make($cart)',
            $this->source(CheckoutController::class),
            'The checkout page renders a cart too, and it was the one still publishing `cost_price`.'
        );

        $this->assertDoesNotMatchRegularExpression(
            "/'cart' => \\\$cart,/",
            $this->source(CheckoutController::class),
            'A raw cart on the checkout page is the exposure this replaced.'
        );
    }

    /**
     * The product allowlist now sits one level below the cart, so it needs
     * pinning there instead: `CartController` delegates and no longer names
     * `CartItemResource` itself.
     */
    /**
     * The product allowlist now sits one level below the cart, so it needs
     * pinning there instead: `CartController` delegates and no longer names
     * `CartItemResource` itself.
     */
    public function test_the_cart_resource_still_delegates_every_product_to_the_allowlist(): void
    {
        $this->assertStringContainsString(
            'CartItemResource::collection($cart->items)',
            $this->source(CartResource::class),
            'The cart resource must build its lines from the item allowlist. Dropping this republishes the product row on every cart and checkout page.'
        );
    }

    /**
     * The cart page and the cart JSON feed were trimmed while the five
     * mutations kept answering with the raw cart. Every cart response has to go
     * through the allowlist, and a count pins the routes that must be routed -
     * a new mutation that returns a cart without being added here fails.
     */
    public function test_no_cart_response_hands_back_the_raw_cart(): void
    {
        $source = $this->source(CartController::class);

        $this->assertDoesNotMatchRegularExpression(
            "/response\(\)->json\(\s*\\\$\w+(->fresh\(\))?(->load\([^)]*\))?\s*\)/",
            $source,
            'A raw cart on any response republishes `items.product` and with it `cost_price`.'
        );

        $this->assertSame(
            7,
            substr_count($source, '$this->publicCart('),
            'index, page, addItem, updateItem, removeItem, applyCoupon and removeCoupon all return a cart.'
        );
    }

    /**
     * `exists:products,id` made the guest-reachable add-to-cart endpoint a
     * catalogue oracle: 422 for a product that never existed, the pipeline's own
     * wording for one an editor had withdrawn.
     */
    public function test_the_cart_does_not_ask_whether_a_product_exists(): void
    {
        $source = $this->source(CartController::class);

        // Matched as the rule itself, not the bare rule name: the comment above
        // the rule has to be able to name what it replaced.
        $this->assertStringNotContainsString(
            "'product_id' => 'required|exists:products",
            $source,
            'The existence rule answers differently for a withdrawn product than for a nonexistent one.'
        );
    }

    public function test_the_confirmation_page_uses_the_order_allowlist(): void
    {
        $this->assertStringContainsString(
            'StorefrontOrderResource::make($order)',
            $this->source(CheckoutController::class),
            'The guest confirmation page rendered the raw order, which published the confirmation token and other internal columns.'
        );

        $this->assertStringNotContainsString(
            "'order' => \$order,",
            $this->source(CheckoutController::class),
            'A raw order on the confirmation render is the exposure this replaced.'
        );
    }

    /**
     * The order allowlist trims internal fields (confirmation_token,
     * payment_intent_id, notes/refund_notes, processed_by) off the public
     * confirmation payload.
     */
    public function test_the_order_allowlist_strips_internal_fields(): void
    {
        $this->assertStringContainsString(
            'confirmation_token',
            $this->source(StorefrontOrderResource::class),
            'The comment is there to explain why it is absent - but the field itself must not be present.'
        );

        $src = $this->source(StorefrontOrderResource::class);
        $this->assertStringNotContainsString("'confirmation_token' =>", $src);
        $this->assertStringNotContainsString("'payment_intent_id' =>", $src);
        $this->assertStringNotContainsString("'notes' =>", $src);
        $this->assertStringNotContainsString("'refund_notes' =>", $src);
        $this->assertStringNotContainsString("'processed_by' =>", $src);
    }

    /**
     * The cart delegates its product to the storefront allowlist rather than
     * re-listing the fields, so the two ends of the shopper's journey cannot
     * drift apart when a field is added or removed in one place.
     */
    public function test_the_cart_delegates_to_the_storefront_allowlist(): void
    {
        $this->assertStringContainsString(
            'StorefrontProductResource::make($item->product)',
            $this->source(CartItemResource::class),
            'The cart must reuse the storefront resource. A second hand-written field list is a second thing to keep correct.'
        );
    }

    /**
     * The shared `cart` prop reaches every Inertia response, storefront pages
     * included, so the header badge is the widest-reaching product payload in
     * the app. It was already an allowlist; what it lacked was the ability to
     * say a line can no longer be bought.
     */
    public function test_the_shared_cart_badge_carries_the_availability_flag(): void
    {
        $source = $this->source(ShopServiceProvider::class);

        $this->assertMatchesRegularExpression(
            "/'available' => \\\$item->product\?->isPurchasable\(\) \?\? false/",
            $source,
            'The shared cart prop must flag an unsellable line, matching CartItemResource.'
        );
    }

    // ---------------------------------------------------------------------
    // Helpers.
    // ---------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function cartItemPayload(?Product $product): array
    {
        $item = new CartItem([
            'product_id' => $product?->id ?? 4242,
            'quantity' => 1,
            'unit_price' => 10,
        ]);

        // A soft delete leaves the relation resolving to null, which is the
        // case `test_a_line_whose_product_row_is_gone_does_not_blow_up` needs.
        $item->setRelation('product', $product);
        $item->setRelation('variation', null);

        return (new CartItemResource($item))->toArray($this->request);
    }

    private function product(array $attributes = []): Product
    {
        return Product::create($attributes + [
            'name' => 'Thing',
            'slug' => 'thing-'.uniqid(),
            'status' => 'published',
            'type' => 'simple',
            'catalog_visibility' => 'visible',
            'price' => 10,
            'cost_price' => 4,
            'stock_quantity' => 5,
            'track_stock' => true,
        ]);
    }

    private function order(?int $userId): Order
    {
        $order = new Order(['order_number' => 'ORD-'.uniqid(), 'status' => 'completed', 'total' => 10]);
        $order->forceFill(['user_id' => $userId]);

        return $order;
    }

    private function user(): User
    {
        // Deliberately not persisted: these tests are about the ownership
        // comparison, and inserting into the shared `users` table would make
        // the helper's id depend on test order. A counter gives every call a
        // distinct identity with no fixture behind it.
        static $next = 1;

        $user = new User;
        $user->forceFill([
            'id' => $next++,
            'name' => 'Shopper',
            'email' => 'shopper-'.uniqid().'@example.test',
        ]);

        return $user;
    }

    private function source(string $class): string
    {
        return (string) file_get_contents((new ReflectionClass($class))->getFileName());
    }
}
