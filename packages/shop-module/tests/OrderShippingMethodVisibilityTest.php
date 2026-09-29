<?php

namespace Nreljed\ShopModule\Tests;

use Nreljed\ShopModule\Http\Controllers\OrdersController;
use Nreljed\ShopModule\Http\Resources\OrderResource;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Models\ShippingMethod;
use Nreljed\ShopModule\Services\WebhookService;
use Nreljed\ShopModule\Tests\Concerns\BootsShopSchema;
use PHPUnit\Framework\TestCase;

/**
 * The shipping snapshot has to be readable, or recording it is pointless.
 *
 * `CreateOrder` writes both `shipping_method_id` and `shipping_method_name` so
 * that "which carrier went wrong" stays answerable after the method is deleted
 * or renamed. That only buys anything if something can read it back. It could
 * not: the admin API resource, the CSV export and the outbound webhook payload
 * all reported `shipping_total` and none of them reported the method, so the
 * one question the column was added to answer was unanswerable from every staff
 * surface and from every integration.
 *
 * The shape of the bug is worth naming, because it is easy to reintroduce. A
 * write-only field is invisible: nothing errors, the migration applies, the
 * checkout tests pass, and the data is simply never shown. Tests that assert a
 * value was *stored* will stay green no matter how many read surfaces forget
 * it - which is exactly what happened. So each surface is asserted separately,
 * and the export is asserted at the source level because the streaming response
 * cannot be decoded without a booted HTTP kernel.
 */
