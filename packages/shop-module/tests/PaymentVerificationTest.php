<?php

namespace Nreljed\ShopModule\Tests;

use Illuminate\Http\Request;
use Mockery;
use Nreljed\ShopModule\Contracts\PaymentDriver;
use Nreljed\ShopModule\Http\Controllers\CheckoutController;
use Nreljed\ShopModule\Models\Cart;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Models\User;
use Nreljed\ShopModule\Payments\ManualPaymentDriver;
use Nreljed\ShopModule\Payments\PaymentIntentMatcher;
use Nreljed\ShopModule\Payments\PaymentVerification;
use Nreljed\ShopModule\Payments\StripePaymentDriver;
use Nreljed\ShopModule\Pipelines\Checkout\CreateOrder;
use Nreljed\ShopModule\Pipelines\CheckoutContext;
use Nreljed\ShopModule\Http\Controllers\StripeWebhookController;
use Nreljed\ShopModule\Services\PaymentManager;
use Nreljed\ShopModule\Services\StripeService;
use Nreljed\ShopModule\Tests\Concerns\BootsShopSchema;
use Nreljed\ShopModule\Tests\Support\StubVerifiedWebhookDriver;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Stripe\PaymentIntent;

/**
 * The storefront let the shopper decide whether they had paid.
 *
 * `CreateOrder` wrote `payment_status = 'paid'` and `paid_at = now()` whenever
 * the request said `payment_method=stripe`, and stored the
 * `stripe_payment_intent_id` the browser supplied without ever asking Stripe
 * whether that intent captured anything. Posting the checkout form with an
 * arbitrary intent id produced a confirmed, "paid" order for goods nobody
 * paid for, and the signed webhook - which does verify - only arrived later to
 * set the same value again.
 *
 * Payment state is now the gateway's to report: orders are always created
 * pending, and `verifyPayment()` decides.
 */
