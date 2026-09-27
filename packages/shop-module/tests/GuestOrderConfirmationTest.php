<?php

namespace Nreljed\ShopModule\Tests;

use Illuminate\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\UrlGenerator;
use Mockery;
use Nreljed\Platform\Models\User;
use Nreljed\ShopModule\Http\Controllers\CheckoutController;
use Nreljed\ShopModule\Models\Cart;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Pipelines\Checkout\CreateOrder;
use Nreljed\ShopModule\Pipelines\CheckoutContext;
use Nreljed\ShopModule\Tests\Concerns\BootsShopSchema;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Guest order confirmations were world-readable.
 *
 * `CheckoutController::confirmation()` guarded with
 * `if ($order->user_id && ...)`, which is a no-op for an order with no
 * account attached - precisely the orders a guest checkout produces. Order
 * ids are sequential, so walking the public confirmation route upwards
 * returned every guest order in the shop: name, email, phone, billing and
 * shipping addresses, and the exact basket they bought.
 *
 * The same hole reached member orders. `orders.user_id` is `nullOnDelete`, so
 * a shopper who deleted their account had their orders quietly converted
 * from private rows into "guest" rows and therefore into public ones.
 *
 * Access now requires one of: being the account that placed the order, holding
 * the order's confirmation token, or a valid URL signature. Shop staff are
 * exempt, because the same action backs the `/dashboard/shop` confirmation
 * route that already sits behind `permission:shop.manage`.
 */
final class GuestOrderConfirmationTest extends TestCase
{
    use BootsShopSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootShopSchema();
        $this->resetShopTables();

