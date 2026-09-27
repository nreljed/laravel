<?php

namespace Nreljed\ShopModule\Tests;

use Illuminate\Container\Container;
use Illuminate\Contracts\Routing\ResponseFactory as ResponseFactoryContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\NullSessionHandler;
use Illuminate\Session\Store;
use Mockery;
use Nreljed\ShopModule\Http\Controllers\CartController;
use Nreljed\ShopModule\Models\Cart;
use Nreljed\ShopModule\Models\CartItem;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Services\CurrencyService;
use Nreljed\ShopModule\Services\ShopSettingsService;
use Nreljed\ShopModule\Tests\Concerns\BootsShopSchema;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Cart line endpoints resolved `CartItem` by global route-model binding, so
 * `/cart/items/{id}` matched any line in the table. A shopper could then PUT or
 * DELETE another shopper's line, and the JSON response handed back that
 * stranger's whole cart - products, quantities, unit prices and applied
 * coupon codes.
 *
 * The fix scopes the lookup to the caller's own cart. A line that is not in it
 * is a 404, not a 403, because the item's existence is itself private.
 */
final class CartItemOwnershipTest extends TestCase
{
    use BootsShopSchema;

    /**
     * `Store::setId()` silently regenerates any id that is not exactly 40
     * alphanumeric characters, which would hand every request a fresh cart and
     * make the negative cases pass for the wrong reason.
     */
    private const SESSION_A = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const SESSION_B = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootShopSchema();
        $this->resetShopTables();

        $this->bindResponseFactory();
    }

    public function tearDown(): void
    {
        if (Mockery::getContainer() !== null) {
            Mockery::close();
        }

        parent::tearDown();
    }

    /**
     * Actions end in `response()->json(...)`, which resolves the response
     * factory contract from the container. Mockery derives the signature from
     * the interface, so this stays correct as the contract evolves - a
     * hand-written stub kept breaking on new methods.
     */
    private function bindResponseFactory(): void
    {
        $factory = Mockery::mock(ResponseFactoryContract::class);
        $factory->shouldReceive('json')->andReturnUsing(
            fn ($data = [], $status = 200, array $headers = [], $options = 0) => new JsonResponse($data, $status, $headers, $options)
        );

        Container::getInstance()->instance(ResponseFactoryContract::class, $factory);
    }

    public function test_item_in_own_cart_resolves(): void
    {
        $item = $this->seedCartLine(self::SESSION_A);

        $resolved = $this->findOwnCartItem(self::SESSION_A, $item->id);

        $this->assertSame($item->id, $resolved->id);
    }

    public function test_item_in_another_shoppers_cart_is_not_found(): void
    {
        $victimItem = $this->seedCartLine(self::SESSION_B);

        $this->assertGuestCannotReach(self::SESSION_A, $victimItem->id);
    }

    public function test_unknown_item_id_is_not_found(): void
    {
        $this->assertGuestCannotReach(self::SESSION_A, 999999);
    }

    /**
     * The real action, not just the helper.
     *
     * Asserting `findOwnCartItem()` in isolation proves the lookup is scoped
     * but not that the endpoint uses it, so this drives `removeItem()` itself
     * and checks the stranger's line survives.
     */
    public function test_remove_item_leaves_another_shoppers_line_untouched(): void
    {
        $this->seedCartLine(self::SESSION_A);
        $victimItem = $this->seedCartLine(self::SESSION_B);

        try {
            $this->removeItem(self::SESSION_A, $victimItem->id);
            $this->fail('Expected removeItem() to 404 for a line the caller does not own.');
        } catch (NotFoundHttpException) {
            $this->addToAssertionCount(1);
        }

        $this->assertRowExists('cart_items', ['id' => $victimItem->id], "A shopper deleted another shopper's cart line.");
    }

    public function test_remove_item_deletes_the_callers_own_line(): void
    {
        $ownItem = $this->seedCartLine(self::SESSION_A);
        $victimItem = $this->seedCartLine(self::SESSION_B);

        $this->removeItem(self::SESSION_A, $ownItem->id);

        $this->assertRowMissing('cart_items', ['id' => $ownItem->id], 'The caller should be able to remove their own line.');
        $this->assertRowExists('cart_items', ['id' => $victimItem->id]);
    }

    /**
     * `updateItem()` runs `$request->validate()`, which needs a validator this
     * minimal container does not build. Its ownership check is the shared
     * helper, so the structural assertions below cover that neither action can
     * drift back to an unscoped model.
     */
    public function test_actions_do_not_accept_a_globally_bound_cart_item(): void
    {
        $source = file_get_contents(dirname(__DIR__).'/src/Http/Controllers/CartController.php');

        $this->assertDoesNotMatchRegularExpression(
            '#public function (updateItem|removeItem)\([^)]*CartItem \$cartItem#',
            $source,
            'Cart line actions must take a raw id and resolve it within the caller\'s own cart, not accept a globally route-model-bound CartItem.'
        );

        $this->assertSame(
            2,
            preg_match_all('#\$this->findOwnCartItem\(#', $source),
            'Both updateItem() and removeItem() must resolve the line through findOwnCartItem().'
        );
    }

    private function removeItem(string $sessionId, int $cartItemId): void
    {
        $this->controller()->removeItem($this->guestRequest($sessionId), $cartItemId);
    }

    private function assertGuestCannotReach(string $sessionId, int $cartItemId): void
    {
        try {
            $this->findOwnCartItem($sessionId, $cartItemId);
        } catch (NotFoundHttpException) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail("Expected cart item [{$cartItemId}] to be rejected for a session that does not own it.");
    }

    private function findOwnCartItem(string $sessionId, int $cartItemId): CartItem
    {
        $method = (new ReflectionClass(CartController::class))
            ->getMethod('findOwnCartItem');

        return $method->invoke($this->controller(), $this->guestRequest($sessionId), $cartItemId);
    }

    /**
     * `findOwnCartItem()` only ever touches `currencyService` (through
     * `getCart()`), so only that collaborator is wired - the remaining three
     * are irrelevant to the ownership check and pulling in WebhookService and
     * TaxService would just couple the test to unrelated wiring.
     */
    private function controller(): CartController
    {
        $controller = (new ReflectionClass(CartController::class))->newInstanceWithoutConstructor();

        $currencyService = new CurrencyService(new ShopSettingsService);

        $property = (new ReflectionClass(CartController::class))->getProperty('currencyService');
        $property->setValue($controller, $currencyService);

        return $controller;
    }

    private function guestRequest(string $sessionId): Request
    {
        $request = Request::create('/cart/items/1');
        $request->setLaravelSession(new Store('test-session', new NullSessionHandler, $sessionId));

        return $request;
    }

    private function seedCartLine(string $sessionId): CartItem
    {
        $product = Product::create([
            'name' => 'Widget '.$sessionId,
            'price' => 10,
            'status' => 'published',
        ]);

        $cart = Cart::create(['session_id' => $sessionId, 'currency' => 'EUR']);

        $this->assertSame(
            $sessionId,
            (new Store('t', new NullSessionHandler, $sessionId))->getId(),
            'The fixture session id must survive Store::setId() unchanged.'
        );

        return CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 10,
            'currency' => 'EUR',
        ]);
    }
}
