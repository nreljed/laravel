<?php

namespace Nreljed\ShopModule\Tests;

use Illuminate\Container\Container;
use Illuminate\Pipeline\Pipeline;
use Nreljed\ShopModule\Models\Cart;
use Nreljed\ShopModule\Models\CartItem;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ShippingMethod;
use Nreljed\ShopModule\Models\StockAdjustment;
use Nreljed\ShopModule\Pipelines\CartLines\BuildOrderItemAttributes;
use Nreljed\ShopModule\Pipelines\Checkout\ClearCart;
use Nreljed\ShopModule\Pipelines\Checkout\CreateOrder;
use Nreljed\ShopModule\Pipelines\Checkout\CreateOrderItems;
use Nreljed\ShopModule\Pipelines\Checkout\RecordInventory;
use Nreljed\ShopModule\Pipelines\Checkout\ResolveShippingMethod;
use Nreljed\ShopModule\Pipelines\CheckoutContext;
use Nreljed\ShopModule\Services\InventoryService;
use Nreljed\ShopModule\Services\WebhookService;
use Nreljed\ShopModule\Tests\Concerns\BootsShopSchema;
use PHPUnit\Framework\TestCase;

/**
 * Executes the five checkout pipes that were previously only ever
 * `class_exists` checks, against the package's real schema.
 *
 * `CreateOrder`, `CreateOrderItems`, `RecordInventory`, `ClearCart` and
 * `ResolveShippingMethod` together are what turns a quote into a real order:
 * they write every `orders`/`order_items` row, move stock, and destroy the
 * cart. None of that was covered, so a typo in any of them would have shipped
 * a checkout that silently produced empty orders or never decremented stock.
 *
 * These assertions also lock the money columns together: `subtotal`,
 * `discount_total`, `tax_total`, `shipping_total` and `total` are written from
 * the quote but never reconciled against each other, and `order_items.subtotal`
 * is never compared to the order total.
 */