        $this->bindGate();
        $this->bindAppKey();
        $this->bindUrlGenerator();
    }

    public function tearDown(): void
    {
        if (Mockery::getContainer() !== null) {
            Mockery::close();
        }

        parent::tearDown();
    }

    // ---------------------------------------------------------------------
    // The leak.
    // ---------------------------------------------------------------------

    public function test_guest_order_confirmation_is_refused_without_proof(): void
    {
        $order = $this->guestOrder();

        $this->assertDenied(
            fn () => $this->confirm($order),
            'A guest order was readable by id alone - its name, email, phone, addresses and basket are public to anyone who increments a number.'
        );
    }

    public function test_guest_order_ids_cannot_be_walked(): void
    {
        $victims = [$this->guestOrder(), $this->guestOrder(), $this->guestOrder()];

        foreach ($victims as $index => $victim) {
            $this->assertDenied(
                fn () => $this->confirm($victim),
                "Guest order #".($index + 1)." was readable by enumeration."
            );
        }
    }

    public function test_member_order_confirmation_is_refused_for_another_member(): void
    {
        $order = $this->memberOrder($this->user(1));

        $this->assertDenied(
            fn () => $this->confirm($order, $this->requestFor($this->user(2))),
            'One member could read another member\'s order by id.'
        );
    }

    public function test_member_order_is_refused_for_a_signed_out_visitor(): void
    {
        $order = $this->memberOrder($this->user(1));

        $this->assertDenied(
            fn () => $this->confirm($order, $this->requestFor(null)),
            'A signed-out visitor could read a member order by id.'
        );
    }

    // ---------------------------------------------------------------------
    // The three things that do grant access.
    // ---------------------------------------------------------------------

    public function test_guest_order_is_readable_with_its_confirmation_token(): void
    {
        $order = $this->guestOrder();

        $this->assertAllows(
            fn () => $this->authorize($order, $this->requestFor(null, $order->confirmation_token))
        );
    }

    public function test_member_order_is_readable_by_the_account_that_placed_it(): void
    {
        $order = $this->memberOrder($this->user(7));

        $this->assertAllows(fn () => $this->authorize($order, $this->requestFor($this->user(7))));
    }

    public function test_guest_order_is_readable_through_a_valid_signature(): void
    {
        $order = $this->guestOrder();

        $request = $this->signedRequest('store.checkout.confirmation', ['order' => $order->id]);

        $this->assertAllows(fn () => $this->authorize($order, $request));
    }

    public function test_shop_staff_may_read_any_order(): void
    {
        $staff = $this->user(99, managesShop: true);

        foreach ([$this->guestOrder(), $this->memberOrder($this->user(1))] as $order) {
            $this->assertAllows(
                fn () => $this->authorize($order, $this->requestFor($staff)),
                'Shop staff must be able to open a customer order - the /dashboard/shop confirmation route would otherwise 403 on every member order.'
            );
        }
    }

    // ---------------------------------------------------------------------
    // The token has to actually be a secret.
    // ---------------------------------------------------------------------

    public function test_another_orders_token_does_not_unlock_this_one(): void
    {
        $victim = $this->guestOrder();
        $attacker = $this->guestOrder();

        $this->assertForbidden(
            fn () => $this->authorize($victim, $this->requestFor(null, $attacker->confirmation_token)),
            'A valid token from a different order unlocked this one - tokens must be per-order, not per-visitor.'
        );
    }

    public function test_a_forged_token_is_refused(): void
    {
        $order = $this->guestOrder();

        foreach (['', 'guess', str_repeat('a', 64), substr($order->confirmation_token, 0, 63), $order->confirmation_token.'x'] as $forged) {
            $this->assertForbidden(
                fn () => $this->authorize($order, $this->requestFor(null, $forged)),
                'A forged token opened the order: '.var_export($forged, true)
            );
        }
    }

    public function test_a_null_token_is_refused(): void
    {
        $order = $this->guestOrder();

        $this->assertFalse($order->hasValidConfirmationToken(null), 'A missing token must not validate.');
    }

    public function test_an_order_without_a_token_refuses_every_token(): void
    {
        $order = $this->guestOrder();
        $order->update(['confirmation_token' => null]);

        // Belt and braces: `hash_equals()` against an empty stored value is
        // already false, so this asserts the observable rule - a row with no
        // token issued never opens - rather than the exact guard that
        // enforces it.
        $this->assertFalse(
            $order->fresh()->hasValidConfirmationToken('anything'),
            'An order with no token issued must not accept one, or `confirmation_token = null` would become a wildcard.'
        );

        $this->assertDenied(
            fn () => $this->authorize($order->fresh(), $this->requestFor(null, 'anything')),
            'An order with no token opened for a caller presenting a token.'
        );
    }

    public function test_tokens_are_long_and_unique_per_order(): void
    {
        $a = $this->guestOrder();
        $b = $this->guestOrder();

        $this->assertNotSame($a->confirmation_token, $b->confirmation_token, 'Tokens must not repeat across orders.');
        $this->assertSame(64, strlen((string) $a->confirmation_token), 'A 64-character random token; anything shorter is enumerable.');
    }

    // ---------------------------------------------------------------------
    // Deleting an account must not publish the order.
    // ---------------------------------------------------------------------

    public function test_deleting_an_account_does_not_publish_the_order(): void
    {
        $user = $this->user(5);
        $order = $this->memberOrder($user);
        $token = $order->confirmation_token;

        // What `onUserDelete()` does, and what the `nullOnDelete` foreign key
        // does on its own.
        $order->update(['user_id' => null]);
        $order = $order->fresh();

        $this->assertNull($order->user_id, 'Precondition: the order is now a row with no account.');

        $this->assertDenied(
            fn () => $this->confirm($order),
            'Deleting an account turned a private order into a publicly readable one.'
        );

        $this->assertAllows(
            fn () => $this->authorize($order, $this->requestFor(null, $token)),
            'The owner must still reach the order they paid for after the account is gone.'
        );
    }

    // ---------------------------------------------------------------------
    // The link producers. A guest who is redirected somewhere they cannot
    // read has not been served, and the registration form that dropped its
    // own signature was permanently 401.
    // ---------------------------------------------------------------------

    public function test_guest_redirect_carries_the_token(): void
    {
        $order = $this->guestOrder();

        $parameters = $this->confirmationRedirectParameters($order);

        $this->assertSame($order->id, $parameters['order']);
        $this->assertSame(
            $order->confirmation_token,
            $parameters['token'],
            'A guest is redirected to a page that will refuse them without the token in the URL - the first thing after a successful guest checkout would be a 403 on their own order.'
        );
    }

    public function test_member_redirect_does_not_leak_the_token(): void
    {
        $order = $this->memberOrder($this->user(3));

        $parameters = $this->confirmationRedirectParameters($order);

        $this->assertArrayNotHasKey(
            'token',
            $parameters,
            'A member is identified by their session; the token has no business in their address bar, history, or a screenshot.'
        );
    }

    public function test_registration_form_url_preserves_the_signature_it_arrived_with(): void
    {
        $order = $this->guestOrder();

        $request = $this->signedRequest('store.checkout.register-from-order', [
            'order' => $order->id,
            'email' => $order->customer_email,
        ]);

        $url = $this->invoke('registerFormUrl', $request, $order);

        $this->assertStringContainsString('token='.$order->confirmation_token, $url, 'The form action must carry the order token.');
        $this->assertStringContainsString('signature=', $url, 'The form action dropped the signature the visitor arrived with, so submitting it could only ever 401.');

        // And the URL the page hands back must actually pass the guard it is
        // posted to.
        $this->assertAllows(fn () => $this->authorizeRegistration($order, Request::create($url)));
    }

    public function test_registration_still_works_after_the_signature_expires(): void
    {
        $order = $this->guestOrder();

        // A shopper who dismisses the account prompt for a fortnight should
        // not find a dead link.
        $request = Request::create('/store/order/'.$order->id.'/register?token='.$order->confirmation_token);

        $this->assertAllows(
            fn () => $this->authorizeRegistration($order, $request),
            'The token is issued at checkout and does not expire, so a lapsed temporary signature must not close the flow.'
        );
    }

    public function test_registration_without_any_proof_is_still_refused(): void
    {
        $order = $this->guestOrder();

        $this->assertUnauthorized(
            fn () => $this->authorizeRegistration($order, Request::create('/store/order/'.$order->id.'/register')),
            'An unsigned, tokenless URL must not be able to claim an order and attach it to a new account.'
        );
    }

    public function test_emailed_registration_link_carries_the_token(): void
    {
        $source = file_get_contents(dirname(__DIR__).'/src/Http/Controllers/CheckoutController.php');

        $this->assertMatchesRegularExpression(
            "/temporarySignedRoute\(\s*'store\.checkout\.register-from-order'.*?'token'\s*=>\s*\\\$order->confirmation_token/s",
            $source,
            'The emailed link must carry the order token; a temporary signature alone leaves the shopper with a dead link once it lapses.'
        );
    }

    // ---------------------------------------------------------------------
    // Every order gets a token.
    // ---------------------------------------------------------------------

    public function test_checkout_issues_a_confirmation_token(): void
    {
        $order = $this->runCreateOrder();

        $this->assertNotNull($order->confirmation_token, 'An order created without a token is permanently unreadable by its own guest owner.');
    }

    public function test_a_reused_provisional_draft_keeps_its_token(): void
    {
        $cart = $this->cart();

        $first = $this->runCreateOrder(user: null, cart: $cart);
        $token = $first->confirmation_token;

        $second = $this->runCreateOrder(user: null, cart: $cart, provisional: $first);

        $this->assertSame(
            $token,
            $second->confirmation_token,
            'Replacing a provisional draft must not invalidate a link the shopper already has open.'
        );
    }

    public function test_the_token_is_not_taken_from_the_checkout_request(): void
    {
        // A shopper must not be able to choose their own token: pick a known
        // value and that order, and only that order, opens. The pipe builds
        // its attribute list explicitly rather than spreading the request, so
        // a client-supplied token cannot survive.
        $order = $this->runCreateOrder(validated: [
            'confirmation_token' => 'attacker-chosen-token',
        ]);

        $this->assertNotSame(
            'attacker-chosen-token',
            $order->confirmation_token,
            'The checkout request supplied the token, so whoever knew that value could open the order.'
        );
    }

    // ---------------------------------------------------------------------
    // Helpers.
    // ---------------------------------------------------------------------

    /**
     * Call the real public action, so the test proves the guard is actually
     * wired in rather than merely present.
     */
    private function confirm(Order $order, ?Request $request = null): void
    {
        $this->controller()->confirmation($request ?? $this->requestFor(null), $order);
    }

    private function authorize(Order $order, Request $request): void
    {
        $this->invoke('authorizeOrderView', $request, $order);
    }

    private function authorizeRegistration(Order $order, Request $request): void
    {
        $this->invoke('authorizeRegistrationLink', $request, $order);
    }

    private function confirmationRedirectParameters(Order $order): array
    {
        return $this->invoke('confirmationRedirectParameters', $order);
    }

    private function invoke(string $method, mixed ...$arguments): mixed
    {
        return (new ReflectionClass(CheckoutController::class))
            ->getMethod($method)
            ->invoke($this->controller(), ...$arguments);
    }

    private function controller(): CheckoutController
    {
        return (new ReflectionClass(CheckoutController::class))->newInstanceWithoutConstructor();
    }

    private function requestFor(?User $user, ?string $token = null): Request
    {
        $query = $token !== null ? ['token' => $token] : [];

        $request = Request::create('/store/checkout/confirmation/1?'.http_build_query($query));

        $request->setUserResolver(fn () => $user);

        return $request;
    }

    /**
     * A genuinely signed request, produced by Laravel's own signer rather than
     * hand-assembled, so the test cannot accidentally assert against a URL
     * shape the framework would never accept.
     */
    private function signedRequest(string $routeName, array $parameters): Request
    {
        $url = $this->url()->route($routeName, $parameters, true);
        $signed = $this->url()->signedRoute($routeName, $parameters, false);

        $this->assertStringContainsString('signature=', $signed, 'The harness failed to build a signed URL.');

        return Request::create($signed);
    }

    private function user(int $id, bool $managesShop = false): User
    {
        $user = new User();
        $user->id = $id;

        $this->staffIds[$user->id] = $managesShop;

        return $user;
    }

    /** @var array<int, bool> */
    private array $staffIds = [];

    private function guestOrder(): Order
    {
        return Order::create([
            'user_id' => null,
            'status' => 'pending',
            'payment_method' => 'manual',
            'payment_status' => 'pending',
            'subtotal' => 50,
            'tax_total' => 0,
            'shipping_total' => 0,
            'discount_total' => 0,
            'total' => 50,
            'currency' => 'USD',
            'customer_email' => 'guest@example.test',
            'customer_name' => 'Guest Shopper',
            'customer_phone' => '+1 555 0100',
            'billing_address' => ['line1' => '1 Guest Way'],
            'shipping_address' => ['line1' => '1 Guest Way'],
            'confirmation_token' => Order::newConfirmationToken(),
        ]);
    }

    private function memberOrder(User $user): Order
    {
        $order = $this->guestOrder();
        $order->update(['user_id' => $user->id]);

        return $order->fresh();
    }

    private function cart(): Cart
    {
        return Cart::create(['session_id' => 's'.uniqid()]);
    }

    /**
     * Drive the real `CreateOrder` pipe, so "every order gets a token" is a
     * statement about the checkout path and not about a helper.
     */
    private function runCreateOrder(?User $user = null, ?Cart $cart = null, ?Order $provisional = null, array $validated = []): Order
    {
        $context = new CheckoutContext(
            $cart ?? $this->cart(),
            $validated + [
                'customer_email' => 'guest@example.test',
                'customer_name' => 'Guest Shopper',
                'customer_phone' => '+1 555 0100',
                'payment_method' => 'manual',
                'billing_address' => ['line1' => '1 Guest Way'],
                'shipping_address' => ['line1' => '1 Guest Way'],
            ],
            $user,
            $provisional,
        );

        $context->quote = [
            'subtotal' => 50.0,
            'discount_total' => 0.0,
            'shipping_total' => 0.0,
            'tax' => ['tax_total' => 0.0],
            'total' => 50.0,
            'currency' => 'USD',
            'exchange_rate' => 1.0,
            'coupons' => [],
        ];

        (new CreateOrder())->handle($context, function ($passed) {
            return $passed;
        });

        return $context->order->fresh();
    }

    /**
     * A real `Gate` so `$user->can('shop.manage')` resolves the same way it
     * does in the app.
     */
    private function bindGate(): void
    {
        $container = Container::getInstance();

        $gate = new Gate($container, fn () => null);
        $gate->define('shop.manage', fn (User $user) => $this->staffIds[(int) $user->id] ?? false);

        $container->instance(GateContract::class, $gate);
    }

    /**
     * `UrlGenerator::signedRoute()` signs with `app.key`, which the harness
     * config does not carry. Set once and reused, so a signature produced in
     * one assertion is still verifiable in the next.
     */
    private function bindAppKey(): void
    {
        Container::getInstance()->make('config')->set(
            'app.key',
            'base64:'.base64_encode(str_repeat('k', 32))
        );
    }

    /**
     * A `UrlGenerator` over just the routes the controller builds links to, so
     * `route()`/`signedRoute()` are exercised for real.
     */
    private function bindUrlGenerator(): void
    {
        $routes = new RouteCollection();

        foreach ([
            'store.checkout.confirmation' => '/store/checkout/confirmation/{order}',
            'store.checkout.register-from-order' => '/store/order/{order}/register',
            'store.checkout.store-register' => '/store/order/{order}/register',
        ] as $name => $uri) {
            $route = new Route(['GET', 'POST'], $uri, []);
            $route->name($name);
            $routes->add($route);
        }

        // `UrlGenerator` resolves the signing key through a callable the
        // RoutingServiceProvider injects; without it `signedRoute()` throws
        // "no array or string given" instead of producing a signature.
        $url = (new UrlGenerator($routes, Request::create('http://localhost')))
            ->setKeyResolver(fn () => Container::getInstance()->make('config')->get('app.key'));

        Container::getInstance()->instance('url', $url);
    }
    private function url(): UrlGenerator
    {
        return Container::getInstance()->make('url');
    }

    private function assertAllows(callable $callback, string $message = ''): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            $this->fail($message !== '' ? $message : 'Expected access to be allowed, got '.get_class($e).': '.$e->getMessage());
        }

        $this->assertTrue(true);
    }

    /**
     * Assert the real public action refuses this visitor.
     *
     * Anything other than a 403 counts as a leak, and the distinction matters
     * for reading a failure: if the guard is removed the action runs on and
     * blows up somewhere inside Inertia with a container error. That is still
     * a failure - the order was served - it just does not look like one unless
     * it is said out loud.
     */
    private function assertDenied(callable $callback, string $leak): void
    {
        try {
            $callback();
        } catch (AccessDeniedHttpException) {
            $this->assertTrue(true);

            return;
        } catch (\Throwable $e) {
            $this->fail("Leaked: the guard let the request past and the action reached ".get_class($e)." (".$e->getMessage()."). ".$leak);
        }

        $this->fail("Leaked: the action completed and the order was served. ".$leak);
    }

    private function assertForbidden(callable $callback, string $message = ''): void
    {
        $this->assertThrows(AccessDeniedHttpException::class, $callback, $message);
    }

    private function assertUnauthorized(callable $callback, string $message = ''): void
    {
        $this->assertThrows(UnauthorizedHttpException::class, $callback, $message);
    }

    private function assertThrows(string $expected, callable $callback, string $message = ''): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            $this->assertInstanceOf(
                $expected,
                $e,
                ($message !== '' ? $message : 'Expected '.$expected).' but got '.get_class($e).': '.$e->getMessage()
            );

            return;
        }

        $this->fail($message !== '' ? $message.' (nothing was thrown)' : 'Expected '.$expected.', nothing was thrown.');
    }
}
