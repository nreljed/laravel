<?php

namespace Tests\Feature\Modules\Shop;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Models\ShippingMethod;
use Tests\BootsFeatures;
use Tests\TestCase;

/**
 * The orders export has to say which carrier an order went with, checked by
 * reading the bytes the endpoint actually returns.
 *
 * shop-module 0.1.71 added `shipping_method` to this CSV, and the package suite
 * asserts the header and the row at source level - it cannot run the endpoint,
 * because the export is a streamed response and the package harness boots a
 * database rather than an HTTP kernel. Source-level is a weak substitute for the
 * one thing that matters here: that the header labels a column and the row
 * actually fills it. Those can disagree, and a column of blanks looks exactly
 * like a column of values in a diff.
 *
 * So the header index and the row index are compared, and the value is read out
 * of the parsed row rather than checked for presence in the file. "Contains
 * DHL Express somewhere" would also pass if it appeared in an unrelated column.
 *
 * A second, smaller gap lives in the same feature: the admin order page gated
 * its shipping row on `shipping_total > 0`, so every free-shipping order showed
 * no shipping row at all - hiding the method on precisely the orders where an
 * operator most wants to confirm the service, and making a recorded method
 * indistinguishable from none. Free shipping makes that a realistic order, not a
 * corner case, which is why it is asserted rather than left.
 */
class OrderShippingMethodExportTest extends TestCase
{
    use BootsFeatures, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFeatures();
        $this->admin = $this->createUserWithRole('admin');
    }

    public function test_the_export_writes_the_shipping_method_under_its_own_header(): void
    {
        $method = $this->method('DHL Express');
        $this->order('EXP-1', $method, shippingTotal: 12.50);
        $this->order('EXP-2', $this->method('UPS Ground'), shippingTotal: 8.25);

        $csv = $this->export();

        $rows = $this->rows($csv);
        $header = array_map('trim', $rows[0]);

        $this->assertContains('shipping_method', $header, 'The export has no shipping_method column.');

        $column = array_search('shipping_method', $header, true);
        $this->assertIsInt($column, 'shipping_method has no index in the header.');

        $byNumber = [];
        foreach (array_slice($rows, 1) as $row) {
            $byNumber[trim($row[0])] = $row;
        }

        // Read the cell at the header's index rather than searching the row for
        // the value: a method name landing in the wrong column is the exact bug
        // an offset mistake produces, and a substring search would not see it.
        $this->assertSame('DHL Express', trim($byNumber['EXP-1'][$column]));
        $this->assertSame('UPS Ground', trim($byNumber['EXP-2'][$column]));
    }

    public function test_the_export_leaves_the_method_empty_for_an_order_that_predates_the_snapshot(): void
    {
        $this->order('LEGACY-1', null, shippingTotal: 5.00);

        $rows = $this->rows($this->export());
        $header = array_map('trim', $rows[0]);
        $column = array_search('shipping_method', $header, true);

        $row = array_slice($rows, 1)[0];

        $this->assertSame('LEGACY-1', trim($row[0]));
        $this->assertSame('', trim($row[$column]), 'An order with no snapshot must export an empty cell, not a shifted value.');
    }

    public function test_the_export_still_reports_the_shipping_total(): void
    {
        $this->order('TOT-1', $this->method('PostNL'), shippingTotal: 7.40);

        $rows = $this->rows($this->export());
        $header = array_map('trim', $rows[0]);

        $totalColumn = array_search('shipping_total', $header, true);
        $methodColumn = array_search('shipping_method', $header, true);

        $row = array_slice($rows, 1)[0];

        // '7.40', not '7.4': the model casts the column to decimal:2, so the
        // export emits the stored scale. Asserting the shortened form would
        // make this test a false alarm waiting to happen the day that cast
        // changes.
        $this->assertSame('7.40', trim($row[$totalColumn]));
        $this->assertSame('PostNL', trim($row[$methodColumn]));
    }

    public function test_the_admin_order_page_shows_the_method_for_a_free_shipping_order(): void
    {
        $order = $this->order('FREE-1', $this->method('DHL Express'), shippingTotal: 0.0);

        $this->actingAs($this->admin)
            ->get(route('dashboard.shop.orders.show', $order))
            ->assertOk()
            // Inertia, not `viewData()`: the page is a JSON XHR response, so the
            // props have to be read off the serialised page rather than a Blade
            // view's data array.
            ->assertInertia(fn ($page) => $page
                // `false` turns off Inertia's ensure_pages_exist lookup: the
                // page map is assembled in `page-config.ts`, so the component
                // name is not resolvable to a file the way it is for core pages.
                ->component('shop/orders/show', false)
                ->where('order.order_number', 'FREE-1')
                ->where('order.shipping_method_name', 'DHL Express')
                // '0.00' as a string, from the model's decimal:2 cast - the page
                // does `Number(...) > 0` rather than comparing as a string, which
                // is why a free order reaches the branch at all.
                ->where('order.shipping_total', '0.00')
                ->etc(),
            );
    }

    public function test_the_admin_order_page_loads_the_live_method_relation(): void
    {
        $method = $this->method('Flat Rate');
        $order = $this->order('REL-1', $method, shippingTotal: 5.00);

        $method->update(['name' => 'Flat Rate 2026']);

        $this->actingAs($this->admin)
            ->get(route('dashboard.shop.orders.show', $order))
            ->assertOk()
            // Both names reach the page: the snapshot the parcel went with, and
            // what the method is called now. With only one, a rename is
            // indistinguishable from a deletion.
            ->assertInertia(fn ($page) => $page
                ->component('shop/orders/show', false)
                ->where('order.shipping_method_name', 'Flat Rate')
                ->where('order.shipping_method.name', 'Flat Rate 2026')
                ->etc(),
            );
    }

    private function export(): string
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard.shop.orders.export'));

        $response->assertOk();

        return $response->streamedContent();
    }

    /**
     * @return list<list<string>>
     */
    private function rows(string $csv): array
    {
        // `str_getcsv()` parses a single record, so the body has to be split
        // into lines first - handing it the whole stream yields one string where
        // rows are expected. The UTF-8 BOM the export writes is stripped up
        // front, or it prefixes the first header cell and every lookup for
        // "order_number" misses by one character.
        $body = str_replace("\xEF\xBB\xBF", '', $csv);

        $rows = [];
        foreach (preg_split('/\r\n|\r|\n/', $body) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }

            $row = array_map(static fn (?string $cell): string => (string) $cell, str_getcsv($line, ',', '"', '\\'));

            if ($row !== []) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function method(string $name): ShippingMethod
    {
        return ShippingMethod::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(4),
            'cost' => 7.40,
            'is_active' => true,
        ]);
    }

    private function order(string $number, ?ShippingMethod $method, float $shippingTotal): Order
    {
        return Order::create([
            'order_number' => $number,
            'status' => 'pending',
            'subtotal' => 40,
            'shipping_total' => $shippingTotal,
            'tax_total' => 0,
            'discount_total' => 0,
            'total' => 40 + $shippingTotal,
            'customer_name' => 'Export Test',
            'customer_email' => 'export@example.test',
            'shipping_method_id' => $method?->id,
            'shipping_method_name' => $method?->name,
        ]);
    }
}