final class OrderShippingMethodVisibilityTest extends TestCase
{
    use BootsShopSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootShopSchema();
        $this->resetShopTables();
    }

    public function test_the_admin_order_resource_reports_the_shipping_method(): void
    {
        $method = $this->method('DHL Express');
        $order = $this->order($method, shippingTotal: 12.50);

        $this->assertSame('DHL Express', $this->resource($order)['shipping_method_name']);
    }

    public function test_the_admin_order_resource_reports_the_live_name_beside_the_snapshot(): void
    {
        $method = $this->method('Flat Rate');
        $order = $this->order($method);

        $order->load('shippingMethod');
        $method->update(['name' => 'Flat Rate 2026']);

        $payload = $this->resource($order->fresh()->load('shippingMethod'));

        // The snapshot is what the parcel went with; the relation is what the
        // method is called now. Reporting only one of them makes a rename
        // indistinguishable from a deletion.
        $this->assertSame('Flat Rate', $payload['shipping_method_name']);
        $this->assertSame('Flat Rate 2026', $payload['shipping_method']);
    }

    public function test_the_order_resource_survives_a_deleted_method(): void
    {
        $method = $this->method('PostNL');
        $order = $this->order($method);

        $method->delete();

        $payload = $this->resource($order->fresh()->load('shippingMethod'));

        $this->assertSame('PostNL', $payload['shipping_method_name']);
        $this->assertNull($payload['shipping_method']);
    }

    public function test_an_order_with_no_method_reports_null_rather_than_omitting_the_key(): void
    {
        $order = Order::create([
            'order_number' => 'NO-SHIP',
            'status' => 'pending',
            'subtotal' => 40,
            'shipping_total' => 0,
            'tax_total' => 0,
            'discount_total' => 0,
            'total' => 40,
            'customer_name' => 'No Ship',
            'customer_email' => 'noship@example.test',
        ]);

        $payload = $this->resource($order->fresh()->load('shippingMethod'));

        $this->assertArrayHasKey('shipping_method_name', $payload);
        $this->assertNull($payload['shipping_method_name']);
    }

    public function test_the_webhook_payload_reports_the_shipping_method(): void
    {
        $order = $this->order($this->method('UPS Ground'), shippingTotal: 8.25);

        $payload = $this->payloadFor($order);

        // An integration that receives `shipping_total` and no method cannot
        // tell a slow carrier from a cheap one, which is the whole reason a
        // receiver would be reading it.
        $this->assertSame('UPS Ground', $payload['shipping_method']);
        $this->assertSame(8.25, $payload['shipping_total']);
    }

    public function test_the_webhook_payload_survives_a_deleted_method(): void
    {
        $method = $this->method('DHL Express');
        $order = $this->order($method);
        $method->delete();

        $this->assertSame('DHL Express', $this->payloadFor($order->fresh())['shipping_method']);
    }

    /**
     * Source-level, because the export is a streamed CSV response that cannot be
     * decoded without a booted HTTP kernel and a staff session.
     *
     * What is pinned is the pairing that matters: `shipping_method` has to be in
     * the header *and* in the row, at matching positions. Asserting only that
     * the string appears somewhere in the file would pass on a header with no
     * row value, which is the shape that produces a column of blanks.
     */
    public function test_the_csv_export_pairs_the_method_header_with_a_row_value(): void
    {
        $source = file_get_contents((new \ReflectionClass(OrdersController::class))->getFileName());

        $this->assertStringContainsString("'shipping_method'", $source, 'The export header has no shipping_method column.');

        preg_match_all('/fputcsv\(\s*\$handle,\s*\[(.*?)\]/s', $source, $matches);

        $this->assertNotEmpty($matches[1], 'No fputcsv() header/row pairs found in OrdersController.');

        $header = $this->columns($matches[1][0], quotedOnly: true);
        $row = $this->columns($matches[1][1]);

        $this->assertContains('shipping_method', $header);
        $this->assertContains('shipping_method_name', $row, 'The export row must read the snapshot, not the relation.');
        $this->assertSame(
            array_search('shipping_method', $header, true),
            array_search('shipping_method_name', $row, true),
            'shipping_method sits at a different position in the header and the row, so the column would be mislabelled.',
        );
    }

    public function test_the_admin_order_page_loads_the_shipping_method_relation(): void
    {
        $source = file_get_contents((new \ReflectionClass(OrdersController::class))->getFileName());

        // The admin page renders the raw model rather than the resource, so the
        // relation is only serialised if `show()` eager-loads it. Without this
        // the page silently has no current name to compare the snapshot against.
        preg_match('/public function show\(Order \$record\).*?\n    \}/s', $source, $method);

        $this->assertNotEmpty($method, 'Could not locate OrdersController::show().');
        $this->assertStringContainsString('shippingMethod', $method[0], 'show() does not load the shippingMethod relation.');
    }

    private function method(string $name): ShippingMethod
    {
        return ShippingMethod::create([
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'cost' => 8.25,
            'is_active' => true,
        ]);
    }

    private function order(?ShippingMethod $method, float $shippingTotal = 0.0): Order
    {
        return Order::create([
            'order_number' => 'VIS-'.($method?->id ?? 0).'-'.$shippingTotal,
            'status' => 'pending',
            'subtotal' => 40,
            'shipping_total' => $shippingTotal,
            'tax_total' => 0,
            'discount_total' => 0,
            'total' => 40 + $shippingTotal,
            'customer_name' => 'Visibility Test',
            'customer_email' => 'visibility@example.test',
            'shipping_method_id' => $method?->id,
            'shipping_method_name' => $method?->name,
        ]);
    }

    private function payloadFor(Order $order): array
    {
        $service = new class extends WebhookService
        {
            public function orderPayload(Order $order): array
            {
                return $this->buildOrderPayload($order);
            }
        };

        return $service->orderPayload($order->loadMissing('items'));
    }

    /**
     * @return array<string, mixed>
     */
    private function resource(Order $order): array
    {
        // A real Request rather than the `request()` helper: the harness boots
        // the database and the facades, not an HTTP kernel, so the helper has no
        // container binding to resolve. `OrderResource::toArray()` only needs
        // the object for its type hint.
        return (new OrderResource($order))->toArray(new \Illuminate\Http\Request());
    }

    /**
     * @return list<string>
     */
    private function columns(string $csvCall, bool $quotedOnly = false): array
    {
        if ($quotedOnly) {
            preg_match_all("/'([^']+)'/", $csvCall, $matches);

            return $matches[1];
        }

        // The header is quoted literals; the row is `$o->attribute` access. Both
        // have to be recognised or the row looks empty. Only the bare attribute
        // name is kept, so a row entry and its header label can be compared.
        preg_match_all("/'([^']+)'|\\\$o->(\w+)/", $csvCall, $matches, PREG_SET_ORDER);

        return array_map(static fn (array $m): string => $m[1] !== '' ? $m[1] : $m[2], $matches);
    }
}