final class PaymentVerificationTest extends TestCase
{
    use BootsShopSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootShopSchema();
        $this->resetShopTables();
    }

    public function tearDown(): void
    {
        if (Mockery::getContainer() !== null) {
            Mockery::close();
        }

        parent::tearDown();
    }

    // ---------------------------------------------------------------------
    // The order is never born paid.
    // ---------------------------------------------------------------------

    public function test_stripe_checkout_does_not_mark_the_order_paid(): void
    {
        $order = $this->createOrderFor(['payment_method' => 'stripe', 'stripe_payment_intent_id' => 'pi_anything']);

        $this->assertNotSame('paid', $order->payment_status, 'A client-supplied payment_method must not be able to mark an order paid.');
        $this->assertSame('pending', $order->payment_status);
        $this->assertNull($order->paid_at, 'paid_at must not be stamped from client input.');
    }

    public function test_stripe_checkout_does_not_trust_the_client_intent_id(): void
    {
        $order = $this->createOrderFor(['payment_method' => 'stripe', 'stripe_payment_intent_id' => 'pi_guessed']);

        // The reference is stored, because it is what the gateway will later be
        // asked about - but on its own it grants nothing.
        $this->assertSame('pi_guessed', $order->payment_intent_id);
        $this->assertSame('pending', $order->payment_status);
    }

    public function test_manual_checkout_is_pending_too(): void
    {
        $order = $this->createOrderFor(['payment_method' => 'manual']);

        $this->assertSame('pending', $order->payment_status);
        $this->assertNull($order->paid_at);
    }

    // ---------------------------------------------------------------------
    // Gateway answers.
    // ---------------------------------------------------------------------

    public function test_a_settled_intent_matching_the_order_is_paid(): void
    {
        $order = $this->order(total: '115.00', intent: 'pi_ok');

        $result = $this->stripeDriver($this->intent([
            'id' => 'pi_ok',
            'status' => 'succeeded',
            'amount' => 11500,
            'amount_received' => 11500,
            'currency' => 'eur',
            'metadata' => ['order_id' => (string) $order->id],
        ]))->verifyPayment('pi_ok', $order);

        $this->assertTrue($result->isPaid(), $result->reason ?? '');
    }

    public function test_an_intent_quoted_for_less_than_the_total_is_refused(): void
    {
        $order = $this->order(total: '115.00', intent: 'pi_short');

        $result = $this->stripeDriver($this->intent([
            'id' => 'pi_short',
            'status' => 'succeeded',
            // The intent itself is for a €1 amount, against a €115 order.
            'amount' => 100,
            'amount_received' => 100,
            'currency' => 'eur',
            'metadata' => ['order_id' => (string) $order->id],
        ]))->verifyPayment('pi_short', $order);

        $this->assertRefused($result, 'The amount paid does not match the order total.');
    }

    public function test_an_intent_carrying_no_order_stamp_is_refused(): void
    {
        // The metadata check used to be skipped when the stamp was absent,
        // which left a paid, correct-amount intent from anywhere replayable
        // against any order. Intents this app creates are always stamped, so
        // an unstamped one cannot be tied to this order and must not settle it.
        $order = $this->order(total: '115.00', intent: 'pi_unstamped');

        $result = $this->stripeDriver($this->intent([
            'id' => 'pi_unstamped',
            'status' => 'succeeded',
            'amount' => 11500,
            'amount_received' => 11500,
            'currency' => 'eur',
            'metadata' => [],
        ]))->verifyPayment('pi_unstamped', $order);

        $this->assertRefused($result, 'The payment was taken for a different order.');
    }

    public function test_a_paid_intent_cannot_be_replayed_against_a_second_order(): void
    {
        // The replay scenario end to end: a genuinely paid intent of the right
        // amount, offered to a different order, must not confirm it.
        $order = $this->order(total: '115.00', intent: 'pi_replay');
        $intent = $this->intent([
            'id' => 'pi_replay',
            'status' => 'succeeded',
            'amount' => 11500,
            'amount_received' => 11500,
            'currency' => 'eur',
            'metadata' => ['order_id' => '9999'],
        ]);

        $this->assertRefused($this->stripeDriver($intent)->verifyPayment('pi_replay', $order), 'The payment was taken for a different order.');
    }

    public function test_a_partially_captured_intent_is_refused(): void
    {
        $order = $this->order(total: '115.00', intent: 'pi_partial');

        $result = $this->stripeDriver($this->intent([
            'id' => 'pi_partial',
            'status' => 'succeeded',
            // The intent is for the right amount, but only part of it landed.
            'amount' => 11500,
            'amount_received' => 5000,
            'currency' => 'eur',
            'metadata' => ['order_id' => (string) $order->id],
        ]))->verifyPayment('pi_partial', $order);

        $this->assertRefused($result, 'Only part of the order total was paid.');
    }

    public function test_a_settled_intent_in_another_currency_is_refused(): void
    {
        $order = $this->order(total: '115.00', intent: 'pi_usd');

        $result = $this->stripeDriver($this->intent([
            'id' => 'pi_usd',
            'status' => 'succeeded',
            'amount' => 11500,
            'amount_received' => 11500,
            'currency' => 'usd',
            'metadata' => ['order_id' => (string) $order->id],
        ]))->verifyPayment('pi_usd', $order);

        $this->assertRefused($result, 'The payment was taken in a different currency.');
    }

    public function test_an_intent_belonging_to_another_order_is_refused(): void
    {
        $order = $this->order(total: '115.00', intent: 'pi_mine');

        $result = $this->stripeDriver($this->intent([
            'id' => 'pi_mine',
            'status' => 'succeeded',
            'amount_received' => 11500,
            'currency' => 'eur',
            'metadata' => ['order_id' => '999999'],
        ]))->verifyPayment('pi_mine', $order);

        $this->assertRefused($result, 'The payment was taken for a different order.');
    }

    public function test_an_intent_id_that_is_not_the_orders_own_is_refused(): void
    {
        $order = $this->order(total: '115.00', intent: 'pi_mine');

        $result = $this->stripeDriver($this->intent([
            'id' => 'someone_elses',
            'status' => 'succeeded',
            'amount' => 11500,
            'amount_received' => 11500,
            'currency' => 'eur',
            'metadata' => ['order_id' => (string) $order->id],
        ]))->verifyPayment('someone_elses', $order);

        $this->assertRefused($result, 'The payment reference does not match this order.');
    }

    public function test_an_async_method_is_pending_rather_than_paid_or_refused(): void
    {
        $order = $this->order(total: '115.00', intent: 'pi_sepa');

        $result = $this->stripeDriver($this->intent([
            'id' => 'pi_sepa',
            'status' => 'processing',
            'amount_received' => 0,
            'amount' => 11500,
            'currency' => 'eur',
            'metadata' => ['order_id' => (string) $order->id],
        ]))->verifyPayment('pi_sepa', $order);

        $this->assertTrue($result->isPending());
        $this->assertFalse($result->isPaid());
    }

    public function test_a_cancelled_intent_is_refused(): void
    {
        $order = $this->order(total: '115.00', intent: 'pi_cancel');

        $result = $this->stripeDriver($this->intent([
            'id' => 'pi_cancel',
            'status' => 'canceled',
            'amount_received' => 0,
            'amount' => 11500,
            'currency' => 'eur',
            'metadata' => ['order_id' => (string) $order->id],
        ]))->verifyPayment('pi_cancel', $order);

        $this->assertRefused($result, 'The payment was cancelled.');
    }

    public function test_an_intent_still_awaiting_a_method_is_refused(): void
    {
        $order = $this->order(total: '115.00', intent: 'pi_unpaid');

        $result = $this->stripeDriver($this->intent([
            'id' => 'pi_unpaid',
            'status' => 'requires_payment_method',
            'amount_received' => 0,
            'amount' => 11500,
            'currency' => 'eur',
            'metadata' => ['order_id' => (string) $order->id],
        ]))->verifyPayment('pi_unpaid', $order);

        $this->assertRefused($result, 'The payment has not completed.');
    }

    public function test_an_unreachable_gateway_never_reads_as_paid(): void
    {
        $order = $this->order(total: '115.00', intent: 'pi_net');

        $service = Mockery::mock(StripeService::class);
        $service->shouldReceive('retrievePaymentIntent')
            ->andThrow(new RuntimeException('Connection timed out'));

        $result = (new StripePaymentDriver($service, new PaymentIntentMatcher($service)))->verifyPayment('pi_net', $order);

        $this->assertTrue($result->isFailed(), 'A gateway we could not reach must not be treated as a successful payment.');
    }

    public function test_an_empty_reference_is_refused(): void
    {
        $order = $this->order(total: '115.00', intent: 'pi_x');

        $service = Mockery::mock(StripeService::class);
        $service->shouldNotReceive('retrievePaymentIntent');

        $result = (new StripePaymentDriver($service, new PaymentIntentMatcher($service)))->verifyPayment('', $order);

        $this->assertTrue($result->isFailed());
    }

    public function test_manual_payments_stay_pending_and_ask_nothing_of_a_gateway(): void
    {
        $order = $this->order(total: '115.00', intent: null);

        $result = (new ManualPaymentDriver)->verifyPayment('', $order);

        $this->assertTrue($result->isPending());
        $this->assertFalse($result->isPaid(), 'Manual payments are reconciled by staff, not self-certified.');
    }

    // ---------------------------------------------------------------------
    // Controller-level policy.
    // ---------------------------------------------------------------------

    public function test_manual_checkout_does_not_call_a_gateway(): void
    {
        $manager = Mockery::mock(PaymentManager::class);
        $manager->shouldNotReceive('driver');

        $result = $this->controllerVerify($this->controller($manager), 
            $this->order(total: '10.00', intent: null),
            ['payment_method' => 'manual'],
        );

        $this->assertTrue($result->isPending());
    }

    public function test_an_unavailable_payment_method_is_refused(): void
    {
        $manager = Mockery::mock(PaymentManager::class);
        $manager->shouldReceive('driver')->andThrow(new RuntimeException('Unsupported payment driver [paypal].'));

        $result = $this->controllerVerify($this->controller($manager), 
            $this->order(total: '10.00', intent: 'pi_x'),
            ['payment_method' => 'paypal', 'stripe_payment_intent_id' => 'pi_x'],
        );

        $this->assertTrue($result->isFailed());
    }

    public function test_a_gateway_error_degrades_to_pending_and_never_to_paid(): void
    {
        $driver = Mockery::mock(PaymentDriver::class);
        $driver->shouldReceive('verifyPayment')->andThrow(new RuntimeException('boom'));

        $manager = Mockery::mock(PaymentManager::class);
        $manager->shouldReceive('driver')->andReturn($driver);

        $result = $this->controllerVerify($this->controller($manager), 
            $this->order(total: '10.00', intent: 'pi_x'),
            ['payment_method' => 'stripe', 'stripe_payment_intent_id' => 'pi_x'],
        );

        $this->assertFalse($result->isPaid());
        $this->assertTrue($result->isPending(), 'An inconclusive check must leave the order awaiting the webhook, never paid.');
    }

    public function test_a_stripe_checkout_with_no_reference_at_all_is_refused(): void
    {
        $manager = Mockery::mock(PaymentManager::class);
        $manager->shouldNotReceive('driver');

        $result = $this->controllerVerify($this->controller($manager), 
            $this->order(total: '10.00', intent: null),
            ['payment_method' => 'stripe'],
        );

        $this->assertTrue($result->isFailed());
    }

    /**
     * The pipeline re-prices a reused draft from the live cart, so the intent
     * the shopper paid is not necessarily the intent that matches the order it
     * ends up with.
     *
     * Here the intent was raised against a €10 basket and captured, the cart
     * then grew to €115, and checkout reuses the original draft - which is
     * updated to €115. The order total and the money taken have silently
     * diverged, which is an underpayment, so verification must refuse it rather
     * than confirm a €115 order against a €10 charge.
     */
    public function test_a_cart_that_grew_after_the_intent_was_paid_cannot_check_out_on_the_cheap_intent(): void
    {
        $cart = $this->cart();

        $draft = Order::create([
            'cart_id' => $cart->id,
            'order_number' => 'SHOP-STALE-1',
            'customer_email' => 'ada@example.test',
            'customer_name' => 'Ada Lovelace',
            'subtotal' => 10.00,
            'total' => 10.00,
            'currency' => 'EUR',
            'status' => 'draft',
            'payment_method' => 'stripe',
            'payment_intent_id' => 'pi_cheap',
        ]);

        $context = new CheckoutContext(
            $cart,
            [
                'customer_name' => 'Ada Lovelace',
                'customer_email' => 'ada@example.test',
                'billing_address' => ['country' => 'GB'],
                'shipping_address' => ['country' => 'GB'],
                'payment_method' => 'stripe',
                'stripe_payment_intent_id' => 'pi_cheap',
            ],
            null,
            $draft,
        );

        // The basket is now worth 115, not the 10 the intent was raised for.
        $context->quote = [
            'subtotal' => 115.00,
            'discount_total' => 0.00,
            'shipping_total' => 0.00,
            'tax' => ['tax_total' => 0.00],
            'total' => 115.00,
            'currency' => 'EUR',
            'exchange_rate' => 1.0,
            'coupons' => collect(),
            'discount_breakdown' => [],
        ];

        (new CreateOrder)->handle($context, fn ($c) => $c);

        $order = $context->order;

        $this->assertSame(115.00, (float) $order->fresh()->total, 'The draft should have been re-priced from the live cart.');

        $result = $this->stripeDriver($this->intent([
            'id' => 'pi_cheap',
            'status' => 'succeeded',
            'amount' => 1000,
            'amount_received' => 1000,
            'currency' => 'eur',
            'metadata' => ['order_id' => (string) $order->id],
        ]))->verifyPayment('pi_cheap', $order);

        $this->assertRefused($result, 'The amount paid does not match the order total.');
    }

    // ---------------------------------------------------------------------
    // Provisional (pre-payment) drafts cannot be hijacked.
    // ---------------------------------------------------------------------

    public function test_a_draft_belonging_to_another_shoppers_cart_is_not_claimed(): void
    {
        $victimCart = $this->cart();
        $attackerCart = $this->cart();

        $victimDraft = $this->draftOrder($victimCart, 'pi_victim', $victimCart->id);

        $found = $this->findOwnProvisionalOrder($attackerCart, null, 'pi_victim');

        $this->assertNull($found, "Another shopper's draft order was claimable by payment intent alone.");
        $this->assertNotSame(
            $attackerCart->id,
            $victimDraft->fresh()->cart_id,
            "The claimed draft was re-pointed at the claiming shopper's cart."
        );
    }

    public function test_own_draft_is_claimed_and_reused(): void
    {
        $cart = $this->cart();
        $draft = $this->draftOrder($cart, 'pi_mine', $cart->id);

        $found = $this->findOwnProvisionalOrder($cart, null, 'pi_mine');

        $this->assertNotNull($found);
        $this->assertSame($draft->id, $found->id, "The shopper's own draft must be reused rather than duplicated.");
    }

    public function test_a_guest_cannot_claim_a_legacy_draft_that_has_no_cart_link(): void
    {
        // Drafts predating orders.cart_id carry no ownership anchor at all, so
        // for guests there is nothing left to prove ownership with and the
        // lookup must come back empty rather than match anything.
        $draft = $this->draftOrder($this->cart(), 'pi_legacy', null);

        $this->assertNull($this->findOwnProvisionalOrder($this->cart(), null, 'pi_legacy'));
        $this->assertNotNull($draft->fresh());
    }

    // ---------------------------------------------------------------------
    // The signed webhook is the authoritative settlement path for methods
    // that do not settle inline (SEPA, bank transfer).
    // ---------------------------------------------------------------------

    public function test_the_webhook_settles_a_correctly_paid_async_order(): void
    {
        $order = $this->order(total: '115.00', intent: 'pi_async');

        $this->webhook('payment_intent.succeeded', [
            'id' => 'pi_async',
            'status' => 'succeeded',
            'amount' => 11500,
            'amount_received' => 11500,
            'currency' => 'eur',
            'metadata' => ['order_id' => (string) $order->id],
        ]);

        $order->refresh();

        $this->assertSame('paid', $order->payment_status);
        $this->assertNotNull($order->paid_at);
    }

    public function test_the_webhook_will_not_settle_an_order_paid_the_wrong_amount(): void
    {
        // The async path is the one that actually settles SEPA and transfer
        // orders, so an underpayment arriving here must not confirm the order.
        $order = $this->order(total: '115.00', intent: 'pi_short_async');

        $this->webhook('payment_intent.succeeded', [
            'id' => 'pi_short_async',
            'status' => 'succeeded',
            'amount' => 1000,
            'amount_received' => 1000,
            'currency' => 'eur',
            'metadata' => ['order_id' => (string) $order->id],
        ]);

        $this->assertNull($order->refresh()->paid_at, 'A €10 payment settled a €115 order.');
        $this->assertSame('pending', $order->payment_status);
    }

    public function test_the_webhook_ignores_a_success_whose_stamp_names_another_order(): void
    {
        // The event's intent belongs to $order but its metadata names $other.
        // One of the two identifiers is therefore wrong, and since the webhook
        // resolves the order *from* the metadata, settling $other with an
        // intent it never issued is exactly the confusion to refuse. Neither
        // order may move.
        $order = $this->order(total: '115.00', intent: 'pi_mine');
        $other = $this->order(total: '115.00', intent: 'pi_theirs');

        $this->webhook('payment_intent.succeeded', [
            'id' => 'pi_mine',
            'status' => 'succeeded',
            'amount' => 11500,
            'amount_received' => 11500,
            'currency' => 'eur',
            'metadata' => ['order_id' => (string) $other->id],
        ]);

        $this->assertSame('pending', $order->refresh()->payment_status, "The intent's own order was settled via another order's id.");
        $this->assertSame('pending', $other->refresh()->payment_status, 'An order was settled with an intent it never issued.');
    }

    public function test_a_replayed_success_does_not_rewrite_when_the_order_was_paid(): void
    {
        // Stripe redelivers events until acknowledged, so the same success
        // arrives repeatedly. Re-stamping paid_at each time would rewrite the
        // record of when the shopper actually paid.
        $order = $this->order(total: '115.00', intent: 'pi_retry');
        // SQLite keeps no microseconds in datetime columns, so compare at
        // second precision - a mismatch here would be the column, not the code.
        $originalPaidAt = now()->subDays(3)->startOfSecond();

        $order->update(['payment_status' => 'paid', 'paid_at' => $originalPaidAt]);

        $this->webhook('payment_intent.succeeded', [
            'id' => 'pi_retry',
            'status' => 'succeeded',
            'amount' => 11500,
            'amount_received' => 11500,
            'currency' => 'eur',
            'metadata' => ['order_id' => (string) $order->id],
        ]);

        $this->assertSame(
            $originalPaidAt->toDateTimeString(),
            $order->refresh()->paid_at->toDateTimeString(),
            'A redelivered success event moved the recorded payment time.',
        );
    }

    public function test_a_late_failure_does_not_downgrade_a_settled_order(): void
    {
        // Event delivery is unordered, so a failure can land after the success
        // that settled the order - or after a refund.
        foreach (['paid', 'refunded', 'partially_refunded'] as $settled) {
            $order = $this->order(total: '115.00', intent: "pi_settled_{$settled}");
            $order->update(['payment_status' => $settled, 'paid_at' => now()]);

            $this->webhook('payment_intent.payment_failed', [
                'id' => "pi_settled_{$settled}",
                'status' => 'requires_payment_method',
                'metadata' => ['order_id' => (string) $order->id],
            ]);

            $this->assertSame(
                $settled,
                $order->refresh()->payment_status,
                "A late failure downgraded a {$settled} order.",
            );
        }
    }

    public function test_a_partial_refund_does_not_mark_the_whole_order_refunded(): void
    {
        $order = $this->order(total: '115.00', intent: 'pi_partial_refund');
        $order->update(['payment_status' => 'paid', 'paid_at' => now()]);

        $this->webhook('charge.refunded', [
            'payment_intent' => 'pi_partial_refund',
            'amount' => 11500,
            'amount_refunded' => 5000,
        ]);

        $this->assertSame(
            'partially_refunded',
            $order->refresh()->payment_status,
            'A €50 refund was recorded as a full refund of a €115 order.',
        );
    }

    public function test_a_full_refund_marks_the_order_refunded(): void
    {
        $order = $this->order(total: '115.00', intent: 'pi_full_refund');
        $order->update(['payment_status' => 'paid', 'paid_at' => now()]);

        $this->webhook('charge.refunded', [
            'payment_intent' => 'pi_full_refund',
            'amount' => 11500,
            'amount_refunded' => 11500,
        ]);

        $this->assertSame('refunded', $order->refresh()->payment_status);
    }

    public function test_an_unsigned_event_is_rejected(): void
    {
        $order = $this->order(total: '115.00', intent: 'pi_unsigned');

        $manager = Mockery::mock(PaymentManager::class);
        $manager->shouldReceive('driver')->andThrow(new RuntimeException('No signatures found matching the expected signature'));

        $controller = (new ReflectionClass(StripeWebhookController::class))->newInstanceWithoutConstructor();
        (new ReflectionClass(StripeWebhookController::class))->getProperty('paymentManager')->setValue($controller, $manager);
        (new ReflectionClass(StripeWebhookController::class))->getProperty('matcher')->setValue($controller, $this->matcher());

        $response = $controller->handle(Request::create('/store/webhook/stripe', 'POST', [], [], [], [], '{}'));

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('pending', $order->refresh()->payment_status);
    }

    // ---------------------------------------------------------------------
    // Helpers.
    // ---------------------------------------------------------------------

    private function matcher(): PaymentIntentMatcher
    {
        $service = Mockery::mock(StripeService::class);
        $service->shouldReceive('toCents')->andReturnUsing(
            fn (float $amount, string $currency = 'usd') => in_array(strtolower($currency), ['jpy', 'krw', 'vnd', 'clp', 'xof', 'xaf'], true)
                ? (int) round($amount)
                : (int) round($amount * 100)
        );

        return new PaymentIntentMatcher($service);
    }

    /**
     * Deliver a signed event through the real controller, with only the
     * signature check stubbed - the transition under test is the controller's.
     */
    private function webhook(string $type, array $object): void
    {
        $manager = Mockery::mock(PaymentManager::class);
        $manager->shouldReceive('driver')->andReturn(
            new StubVerifiedWebhookDriver(['type' => $type, 'data' => ['object' => $object]])
        );

        $controller = (new ReflectionClass(StripeWebhookController::class))->newInstanceWithoutConstructor();
        (new ReflectionClass(StripeWebhookController::class))->getProperty('paymentManager')->setValue($controller, $manager);
        (new ReflectionClass(StripeWebhookController::class))->getProperty('matcher')->setValue($controller, $this->matcher());

        $response = $controller->handle(Request::create('/store/webhook/stripe', 'POST', [], [], [], [], '{}'));

        $this->assertSame(200, $response->getStatusCode());
    }

    private function findOwnProvisionalOrder(Cart $cart, ?User $user, string $paymentIntentId): ?Order
    {
        return (new ReflectionClass(CheckoutController::class))
            ->getMethod('findOwnProvisionalOrder')
            ->invoke(
                (new ReflectionClass(CheckoutController::class))->newInstanceWithoutConstructor(),
                $cart,
                $user,
                $paymentIntentId,
            );
    }

    /**
     * `cartId` is explicit rather than defaulting to the cart's id, because one
     * test needs a draft with *no* ownership anchor at all.
     */
    private function draftOrder(Cart $cart, string $intent, ?int $cartId): Order
    {
        return Order::create([
            'cart_id' => $cartId,
            'customer_email' => 'victim@example.test',
            'customer_name' => 'Victim',
            'subtotal' => 10.00,
            'total' => 10.00,
            'currency' => 'EUR',
            'status' => 'draft',
            'payment_method' => 'stripe',
            'payment_intent_id' => $intent,
        ]);
    }

    /**
     * Assert the payment was refused *and* that it was refused by the intended
     * check.
     *
     * A bare `isFailed()` would still pass if a fixture tripped an earlier gate
     * than the one under test, which is how a test can quietly stop proving
     * what its name claims.
     */
    private function assertRefused(PaymentVerification $result, string $expectedReason): void
    {
        $this->assertTrue($result->isFailed(), 'Expected the payment to be refused, but it was not.');
        $this->assertSame($expectedReason, $result->reason);
    }

    private function controller(PaymentManager $manager): CheckoutController
    {
        $controller = (new ReflectionClass(CheckoutController::class))->newInstanceWithoutConstructor();

        $property = (new ReflectionClass(CheckoutController::class))->getProperty('paymentManager');
        $property->setValue($controller, $manager);

        return $controller;
    }

    /**
     * `verifyPayment()` is protected; it is the payment policy the controller
     * applies after checkout, which is exactly what these tests are about, so
     * it is invoked reflectively rather than duplicated here.
     */
    private function controllerVerify(CheckoutController $controller, Order $order, array $validated): PaymentVerification
    {
        return (new ReflectionClass(CheckoutController::class))
            ->getMethod('verifyPayment')
            ->invoke($controller, $order, $validated);
    }

    private function stripeDriver(PaymentIntent $intent): StripePaymentDriver
    {
        $service = Mockery::mock(StripeService::class);
        $service->shouldReceive('retrievePaymentIntent')->andReturn($intent);
        // `toCents()` is real arithmetic, not a stub, so the amount assertion
        // under test is the driver's comparison and not a mocked value.
        $service->shouldReceive('toCents')->andReturnUsing(
            fn (float $amount, string $currency = 'usd') => in_array(strtolower($currency), ['jpy', 'krw', 'vnd', 'clp', 'xof', 'xaf'], true)
                ? (int) round($amount)
                : (int) round($amount * 100)
        );

        return new StripePaymentDriver($service, new PaymentIntentMatcher($service));
    }

    /**
     * A real `PaymentIntent` built offline via the SDK's own factory, so the
     * driver is exercised against the actual response shape (and the mock's
     * declared return type is satisfied) rather than a hand-rolled stand-in.
     */
    private function intent(array $attributes): PaymentIntent
    {
        return PaymentIntent::constructFrom($attributes + [
            'object' => 'payment_intent',
            'metadata' => [],
        ]);
    }

    private function order(string $total, ?string $intent): Order
    {
        return Order::create([
            'customer_email' => 'ada@example.test',
            'customer_name' => 'Ada Lovelace',
            'subtotal' => $total,
            'total' => $total,
            'currency' => 'EUR',
            'payment_method' => $intent !== null ? 'stripe' : 'manual',
            'payment_intent_id' => $intent,
        ]);
    }

    /**
     * Run the real pipe with the given request payload - the same entry point
     * `CheckoutService` uses.
     */
    private function createOrderFor(array $validated): Order
    {
        $context = new CheckoutContext(
            $this->cart(),
            $validated + [
                'customer_name' => 'Ada Lovelace',
                'customer_email' => 'ada@example.test',
                'billing_address' => ['country' => 'GB'],
                'shipping_address' => ['country' => 'GB'],
            ],
            null,
        );

        $context->quote = [
            'subtotal' => 100.00,
            'discount_total' => 0.00,
            'shipping_total' => 0.00,
            'tax' => ['tax_total' => 0.00],
            'total' => 100.00,
            'currency' => 'EUR',
            'exchange_rate' => 1.0,
            'coupons' => collect(),
            'discount_breakdown' => [],
        ];

        (new CreateOrder)->handle($context, fn ($c) => $c);

        return $context->order;
    }

    private function cart(): \Nreljed\ShopModule\Models\Cart
    {
        return \Nreljed\ShopModule\Models\Cart::create(['session_id' => 's'.uniqid()]);
    }
}
