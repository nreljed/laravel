<?php

namespace Nreljed\ShopModule\Tests;

use Illuminate\Validation\ValidationException;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Models\OrderItem;
use Nreljed\ShopModule\Services\InventoryService;
use Nreljed\ShopModule\Tests\Concerns\BootsShopSchema;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * A refund could be made to exceed the order total, and it restocked the wrong
 * stock.
 *
 * Three separate things had to line up:
 *
 *  - `items.*.order_item_id` was validated with a bare `exists:order_items,id`,
 *    a global check. Refunding order #5 accepted line ids belonging to order
 *    #9 and booked those amounts against #5.
 *  - Nothing capped the amount. `total_refunded` was incremented by whatever
 *    was submitted, so two refunds of the same line both passed validation and
 *    left the order over-refunded, with `payment_status` merely flipping to
 *    `refunded` once the running total crossed the order total.
 *  - `recordRefundForOrder()` returns *every* line at full quantity, so a
 *    refund of one item out of three put the other two back into stock as well.
 *    The submitted `quantity` was validated and then discarded.
 */
final class RefundIntegrityTest extends TestCase
{
    use BootsShopSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootShopSchema();
        $this->resetShopTables();
    }

    // ---------------------------------------------------------------------
    // Line ownership.
    // ---------------------------------------------------------------------

    public function test_a_line_from_another_order_is_rejected(): void
    {
        $order = $this->order(total: 100);
        $other = $this->order(total: 500);
        $foreignLine = $this->line($other, name: 'Someone else\'s item', total: 400);

        $errors = $this->validateRefund($order, [
            ['order_item_id' => $foreignLine->id, 'quantity' => 1, 'amount' => 400],
        ]);

        $this->assertArrayHasKey('items.0.order_item_id', $errors, 'A global `exists` check accepted another order\'s line and booked its amount against this order.');
    }

    // ---------------------------------------------------------------------
    // Affordability.
    // ---------------------------------------------------------------------

    public function test_a_refund_larger_than_the_line_is_rejected(): void
    {
        $order = $this->order(total: 100);
        $line = $this->line($order, name: 'Mug', total: 25);

        $errors = $this->validateRefund($order, [
            ['order_item_id' => $line->id, 'quantity' => 1, 'amount' => 25.01],
        ]);

        $this->assertArrayHasKey('items.0.amount', $errors, 'A line worth 25.00 accepted a refund of any size.');
    }

    public function test_a_refund_cannot_claim_more_units_than_were_bought(): void
    {
        $order = $this->order(total: 100);
        $line = $this->line($order, name: 'Mug', quantity: 2, total: 50);

        $errors = $this->validateRefund($order, [
            ['order_item_id' => $line->id, 'quantity' => 3, 'amount' => 10],
        ]);

        $this->assertArrayHasKey('items.0.quantity', $errors, 'Three units were refunded from a line that shipped two.');
    }

    public function test_shipping_refund_cannot_exceed_the_shipping_charged(): void
    {
        $order = $this->order(total: 100, shippingTotal: 8);
        $line = $this->line($order, name: 'Mug', total: 92);

        $errors = $this->validateRefund($order, [
            ['order_item_id' => $line->id, 'quantity' => 1, 'amount' => 92],
        ], shippingRefund: 8.01);

        $this->assertArrayHasKey('shipping_refund', $errors, 'Shipping charged was 8.00; a 1000.00 shipping refund validated.');
    }

    public function test_a_refund_within_every_cap_passes(): void
    {
        $order = $this->order(total: 100, shippingTotal: 8);
        $line = $this->line($order, name: 'Mug', total: 92);

        $this->assertSame(
            [],
            $this->validateRefund($order, [
                ['order_item_id' => $line->id, 'quantity' => 1, 'amount' => 92],
            ], shippingRefund: 8),
            'The caps must not make a legitimate refund impossible.'
        );
    }

    public function test_an_already_over_refunded_order_is_refused_outright(): void
    {
        $order = $this->order(total: 100);
        $order->update(['total_refunded' => 140]);
        $line = $this->line($order, name: 'Mug', total: 92);

        $errors = $this->validateRefund($order, [
            ['order_item_id' => $line->id, 'quantity' => 1, 'amount' => 10],
        ]);

        $this->assertArrayHasKey('items', $errors, 'An order already refunded past its total should stop accepting refunds until it is reconciled.');

        // Also over the sum cap, so the specific wording is what distinguishes
        // the two guards. "the -40.00 still refundable" tells an admin nothing.
        $this->assertStringContainsString(
            'over-refunded',
            implode(' ', (array) $errors['items']),
            'An over-refunded order needs a message that says so, rather than a negative allowance.'
        );
    }

    // ---------------------------------------------------------------------
    // The sum, which no per-line cap can see.
    // ---------------------------------------------------------------------

    public function test_lines_that_each_pass_their_own_cap_cannot_sum_past_the_order_total(): void
    {
        // A discount makes this shape real: the lines are worth 120, the order
        // is worth 100. Each row is at its own cap, so nothing else rejects it.
        $order = $this->order(total: 100);
        $first = $this->line($order, name: 'Mug', total: 60);
        $second = $this->line($order, name: 'Kettle', total: 60);

        $errors = $this->validateRefund($order, [
            ['order_item_id' => $first->id, 'quantity' => 1, 'amount' => 60],
            ['order_item_id' => $second->id, 'quantity' => 1, 'amount' => 60],
        ]);

        $this->assertArrayHasKey('items', $errors, 'Two refunds at 60 each passed every per-line cap and refunded 120.00 on a 100.00 order.');
    }

    public function test_the_same_line_listed_twice_cannot_refund_twice(): void
    {
        $order = $this->order(total: 100);
        $order->update(['total_refunded' => 40]);
        $line = $this->line($order, name: 'Mug', total: 50);

        $errors = $this->validateRefund($order, [
            ['order_item_id' => $line->id, 'quantity' => 1, 'amount' => 50],
            ['order_item_id' => $line->id, 'quantity' => 1, 'amount' => 50],
        ]);

        $this->assertArrayHasKey('items', $errors, 'Listing one line twice refunded it twice: 100.00 against the 60.00 left.');
    }

    public function test_shipping_counts_towards_the_remaining_total(): void
    {
        // Deliberately tuned so only the shipping decides it: the 92.00 line
        // alone fits inside the 95.00 left, the 8.00 shipping does not.
        $order = $this->order(total: 100, shippingTotal: 8);
        $order->update(['total_refunded' => 5]);
        $line = $this->line($order, name: 'Mug', total: 92);

        $errors = $this->validateRefund($order, [
            ['order_item_id' => $line->id, 'quantity' => 1, 'amount' => 92],
        ], shippingRefund: 8);

        $this->assertArrayHasKey('items', $errors, 'Item and shipping each passed their own caps but together exceeded the 95.00 remaining.');
    }

    public function test_a_refund_that_exactly_uses_the_remaining_total_passes(): void
    {
        $order = $this->order(total: 100, shippingTotal: 8);
        $order->update(['total_refunded' => 20]);
        $line = $this->line($order, name: 'Mug', total: 92);

        $this->assertSame(
            [],
            $this->validateRefund($order, [
                ['order_item_id' => $line->id, 'quantity' => 1, 'amount' => 72],
            ], shippingRefund: 8),
            'The sum cap must allow spending the remainder exactly, not less.'
        );
    }

    // ---------------------------------------------------------------------
    // Restocking.
    // ---------------------------------------------------------------------

    public function test_a_partial_refund_returns_only_the_refunded_lines(): void
    {
        [$order, $refunded, $kept] = $this->orderWithTwoLines();

        $this->restock($order, [
            ['order_item_id' => $refunded->id, 'quantity' => 1],
        ]);

        $this->assertSame(1, $this->stockFor($refunded), 'The refunded line came back.');
        $this->assertSame(0, $this->stockFor($kept), 'The line the customer kept was also returned to stock, inflating availability for a product that was never returned.');
    }

    public function test_a_partial_refund_returns_only_the_refunded_quantity(): void
    {
        [$order, $line] = $this->orderWithTwoLines(quantity: 3);

        $this->restock($order, [
            ['order_item_id' => $line->id, 'quantity' => 1],
        ]);

        $this->assertSame(1, $this->stockFor($line), 'One of three units came back; the other two were still sold.');
    }

    public function test_repeated_refund_lines_are_summed_not_overwritten(): void
    {
        [$order, $line] = $this->orderWithTwoLines(quantity: 5);

        $this->restock($order, [
            ['order_item_id' => $line->id, 'quantity' => 2],
            ['order_item_id' => $line->id, 'quantity' => 3],
        ]);

        $this->assertSame(5, $this->stockFor($line), 'Two rows for the same line are one refund of five units, not a refund of three.');
    }

    public function test_a_full_order_restock_is_unchanged(): void
    {
        [$order, $refunded, $kept] = $this->orderWithTwoLines();

        // The cancellation and hold-expiry paths still mean "everything".
        $this->inventory()->recordRefundForOrder($order);

        $this->assertSame(2, $this->stockFor($refunded));
        $this->assertSame(2, $this->stockFor($kept), 'Cancelling an order must still return every line.');
    }

    /**
     * The restock tests above drive the service directly, so they cannot see
     * which method `refund()` actually calls. Read the controller so swapping
     * it back to the whole-order path - which is what it did - is a failure.
     */
    public function test_the_refund_action_restock_only_the_named_lines(): void
    {
        $source = (string) file_get_contents(
            (new \ReflectionClass(\Nreljed\ShopModule\Http\Controllers\OrdersController::class))->getFileName()
        );

        $this->assertStringContainsString(
            'recordRefundForLines($order, $validated',
            $source,
            '`refund()` must restock the lines it was given; `recordRefundForOrder()` returns every line at full quantity.'
        );
    }

    // ---------------------------------------------------------------------
    // Helpers.
    // ---------------------------------------------------------------------

    private function inventory(): InventoryService
    {
        // Resolved rather than reflected: `recordRefund()` dispatches a
        // webhook, so the constructor's `WebhookService` has to be real.
        return app(InventoryService::class);
    }

    private function restock(Order $order, array $lines): void
    {
        $this->inventory()->recordRefundForLines($order, $lines);
    }

    private function stockFor(OrderItem $item): int
    {
        return (int) $item->product->fresh()->stock_quantity;
    }

    /**
     * Runs the request's validator exactly as the framework would and returns
     * the error bag, so a failure reads as a field error rather than a thrown
     * `ValidationException`.
     *
     * @return array<string, string>
     */
    private function validateRefund(Order $order, array $items, ?float $shippingRefund = null): array
    {
        $route = new class($order)
        {
            public function __construct(private Order $order) {}

            public function parameter($key, $default = null)
            {
                return $key === 'order' ? $this->order : $default;
            }
        };

        $form = \Nreljed\ShopModule\Requests\RefundOrderRequest::create('/', 'POST', array_merge(
            ['items' => $items],
            $shippingRefund !== null ? ['shipping_refund' => $shippingRefund] : []
        ));

        $form->setRouteResolver(fn () => $route);

        // Built by hand: the harness boots Eloquent but never registered the
        // validation factory, so the `validator()` helper cannot resolve it.
        $factory = new \Illuminate\Validation\Factory(
            new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader, 'en'),
            app()
        );

        // `exists` is what proves a line belongs to this order, so the verifier
        // has to be wired to the real connection for the test to mean anything.
        $factory->setPresenceVerifier(
            new \Illuminate\Validation\DatabasePresenceVerifier(app('db'))
        );

        $validator = $factory->make($form->all(), $form->rules(), $form->attributes());

        $form->withValidator($validator);

        if ($validator->fails()) {
            return $validator->errors()->toArray();
        }

        return [];
    }

    private function order(float $total = 100, float $shippingTotal = 0): Order
    {
        $order = Order::create([
            'order_number' => 'ORD-'.strtoupper(uniqid()),
            'status' => 'processing',
            'payment_status' => 'paid',
            'subtotal' => $total,
            'shipping_total' => $shippingTotal,
            'tax_total' => 0,
            'discount_total' => 0,
            'total' => $total,
            'total_refunded' => 0,
            'currency' => 'EUR',
        ]);

        return $order->fresh();
    }

    private function line(Order $order, string $name, int $quantity = 1, float $total = 25): OrderItem
    {
        return OrderItem::create([
            'order_id' => $order->id,
            'product_name' => $name,
            'quantity' => $quantity,
            'unit_price' => $total / max($quantity, 1),
            'subtotal' => $total,
            'tax_total' => 0,
            'total' => $total,
        ]);
    }

    /**
     * @return array{0: Order, 1: OrderItem, 2: OrderItem}
     */
    private function orderWithTwoLines(int $quantity = 2): array
    {
        $order = $this->order(total: 100);

        $a = $this->product('Refund me', stock: 0);
        $b = $this->product('Keep me', stock: 0);

        $refunded = $this->line($order, 'Refund me', $quantity, 50);
        $kept = $this->line($order, 'Keep me', $quantity, 50);

        $refunded->update(['product_id' => $a->id]);
        $kept->update(['product_id' => $b->id]);

        return [$order->fresh(), $refunded->fresh(), $kept->fresh()];
    }

    private function product(string $name, int $stock): \Nreljed\ShopModule\Models\Product
    {
        return \Nreljed\ShopModule\Models\Product::create([
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'status' => 'published',
            'type' => 'simple',
            'catalog_visibility' => 'visible',
            'price' => 25,
            'stock_quantity' => $stock,
            'track_stock' => true,
        ]);
    }
}