class CheckoutPipelineDatabaseTest extends TestCase
{
    use BootsShopSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootShopSchema();
        $this->resetShopTables();
    }

    public function test_resolve_shipping_method_loads_the_requested_method(): void
    {
        $method = ShippingMethod::create([
            'name' => 'Flat Rate',
            'slug' => 'flat-rate',
            'cost' => 5.00,
            'is_active' => true,
        ]);

        $context = new CheckoutContext($this->cart(), ['shipping_method_id' => $method->id], null);

        (new Pipeline(app()))->send($context)->through([new ResolveShippingMethod])->thenReturn();

        $this->assertTrue($context->shippingMethod->is($method));
    }

    public function test_resolve_shipping_method_throws_when_the_method_does_not_exist(): void
    {
        $context = new CheckoutContext($this->cart(), ['shipping_method_id' => 9999], null);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        (new Pipeline(app()))->send($context)->through([new ResolveShippingMethod])->thenReturn();
    }

    public function test_create_order_persists_every_money_column_from_the_quote(): void
    {
        $quote = $this->quote();
        $context = $this->contextWithQuote($quote);

        (new Pipeline(app()))->send($context)->through([new CreateOrder])->thenReturn();

        $order = $context->order;

        $this->assertNotNull($order->id);
        $this->assertRowExists('orders', [
            'subtotal' => 100.00,
            'discount_total' => 10.00,
            'tax_total' => 20.00,
            'shipping_total' => 5.00,
            'total' => 115.00,
            'currency' => 'EUR',
        ]);
        $this->assertNotEmpty($order->order_number);
        $this->assertSame('pending', $order->status);
    }

    public function test_create_order_snapshots_applied_coupons_onto_the_order(): void
    {
        $quote = $this->quote();
        $quote['coupons'] = collect([
            (object) ['id' => 4, 'code' => 'SAVE10', 'type' => 'fixed_cart'],
        ]);

        $context = $this->contextWithQuote($quote);

        (new Pipeline(app()))->send($context)->through([new CreateOrder])->thenReturn();

        $this->assertSame([
            ['coupon_id' => 4, 'code' => 'SAVE10', 'type' => 'fixed_cart'],
        ], $context->order->applied_coupons);
    }

    public function test_create_order_reuses_the_provisional_draft_instead_of_inserting_a_second_row(): void
    {
        $provisional = Order::create([
            'status' => 'draft',
            'customer_email' => 'draft@example.test',
        ]);

        $context = $this->contextWithQuote($this->quote(), $provisional);

        (new Pipeline(app()))->send($context)->through([new CreateOrder])->thenReturn();

        $this->assertSame($provisional->id, $context->order->id);
        $this->assertSame(1, Order::count());
        $this->assertSame(100.00, (float) $context->order->subtotal);
    }

    public function test_create_order_items_writes_one_row_per_cart_line(): void
    {
        $product = $this->product('Seiko Watch', 50.00, stock: 10);
        $cart = $this->cart();
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 50.00]);

        $context = $this->contextWithQuote($this->quote());
        $context->cart = $cart->fresh()->load('items.product', 'items.variation');

        // `CreateOrderItems` needs an order on the context, so run the pair in
        // the order the real checkout pipeline uses.
        (new Pipeline(app()))
            ->send($context)
            ->through([new CreateOrder, new CreateOrderItems])
            ->thenReturn();

        $this->assertRowExists('order_items', [
            'order_id' => $context->order->id,
            'product_id' => $product->id,
            'product_name' => 'Seiko Watch',
            'quantity' => 2,
            'unit_price' => '50.00',
            'subtotal' => 100.00,
            'total' => 100.00,
            'currency' => 'EUR',
        ]);
    }

    public function test_order_item_subtotal_reconciles_with_the_cart_line_quantity(): void
    {
        $product = $this->product('Group Item', 30.00, stock: 5);
        $cart = $this->cart();
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 30.00]);

        $context = $this->contextWithQuote($this->quote());
        $context->cart = $cart->fresh()->load('items.product', 'items.variation');

        (new Pipeline(app()))
            ->send($context)
            ->through([new CreateOrder, new CreateOrderItems])
            ->thenReturn();

        $item = $context->order->items()->first();

        $this->assertEqualsWithDelta(
            (float) $item->unit_price * $item->quantity,
            (float) $item->subtotal,
            0.001,
        );
        $this->assertEqualsWithDelta((float) $item->subtotal, (float) $item->total, 0.001);
    }

    public function test_record_inventory_decrements_stock_and_writes_an_adjustment(): void
    {
        $product = $this->product('Tracked Item', 25.00, stock: 10);

        $order = Order::create(['status' => 'pending']);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => 'Tracked Item',
            'quantity' => 3,
            'unit_price' => '25.00',
            'subtotal' => 75.00,
            'total' => 75.00,
        ]);

        $context = $this->contextWithQuote($this->quote());
        $context->order = $order;

        (new Pipeline(app()))
            ->send($context)
            ->through([new RecordInventory($this->inventoryService())])
            ->thenReturn();

        $this->assertSame(7, (int) $product->fresh()->stock_quantity);

        $this->assertRowExists('stock_adjustments', [
            'product_id' => $product->id,
            'type' => 'sale',
            'quantity' => -3,
            'stock_before' => 10,
            'stock_after' => 7,
            'reference_type' => 'order',
            'reference_id' => $order->id,
        ]);
    }

    public function test_record_inventory_skips_products_that_do_not_track_stock(): void
    {
        $product = $this->product('Untracked Item', 25.00, stock: 0, trackStock: false);

        $order = Order::create(['status' => 'pending']);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => 'Untracked Item',
            'quantity' => 3,
            'unit_price' => '25.00',
            'subtotal' => 75.00,
            'total' => 75.00,
        ]);

        $context = $this->contextWithQuote($this->quote());
        $context->order = $order;

        (new Pipeline(app()))
            ->send($context)
            ->through([new RecordInventory($this->inventoryService())])
            ->thenReturn();

        $this->assertSame(0, (int) $product->fresh()->stock_quantity);
        $this->assertSame(0, StockAdjustment::count());
    }

    public function test_clear_cart_empties_lines_and_links_the_cart_to_the_order(): void
    {
        $product = $this->product('Anything', 10.00, stock: 5);
        $cart = $this->cart();
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10.00]);
        $cart->update(['coupon_code' => 'SAVE10', 'discount' => 10.00]);

        $context = $this->contextWithQuote($this->quote());
        $context->cart = $cart->fresh();
        $context->order = Order::create(['status' => 'pending']);

        (new Pipeline(app()))->send($context)->through([new ClearCart])->thenReturn();

        $this->assertSame(0, $context->cart->fresh()->items()->count());

        $fresh = $context->cart->fresh();
        $this->assertNull($fresh->coupon_code);
        $this->assertEqualsWithDelta(0.0, (float) $fresh->discount, 0.001);
        $this->assertSame('EUR', $fresh->currency);
        $this->assertSame($context->order->id, $fresh->converted_to_order_id);
    }

    /**
     * The grand total is assembled from five independently written columns
     * and nothing ever checked they agree. Pin the identity.
     */
    public function test_order_grand_total_reconciles_with_its_components(): void
    {
        $quote = $this->quote();
        $context = $this->contextWithQuote($quote);

        (new Pipeline(app()))->send($context)->through([new CreateOrder])->thenReturn();

        $order = $context->order;

        $this->assertEqualsWithDelta(
            (float) $order->subtotal - (float) $order->discount_total
            + (float) $order->tax_total + (float) $order->shipping_total,
            (float) $order->total,
            0.001,
        );
    }

    private function quote(): array
    {
        return [
            'subtotal' => 100.00,
            'discount_total' => 10.00,
            'shipping_total' => 5.00,
            'tax' => ['tax_total' => 20.00],
            'total' => 115.00,
            'currency' => 'EUR',
            'exchange_rate' => 1.0,
            'coupons' => collect(),
            'discount_breakdown' => [],
        ];
    }

    private function contextWithQuote(array $quote, ?Order $provisional = null): CheckoutContext
    {
        $context = new CheckoutContext($this->cart(), [
            'payment_method' => 'manual',
            'customer_name' => 'Ada Lovelace',
            'customer_email' => 'ada@example.test',
            'billing_address' => ['country' => 'GB'],
            'shipping_address' => ['country' => 'GB'],
        ], null, $provisional);

        $context->quote = $quote;

        return $context;
    }

    private function cart(): Cart
    {
        return Cart::create(['session_id' => 'session-'.uniqid()]);
    }

    private function product(string $name, float $price, int $stock, bool $trackStock = true): Product
    {
        $product = new Product([
            'name' => $name,
            'type' => 'simple',
            'price' => $price,
            'track_stock' => $trackStock,
            'stock_quantity' => $stock,
        ]);
        $product->status = 'published';
        $product->save();

        return $product;
    }

    /**
     * A real InventoryService with its webhook side effects neutralised:
     * these tests assert stock movement, not webhook delivery.
     */
    private function inventoryService(): InventoryService
    {
        $webhooks = new class extends WebhookService
        {
            public function __construct() {}

            public function dispatch(string $event, array $payload = []): void {}
        };

        return new InventoryService($webhooks);
    }
}
