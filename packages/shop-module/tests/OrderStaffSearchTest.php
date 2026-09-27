<?php

namespace Nreljed\ShopModule\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Nreljed\Platform\Models\User;
use Nreljed\ShopModule\Http\Controllers\OrdersController;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Tests\Concerns\BootsShopSchema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * The staff lookup pickers on the order screen.
 *
 * Two problems, both on routes the `shop.manage` group already gated, so the
 * group was never the fix:
 *
 *  - Neither picker authorised. Every sibling action in `OrdersController` calls
 *    `$this->authorize()`, and `searchCustomers` hands back names and email
 *    addresses, so a role that reached the group without a policy check on the
 *    action could read the user list through a select box.
 *  - The search term went into `LIKE` unescaped, so `?q=%` matched every row and
 *    returned the first twenty customers. Stripping the wildcards fixed the
 *    crafted case and left the trivial one wide open: an absent or empty `q`
 *    still produced a bare `%`, and so did a term made up of nothing but
 *    wildcards once they were stripped.
 *
 * Both pickers are lookup fields for attaching one specific line or customer,
 * which is why "nothing to search for" returns nothing rather than a list.
 */
final class OrderStaffSearchTest extends TestCase
{
    use BootsShopSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootShopSchema();
        $this->resetShopTables();
        $this->ensureUserColumnsExist();
    }

    /**
     * The shared harness stubs `users` with only `id` and `timestamps`, because
     * the consuming app owns that table and the package's own migrations only
     * ever reference it through foreign keys.
     *
     * `searchCustomers` genuinely reads `name` and `email`, so the columns are
     * added here to let the query run for real. Both are nullable and additive,
     * so a sibling test class inserting into the stub is unaffected - and the
     * check makes the migration a no-op on every test after the first.
     */
    private function ensureUserColumnsExist(): void
    {
        if (! Schema::hasColumn('users', 'name')) {
            Schema::table('users', function ($table) {
                $table->string('name')->nullable();
                $table->string('email')->nullable();
            });
        }
    }

    // ---------------------------------------------------------------------
    // Wildcards cannot become a match-everything pattern.
    // ---------------------------------------------------------------------

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function wildcardOnlyTerms(): iterable
    {
        yield 'absent'        => [''];
        yield 'empty'         => ['   '];
        yield 'percent'       => ['%'];
        yield 'underscore'    => ['__'];
        yield 'backslash'     => ['\\'];
        yield 'percent mix'   => ['%%%'];
        yield 'mixed junk'    => ['%_%\\%'];
        yield 'over length'   => [str_repeat('%', 120)];
    }

    #[DataProvider('wildcardOnlyTerms')]
    public function test_a_term_with_nothing_searchable_in_it_yields_no_pattern(string $q): void
    {
        $this->assertNull(
            $this->searchTermFor($q),
            'A term that is empty, or only wildcards, must not fall back to a bare "%" - that matches every row and turns the customer picker into a directory of names and emails.'
        );
    }

    public function test_a_real_term_is_wrapped_for_substring_matching(): void
    {
        $this->assertSame('%mug%', $this->searchTermFor('mug'));
    }

    public function test_a_term_keeps_the_text_around_a_literal_wildcard(): void
    {
        // "50%" is a real query. Stripping must not reduce it to nothing.
        $this->assertSame('%50%', $this->searchTermFor('50%'));
    }

    public function test_the_term_is_capped_in_length(): void
    {
        $term = $this->searchTermFor(str_repeat('a', 500));

        $this->assertSame(102, mb_strlen((string) $term), 'Expected 100 characters wrapped in the two wildcards.');
    }

    // ---------------------------------------------------------------------
    // Nothing to search for, nothing to show.
    // ---------------------------------------------------------------------

    public function test_the_customer_picker_returns_nothing_rather_than_the_first_page(): void
    {
        $this->customerRow('Ada Lovelace', 'ada@example.test');
        $this->customerRow('Grace Hopper', 'grace@example.test');

        $results = $this->invoke('searchCustomers', Request::create('/', 'GET', ['q' => '%']));

        $this->assertSame([], $results, '?q=% returned the user list.');
    }

    public function test_the_product_picker_returns_nothing_rather_than_the_first_page(): void
    {
        $this->productRow('Kettle');
        $this->productRow('Mug');

        $results = $this->invoke('searchProducts', Request::create('/', 'GET', ['q' => '_']));

        $this->assertSame([], $results, '?q=_ returned the product list.');
    }

    public function test_a_genuine_term_still_searches(): void
    {
        $this->customerRow('Ada Lovelace', 'ada@example.test');
        $this->customerRow('Grace Hopper', 'grace@example.test');

        $results = $this->invoke('searchCustomers', Request::create('/', 'GET', ['q' => 'ada']));

        $this->assertCount(1, $results);
        $this->assertSame('ada@example.test', $results[0]['email']);
    }

    // ---------------------------------------------------------------------
    // Authorisation.
    // ---------------------------------------------------------------------

    /**
     * The pickers authorise through `viewAny` on Order. They are the only two
     * actions in the controller that were reachable on the group gate alone.
     */
    public function test_both_pickers_authorise(): void
    {
        foreach (['searchProducts', 'searchCustomers'] as $action) {
            $this->assertStringContainsString(
                "\$this->authorize('viewAny', Order::class)",
                $this->methodSource($action),
                "{$action}() did not authorise. `searchCustomers` returns names and email addresses."
            );
        }
    }

    public function test_the_customer_picker_still_only_exposes_three_fields(): void
    {
        // A fix for the wildcard hole should not become a reason to widen the
        // projection: `select()` bounds the columns and the map bounds the keys.
        $this->assertStringContainsString("->select('id', 'name', 'email')", $this->methodSource('searchCustomers'));
        $this->assertStringNotContainsString('password', $this->methodSource('searchCustomers'));
    }

    /**
     * The row cap is the other bound on a single request: however the term
     * resolves, one call returns at most this many people.
     */
    public function test_both_pickers_cap_the_rows_they_return(): void
    {
        $this->assertStringContainsString('->limit(20)', $this->methodSource('searchProducts'));
        $this->assertStringContainsString('->limit(20)', $this->methodSource('searchCustomers'));
    }

    public function test_the_cap_is_actually_applied_to_the_query(): void
    {
        // `limit(20)` in the source is only half a claim; the other half is that
        // twenty-one matching customers really do come back as twenty.
        $this->customerRow('Ada Lovelace', 'ada@example.test');
        User::query()->insert(array_map(
            fn (int $i) => ['name' => "Filler {$i}", 'email' => "filler{$i}@example.test"],
            range(1, 25)
        ));

        $results = $this->invoke('searchCustomers', Request::create('/', 'GET', ['q' => 'example.test']));

        $this->assertCount(20, $results, 'The row cap is not reaching the query.');
    }

    // ---------------------------------------------------------------------
    // Helpers.
    // ---------------------------------------------------------------------

    private function searchTermFor(string $q): ?string
    {
        $method = new ReflectionMethod(OrdersController::class, 'searchTerm');
        $method->setAccessible(true);

        return $method->invoke(
            (new ReflectionClass(OrdersController::class))->newInstanceWithoutConstructor(),
            Request::create('/', 'GET', ['q' => $q])
        );
    }

    /**
     * Calls the picker without the HTTP kernel around it.
     *
     * There is no application container in this harness, so `authorize()` cannot
     * resolve a gate. A pass-through stand-in lets the *query* half of these
     * pickers run for real against SQLite, which is what proves the empty-term
     * path returns no rows rather than twenty.
     *
     * @return array<int, array<string, mixed>>
     */
    private function invoke(string $action, Request $request): array
    {
        // An empty constructor overrides the parent's five promoted services:
        // neither picker touches them, and `newInstanceWithoutConstructor()`
        // would not reach the `authorize()` override below on a subclass.
        $controller = new class extends OrdersController
        {
            public function __construct() {}

            public function authorize($ability, $arguments = [])
            {
                // Authorisation is asserted separately, by source.
            }
        };

        return json_decode($controller->{$action}($request)->getContent(), true);
    }

    /**
     * The source of exactly one method.
     *
     * Sliced to the method's own end line, not a fixed window: a window wide
     * enough to hold `searchProducts` also holds all of `searchCustomers`, so
     * an assertion about the first picker would be satisfied by the second
     * one's `authorize()` and pass even with the first one's removed.
     */
    private function methodSource(string $method): string
    {
        $reflection = new ReflectionMethod(OrdersController::class, $method);
        $lines = file($reflection->getFileName());

        return implode("\n", array_slice(
            $lines,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1
        ));
    }

    private function customerRow(string $name, string $email): void
    {
        // Through the query builder, not the model: `User::creating()` stamps a
        // `username` column the stub does not have, and that hook has nothing to
        // do with what the picker reads.
        Schema::getConnection()->table('users')->insert(['name' => $name, 'email' => $email]);
    }

    private function productRow(string $name): void
    {
        Product::create([
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'status' => 'published',
            'type' => 'simple',
            'catalog_visibility' => 'visible',
            'price' => 10,
            'stock_quantity' => 5,
            'track_stock' => true,
        ]);
    }
}
