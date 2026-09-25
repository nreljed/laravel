<?php

namespace Nreljed\ShopModule\Tests;

use Illuminate\Auth\GenericUser;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard as AuthGuard;
use Illuminate\Container\Container;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\DatabasePresenceVerifier;
use Illuminate\Validation\Factory as ValidationFactory;
use Illuminate\Validation\Validator;
use Nreljed\ShopModule\Models\Cart;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductPrice;
use Nreljed\ShopModule\Models\ProductVariation;
use Nreljed\ShopModule\Observers\SyncsPriceLedger;
use Nreljed\ShopModule\Pipelines\Cart\ResolveUnitPrice;
use Nreljed\ShopModule\Pipelines\CartContext;
use Nreljed\ShopModule\Requests\StoreProductRequest;
use Nreljed\ShopModule\Services\CustomerPricingContext;
use Nreljed\ShopModule\Services\CurrencyService;
use Nreljed\ShopModule\Services\PriceLedgerService;
use Nreljed\ShopModule\Services\PriceResolver;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for PR 10C (customer-group pricing) and the ledger-sync fix
 * that made admin price edits reach the storefront.
 *
 * DB-free like ProductFieldsContractTest: an in-memory connection resolver lets
 * Eloquent run real SQL, and the two `product_prices` migrations are executed
 * for real against SQLite. The supporting tables are hand-rolled to keep the
 * fixture small and independent of the full product/customer schema.
 */
final class CustomerGroupPricingContractTest extends TestCase
{
    private \Illuminate\Database\Connection $connection;

    private Container $container;

    private static ?EventDispatcher $dispatcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container;
        $this->container->instance('app', $this->container);
        $this->container->instance(Container::class, $this->container);

        self::$dispatcher = new EventDispatcher($this->container);
        $this->container->instance('events', self::$dispatcher);
        Model::setEventDispatcher(self::$dispatcher);

        $this->container->instance('config', new Repository([
            'database' => [
                'default' => 'sqlite',
                'connections' => [
                    'sqlite' => [
                        'driver' => 'sqlite',
                        'database' => ':memory:',
                        'prefix' => '',
                        'foreign_key_constraints' => true,
                    ],
                ],
            ],
        ]));

        $factory = new ConnectionFactory($this->container);
        $manager = new DatabaseManager($this->container, $factory);

        $this->container->instance('db', $manager);
        $this->container->instance('db.schema', $manager->connection()->getSchemaBuilder());
        $this->container->alias('db', ConnectionResolverInterface::class);
        $this->container->alias('db', DatabaseManager::class);

        Model::setConnectionResolver($manager);

        // `Product::creating` asks the auth factory who is signed in; these
        // tests act as a signed-out system context.
        $this->container->instance(AuthFactory::class, new NullAuth);

        Container::setInstance($this->container);

        // Each test gets its own in-memory database; without this the DB and
        // Schema facades would keep handing out the first test's manager.
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->container);

        $this->connection = $manager->connection();

        // The real CurrencyService needs the settings service and a cache
        // store; the ledger only ever asks it for the default currency code.
        $this->container->instance(CurrencyService::class, new class extends CurrencyService
        {
            public function __construct() {}

            public function defaultCode(): string
            {
                return 'EUR';
            }
        });

        $this->buildSchema();
        $this->runPriceMigrations();

        Product::observe(SyncsPriceLedger::class);
        ProductVariation::observe(SyncsPriceLedger::class);
    }

    /**
     * The request-level tests below reach the real `ProductField` model (the
     * product form's `after()` hook reads the field catalog). Booting a model
     * is recorded in a static, so the next test class would inherit a
     * `ProductField` that is already "booted" against *this* class's event
     * dispatcher — and would never register its own `creating` hooks (the
     * slug derivation ProductFieldsContractTest relies on). Clearing the
     * booted state keeps the suite order-independent.
     */
    protected function tearDown(): void
    {
        Model::clearBootedModels();

        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Ledger sync (the price bug)
    |--------------------------------------------------------------------------
    */

    public function test_creating_a_product_writes_its_public_ledger_row(): void
    {
        $product = $this->makeProduct(['price' => 899, 'compare_price' => 999]);

        $row = ProductPrice::query()->where('product_id', $product->id)->first();

        $this->assertNotNull($row);
        $this->assertSame('EUR', $row->currency);
        $this->assertSame(899.0, $row->price);
        $this->assertSame(999.0, $row->compare_price);
        $this->assertNull($row->sale_price);
        $this->assertNull($row->customer_group_id);
    }

    public function test_updating_a_product_price_keeps_the_ledger_in_step(): void
    {
        $product = $this->makeProduct(['price' => 899]);

        $product->update(['price' => 949]);

        $this->assertSame(949.0, (float) ProductPrice::query()->where('product_id', $product->id)->value('price'));
    }

    public function test_a_price_edit_does_not_leave_a_stale_ledger_row_behind(): void
    {
        $product = $this->makeProduct(['price' => 899, 'compare_price' => 999]);

        $product->update(['price' => 949, 'compare_price' => null, 'sale_price' => 849]);

        $row = ProductPrice::query()->where('product_id', $product->id)->first();

        $this->assertSame(949.0, $row->price);
        $this->assertNull($row->compare_price);
        $this->assertSame(849.0, $row->sale_price);
    }

    public function test_edits_that_do_not_touch_a_price_leave_the_ledger_untouched(): void
    {
        $product = $this->makeProduct(['price' => 899]);

        // Drift the ledger by hand, then save something unrelated: the observer
        // must not "repair" the row it has no reason to look at.
        ProductPrice::query()->where('product_id', $product->id)->update(['price' => 1]);

        $product->update(['name' => 'Renamed wallet']);

        $this->assertSame(1.0, (float) ProductPrice::query()->where('product_id', $product->id)->value('price'));
    }

    public function test_variation_prices_are_mirrored_into_their_own_ledger_rows(): void
    {
        $product = $this->makeProduct(['price' => 899]);

        $variation = ProductVariation::query()->create([
            'product_id' => $product->id,
            'price' => 799,
            'compare_price' => null,
            'sale_price' => null,
            'active_price' => 799,
        ]);

        $row = ProductPrice::query()->where('product_variation_id', $variation->id)->first();

        $this->assertNotNull($row);
        $this->assertSame(799.0, $row->price);
        $this->assertSame($product->id, $row->product_id);

        $variation->update(['price' => 749]);

        $this->assertSame(749.0, (float) ProductPrice::query()->where('product_variation_id', $variation->id)->value('price'));
    }

    public function test_drifted_rows_report_ledger_prices_that_disagree_with_the_product(): void
    {
        $product = $this->makeProduct(['price' => 899]);
        ProductPrice::query()->where('product_id', $product->id)->update(['price' => 1]);

        $drifted = $this->ledger()->driftedRows();

        $this->assertCount(1, $drifted);
        $this->assertSame($product->id, $drifted[0]['product_id']);
        $this->assertSame(1.0, $drifted[0]['ledger_price']);
        $this->assertSame(899.0, $drifted[0]['product_price']);

        $product->update(['price' => 949]);

        $this->assertCount(0, $this->ledger()->driftedRows());
    }

    public function test_forget_removes_group_overrides_along_with_the_public_row(): void
    {
        $product = $this->makeProduct(['price' => 899]);
        $groupId = $this->makeGroup('wholesale', true);

        $this->ledger()->syncGroupRows($product, [['customer_group_id' => $groupId, 'price' => 700]]);

        $this->assertSame(2, ProductPrice::query()->where('product_id', $product->id)->count());

        $this->ledger()->forget($product);

        $this->assertSame(0, ProductPrice::query()->where('product_id', $product->id)->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Group row authoring
    |--------------------------------------------------------------------------
    */

    public function test_sync_group_rows_upserts_by_group(): void
    {
        $product = $this->makeProduct(['price' => 899]);
        $groupId = $this->makeGroup('wholesale', true);

        $this->ledger()->syncGroupRows($product, [['customer_group_id' => $groupId, 'price' => 700]]);
        $this->ledger()->syncGroupRows($product, [['customer_group_id' => $groupId, 'price' => 650]]);

        $rows = ProductPrice::query()
            ->where('product_id', $product->id)
            ->where('customer_group_id', $groupId)
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame(650.0, $rows->first()->price);
    }

    public function test_sync_group_rows_deletes_groups_that_were_removed_from_the_form(): void
    {
        $product = $this->makeProduct(['price' => 899]);
        $wholesale = $this->makeGroup('wholesale', true);
        $partner = $this->makeGroup('partner', true);

        $this->ledger()->syncGroupRows($product, [
            ['customer_group_id' => $wholesale, 'price' => 700],
            ['customer_group_id' => $partner, 'price' => 750],
        ]);

        $this->ledger()->syncGroupRows($product, [
            ['customer_group_id' => $wholesale, 'price' => 700],
        ]);

        $this->assertSame(1, ProductPrice::query()->where('product_id', $product->id)->whereNotNull('customer_group_id')->count());
    }

    public function test_sync_group_rows_preserves_the_public_row(): void
    {
        $product = $this->makeProduct(['price' => 899]);
        $groupId = $this->makeGroup('wholesale', true);

        $this->ledger()->syncGroupRows($product, [['customer_group_id' => $groupId, 'price' => 700]]);

        $this->assertSame(899.0, (float) ProductPrice::query()->where('product_id', $product->id)->whereNull('customer_group_id')->value('price'));
    }

    public function test_group_rows_for_the_form_exclude_the_public_row(): void
    {
        $product = $this->makeProduct(['price' => 899]);
        $groupId = $this->makeGroup('wholesale', true);

        $this->ledger()->syncGroupRows($product, [['customer_group_id' => $groupId, 'price' => 700, 'compare_price' => 800]]);

        $rows = $this->ledger()->groupRowsFor($product);

        $this->assertCount(1, $rows);
        $this->assertSame($groupId, $rows[0]['customer_group_id']);
        $this->assertSame(700.0, $rows[0]['price']);
        $this->assertSame(800.0, $rows[0]['compare_price']);
    }

    public function test_sync_group_rows_persist_a_sale_price(): void
    {
        $product = $this->makeProduct(['price' => 899]);
        $groupId = $this->makeGroup('wholesale', true);

        $this->ledger()->syncGroupRows($product, [[
            'customer_group_id' => $groupId,
            'price' => 700,
            'sale_price' => 650,
            'compare_price' => 899,
        ]]);

        $row = ProductPrice::query()
            ->where('product_id', $product->id)
            ->where('customer_group_id', $groupId)
            ->first();

        $this->assertSame(700.0, (float) $row->price);
        $this->assertSame(650.0, (float) $row->sale_price);
        $this->assertSame(899.0, (float) $row->compare_price);
    }

    public function test_resaving_a_group_row_replaces_its_sale_price(): void
    {
        $product = $this->makeProduct(['price' => 899]);
        $groupId = $this->makeGroup('wholesale', true);

        $this->ledger()->syncGroupRows($product, [
            ['customer_group_id' => $groupId, 'price' => 700, 'sale_price' => 650],
        ]);
        $this->ledger()->syncGroupRows($product, [
            ['customer_group_id' => $groupId, 'price' => 700, 'sale_price' => 675],
        ]);

        $rows = ProductPrice::query()
            ->where('product_id', $product->id)
            ->where('customer_group_id', $groupId)
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame(675.0, (float) $rows->first()->sale_price);
    }

    /**
     * The product form reads its group rows back through `groupRowsFor()` and
     * re-submits them, so a sale price that does not survive this round-trip
     * would be silently dropped on the next unrelated product edit.
     */
    public function test_group_rows_for_the_form_carry_the_sale_price_back(): void
    {
        $product = $this->makeProduct(['price' => 899]);
        $groupId = $this->makeGroup('wholesale', true);

        $this->ledger()->syncGroupRows($product, [[
            'customer_group_id' => $groupId,
            'price' => 700,
            'sale_price' => 650,
            'compare_price' => 899,
            'min_quantity' => 2,
        ]]);

        $rows = $this->ledger()->groupRowsFor($product);

        $this->assertCount(1, $rows);
        $this->assertSame(650.0, $rows[0]['sale_price']);
        $this->assertSame(2, $rows[0]['min_quantity']);
    }

    public function test_a_group_row_with_a_sale_price_survives_a_form_round_trip(): void
    {
        $product = $this->makeProduct(['price' => 899]);
        $groupId = $this->makeGroup('wholesale', true);

        $this->ledger()->syncGroupRows($product, [[
            'customer_group_id' => $groupId,
            'price' => 700,
            'sale_price' => 650,
        ]]);

        // What the form does: read the rows, hand them straight back.
        $this->ledger()->syncGroupRows($product, $this->ledger()->groupRowsFor($product));

        $row = ProductPrice::query()
            ->where('product_id', $product->id)
            ->where('customer_group_id', $groupId)
            ->first();

        $this->assertSame(700.0, (float) $row->price);
        $this->assertSame(650.0, (float) $row->sale_price);
    }

    public function test_the_product_form_accepts_a_group_price_with_a_sale_price(): void
    {
        $groupId = $this->makeGroup('wholesale', true);

        $validator = $this->validateProductRequest([
            'group_prices' => [[
                'customer_group_id' => $groupId,
                'price' => '700',
                'sale_price' => '650',
                'compare_price' => '899',
                'min_quantity' => '1',
            ]],
        ]);

        $this->assertFalse($validator->fails(), json_encode($validator->errors()->all()));
    }

    public function test_the_product_form_rejects_two_rows_for_the_same_group(): void
    {
        $groupId = $this->makeGroup('wholesale', true);

        $validator = $this->validateProductRequest([
            'group_prices' => [
                ['customer_group_id' => $groupId, 'price' => '700'],
                ['customer_group_id' => $groupId, 'price' => '750'],
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('group_prices.1.customer_group_id', $validator->errors()->toArray());
    }

    public function test_the_product_form_rejects_a_negative_group_price(): void
    {
        $groupId = $this->makeGroup('wholesale', true);

        $validator = $this->validateProductRequest([
            'group_prices' => [['customer_group_id' => $groupId, 'price' => '-5']],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('group_prices.0.price', $validator->errors()->toArray());
    }

    public function test_the_product_form_rejects_a_group_without_business_pricing(): void
    {
        $retailOnly = $this->makeGroup('retail', false);

        $validator = $this->validateProductRequest([
            'group_prices' => [['customer_group_id' => $retailOnly, 'price' => '700']],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString(
            'does not allow business pricing',
            $validator->errors()->get('group_prices.0.customer_group_id')[0]
        );
    }

    public function test_the_product_form_rejects_a_group_price_for_an_unknown_group(): void
    {
        $validator = $this->validateProductRequest([
            'group_prices' => [['customer_group_id' => 4242, 'price' => '700']],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('group_prices.0.customer_group_id', $validator->errors()->toArray());
    }

    public function test_a_price_edit_does_not_clobber_a_group_override(): void
    {
        $product = $this->makeProduct(['price' => 899]);
        $groupId = $this->makeGroup('wholesale', true);

        $this->ledger()->syncGroupRows($product, [['customer_group_id' => $groupId, 'price' => 700]]);

        $product->update(['price' => 949]);

        $this->assertSame(700.0, (float) ProductPrice::query()->where('product_id', $product->id)->where('customer_group_id', $groupId)->value('price'));
    }

    /*
    |--------------------------------------------------------------------------
    | Resolution precedence
    |--------------------------------------------------------------------------
    */

    public function test_a_group_row_wins_over_the_public_row(): void
    {
        $product = $this->makeProduct(['price' => 899]);
        $groupId = $this->makeGroup('wholesale', true);
        $this->addGroupRow($product, $groupId, 700);

        $resolved = $this->resolver()->resolve($product, 'EUR', $groupId);

        $this->assertSame(700.0, $resolved['price']);
        $this->assertSame('group_product_prices', $resolved['source']);
    }

    public function test_a_group_row_in_the_requested_currency_beats_one_in_the_default_currency(): void
    {
        $product = $this->makeProduct(['price' => 899]);
        $groupId = $this->makeGroup('wholesale', true);
        $this->addGroupRow($product, $groupId, 700, 'EUR');
        $this->addGroupRow($product, $groupId, 650, 'USD');

        $resolved = $this->resolver()->resolve($product, 'USD', $groupId);

        $this->assertSame(650.0, $resolved['price']);
        $this->assertSame('USD', $resolved['currency']);
    }

    public function test_a_group_row_falls_back_to_the_default_currency(): void
    {
        $product = $this->makeProduct(['price' => 899]);
        $groupId = $this->makeGroup('wholesale', true);
        $this->addGroupRow($product, $groupId, 700, 'EUR');

        $resolved = $this->resolver()->resolve($product, 'USD', $groupId);

        $this->assertSame(700.0, $resolved['price']);
        $this->assertSame('group_default_product_prices', $resolved['source']);
    }

    public function test_the_public_row_is_used_when_the_group_has_no_override(): void
    {
        $product = $this->makeProduct(['price' => 899]);
        $groupId = $this->makeGroup('wholesale', true);

        $resolved = $this->resolver()->resolve($product, 'EUR', $groupId);

        $this->assertSame(899.0, $resolved['price']);
        $this->assertSame('product_prices', $resolved['source']);
    }

    public function test_another_groups_override_never_leaks(): void
    {
        $product = $this->makeProduct(['price' => 899]);
        $wholesale = $this->makeGroup('wholesale', true);
        $partner = $this->makeGroup('partner', true);
        $this->addGroupRow($product, $wholesale, 700);

        $resolved = $this->resolver()->resolve($product, 'EUR', $partner);

        $this->assertSame(899.0, $resolved['price']);
        $this->assertSame('product_prices', $resolved['source']);
    }

    public function test_guests_always_get_the_public_price(): void
    {
        $product = $this->makeProduct(['price' => 899]);
        $groupId = $this->makeGroup('wholesale', true);
        $this->addGroupRow($product, $groupId, 700);

        $resolved = $this->resolver()->resolve($product, 'EUR', null);

        $this->assertSame(899.0, $resolved['price']);
    }

    public function test_a_public_price_in_another_currency_beats_a_group_row_in_the_default_currency(): void
    {
        $product = $this->makeProduct(['price' => 899]);
        $groupId = $this->makeGroup('wholesale', true);
        $this->addGroupRow($product, $groupId, 700, 'EUR');
        $this->addGroupRow($product, null, 800, 'USD');

        $resolved = $this->resolver()->resolve($product, 'USD', $groupId);

        $this->assertSame(800.0, $resolved['price']);
        $this->assertSame('product_prices', $resolved['source']);
    }

    public function test_resolution_falls_back_to_legacy_columns_when_the_ledger_is_empty(): void
    {
        $product = $this->makeProduct(['price' => 899]);
        ProductPrice::query()->where('product_id', $product->id)->delete();

        $resolved = $this->resolver()->resolve($product, 'EUR');

        $this->assertSame(899.0, $resolved['price']);
        $this->assertSame('legacy', $resolved['source']);
    }

    public function test_hydrate_applies_a_group_override_to_the_serialized_price(): void
    {
        $product = $this->makeProduct(['price' => 899]);
        $groupId = $this->makeGroup('wholesale', true);
        $this->addGroupRow($product, $groupId, 700);

        $this->resolver()->hydrate($product, 'EUR', $groupId);

        $this->assertSame(700.0, (float) $product->price);
        $this->assertSame(700.0, (float) $product->active_price);
    }

    public function test_the_active_price_prefers_an_active_group_sale(): void
    {
        $product = $this->makeProduct(['price' => 899]);
        $groupId = $this->makeGroup('wholesale', true);
        $this->addGroupRow($product, $groupId, 700, 'EUR', ['sale_price' => 650]);

        $this->assertSame(650.0, $this->resolver()->activePrice($product, 'EUR', $groupId));
    }

    /*
    |--------------------------------------------------------------------------
    | Cart pricing
    |--------------------------------------------------------------------------
    */

    public function test_the_cart_charges_the_group_price(): void
    {
        $product = $this->makeProduct(['price' => 899]);
        $groupId = $this->makeGroup('wholesale', true);
        $this->addGroupRow($product, $groupId, 700);

        $this->assertSame(700.0, $this->runCartPipeline($product, $groupId)->unitPrice);
    }

    public function test_a_guest_cart_is_still_charged_the_public_price(): void
    {
        $product = $this->makeProduct(['price' => 899]);
        $groupId = $this->makeGroup('wholesale', true);
        $this->addGroupRow($product, $groupId, 700);

        $this->assertSame(899.0, $this->runCartPipeline($product, null)->unitPrice);
    }

    public function test_the_cart_charges_an_active_group_sale(): void
    {
        $product = $this->makeProduct(['price' => 899]);
        $groupId = $this->makeGroup('wholesale', true);
        $this->addGroupRow($product, $groupId, 700, 'EUR', ['sale_price' => 650]);

        $this->assertSame(650.0, $this->runCartPipeline($product, $groupId)->unitPrice);
    }

    /*
    |--------------------------------------------------------------------------
    | Group deletion
    |--------------------------------------------------------------------------
    */

    /**
     * Deleting a group must remove its overrides, not demote them to the
     * public row: a group row demoted to `customer_group_id = null` would
     * collapse onto the public row's `group_scope` and trip the unique key,
     * which would make the group undeletable in the admin.
     */
    public function test_deleting_a_group_removes_its_price_overrides(): void
    {
        $product = $this->makeProduct(['price' => 899]);
        $groupId = $this->makeGroup('wholesale', true);
        $this->addGroupRow($product, $groupId, 700);

        $this->connection->table('customer_groups')->where('id', $groupId)->delete();

        $this->assertSame(0, $this->connection->table('product_prices')
            ->where('customer_group_id', $groupId)->count());

        // The public row is untouched, and it still prices the product.
        $this->assertSame(899.0, $this->resolver()->resolve($product, 'EUR', $groupId)['price']);
    }

    /*
    |--------------------------------------------------------------------------
    | Runtime group resolution
    |--------------------------------------------------------------------------
    */

    public function test_a_user_in_a_business_group_resolves_to_that_group(): void
    {
        $groupId = $this->makeGroup('wholesale', true);
        $this->linkUserToGroup(7, $groupId);

        $this->assertSame($groupId, (new CustomerPricingContext)->groupId(new GenericUser(['id' => 7])));
    }

    public function test_a_group_without_business_pricing_is_never_resolved(): void
    {
        $groupId = $this->makeGroup('retail', false);
        $this->linkUserToGroup(7, $groupId);

        $this->assertNull((new CustomerPricingContext)->groupId(new GenericUser(['id' => 7])));
    }

    public function test_a_user_without_a_customer_record_resolves_to_no_group(): void
    {
        $this->assertNull((new CustomerPricingContext)->groupId(new GenericUser(['id' => 99])));
    }

    public function test_priceable_groups_exclude_groups_without_business_pricing(): void
    {
        $this->makeGroup('retail', false);
        $this->makeGroup('wholesale', true);

        $labels = (new CustomerPricingContext)->priceableGroups()->pluck('label')->all();

        $this->assertSame(['Wholesale'], $labels);
    }

    public function test_the_group_is_resolved_once_per_context(): void
    {
        $groupId = $this->makeGroup('wholesale', true);
        $this->linkUserToGroup(7, $groupId);

        $context = new CustomerPricingContext;
        $this->assertSame($groupId, $context->groupId(new GenericUser(['id' => 7])));

        $this->connection->table('customer_user')->delete();
        $this->assertSame($groupId, $context->groupId(new GenericUser(['id' => 7])));
    }

    /*
    |--------------------------------------------------------------------------
    | Schema
    |--------------------------------------------------------------------------
    */

    public function test_the_migration_adds_the_group_dimension_to_the_ledger(): void
    {
        $schema = $this->connection->getSchemaBuilder();

        $this->assertTrue($schema->hasColumns('product_prices', ['customer_group_id', 'group_scope']));
    }

    public function test_one_group_row_per_group_currency_and_quantity_is_enforced(): void
    {
        $product = $this->makeProduct(['price' => 899]);
        $groupId = $this->makeGroup('wholesale', true);
        $this->addGroupRow($product, $groupId, 700);

        $this->expectException(QueryException::class);

        $this->addGroupRow($product, $groupId, 650);
    }

    public function test_the_public_row_and_a_group_row_can_coexist_for_one_product(): void
    {
        $product = $this->makeProduct(['price' => 899]);
        $groupId = $this->makeGroup('wholesale', true);

        $this->addGroupRow($product, $groupId, 700);

        $this->assertSame(2, ProductPrice::query()->where('product_id', $product->id)->count());
    }

    public function test_duplicate_public_rows_are_still_rejected(): void
    {
        $product = $this->makeProduct(['price' => 899]);

        $this->expectException(QueryException::class);

        $this->addGroupRow($product, null, 850);
    }

    /*
    |--------------------------------------------------------------------------
    | Fixtures
    |--------------------------------------------------------------------------
    */

    protected function ledger(): PriceLedgerService
    {
        return $this->container->make(PriceLedgerService::class);
    }

    protected function resolver(): PriceResolver
    {
        return new PriceResolver($this->container->make(CurrencyService::class));
    }

    /**
     * Run the cart pricing pipe for a shopper resolving to $groupId (null =
     * guest). Only `ResolveUnitPrice` is exercised: the rest of the chain
     * needs persisted carts, which this minimal schema does not have.
     */
    protected function runCartPipeline(Product $product, ?int $groupId): CartContext
    {
        $pricingContext = new class($groupId) extends CustomerPricingContext
        {
            public function __construct(private ?int $groupId) {}

            public function groupId(?Authenticatable $user = null): ?int
            {
                return $this->groupId;
            }
        };

        $context = new CartContext(
            cart: new Cart,
            product: $product,
            variation: null,
            quantity: 1,
            existingItem: null,
            variantLabel: null,
            totalQuantity: 1,
        );

        $pipe = new ResolveUnitPrice($this->resolver(), $pricingContext);

        return $pipe->handle($context, fn (CartContext $passed) => $passed);
    }

    /**
     * Run the real `StoreProductRequest` rules — including its `after()` hooks,
     * which are what enforce the business-pricing gate — over a product payload.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function validateProductRequest(array $overrides = []): Validator
    {
        $payload = array_merge([
            'name' => 'Xiaomi 14 Pro',
            'slug' => 'xiaomi-14-pro',
            'type' => 'simple',
            'status' => 'published',
            'price' => '899',
            'stock_quantity' => '50',
            'weight' => '1',
        ], $overrides);

        $request = StoreProductRequest::create('/dashboard/shop/products/1', 'PUT', $payload);
        $request->setContainer($this->container);

        $translator = new Translator(new ArrayLoader, 'en');
        $factory = new ValidationFactory($translator, $this->container);
        $factory->setPresenceVerifier(new DatabasePresenceVerifier($this->container->make('db')));

        $validator = $factory->make($payload, $request->rules());

        // Mirrors what FormRequest does with `after()` during a real request:
        // the callbacks are registered as a group, not invoked here.
        $validator->after($this->container->call(
            $request->after(...),
            ['validator' => $validator]
        ));

        return $validator;
    }

    protected function makeProduct(array $attributes = []): Product
    {
        static $sequence = 0;
        $sequence++;

        return Product::query()->create(array_merge([
            'name' => 'Test product '.$sequence,
            'slug' => 'test-product-'.$sequence,
            'price' => 899,
            'compare_price' => null,
            'sale_price' => null,
            'type' => 'simple',
        ], $attributes));
    }

    protected function makeGroup(string $code, bool $allowsBusinessPricing): int
    {
        return (int) $this->connection->table('customer_groups')->insertGetId([
            'code' => $code,
            'label' => ucfirst($code),
            'allows_business_pricing' => $allowsBusinessPricing,
        ]);
    }

    protected function addGroupRow(
        Product $product,
        ?int $groupId,
        float $price,
        string $currency = 'EUR',
        array $overrides = [],
    ): void {
        $this->connection->table('product_prices')->insert(array_merge([
            'product_id' => $product->id,
            'product_variation_id' => null,
            'customer_group_id' => $groupId,
            'currency' => $currency,
            'price' => $price,
            'compare_price' => null,
            'sale_price' => null,
            'min_quantity' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    protected function linkUserToGroup(int $userId, int $groupId): void
    {
        $this->connection->table('users')->insert([
            'id' => $userId,
            'name' => 'User '.$userId,
            'email' => 'user'.$userId.'@example.test',
        ]);

        $customerId = (int) $this->connection->table('customers')->insertGetId([
            'code' => 'CUST'.$userId,
            'email' => 'customer'.$userId.'@example.test',
            'customer_group_id' => $groupId,
        ]);

        $this->connection->table('customer_user')->insert([
            'customer_id' => $customerId,
            'user_id' => $userId,
        ]);
    }

    protected function buildSchema(): void
    {
        $schema = $this->connection->getSchemaBuilder();

        $schema->create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name');
            $table->string('symbol', 8)->default('€');
            $table->decimal('exchange_rate', 18, 8)->default(1);
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
        });

        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
        });

        $schema->create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->default('simple');
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('compare_price', 12, 2)->nullable();
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->timestamp('sale_from')->nullable();
            $table->timestamp('sale_to')->nullable();
            // Written by Product::syncPriceRange(), which ProductVariation's
            // `saved` hook calls. They belong to the real schema, so the fixture
            // carries them.
            $table->decimal('min_variation_price', 12, 2)->nullable();
            $table->decimal('max_variation_price', 12, 2)->nullable();
            $table->string('price_hash')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('product_variations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id');
            $table->boolean('visible')->default(true);
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('compare_price', 12, 2)->nullable();
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->decimal('active_price', 12, 2)->nullable();
            $table->timestamp('sale_from')->nullable();
            $table->timestamp('sale_to')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('label');
            $table->boolean('allows_business_pricing')->default(false);
        });

        $schema->create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('email')->unique();
            $table->string('display_name')->nullable();
            $table->foreignId('customer_group_id')->nullable();
        });

        $schema->create('customer_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id');
            $table->foreignId('user_id');
        });

        // `StoreProductRequest::after()` always reads the product-field catalog
        // before it looks at the submitted values, so the request-level tests
        // need the table to exist even though they send no field values.
        $schema->create('product_fields', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->default('text');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_required')->default(false);
            $table->boolean('show_on_storefront')->default(false);
            $table->text('options')->nullable();
            $table->string('default_value')->nullable();
        });

        $this->connection->table('currencies')->insert([
            'code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'is_active' => true, 'is_default' => true,
        ]);
        $this->connection->table('currencies')->insert([
            'code' => 'USD', 'name' => 'Dollar', 'symbol' => '$', 'is_active' => true, 'is_default' => false,
        ]);
    }

    protected function runPriceMigrations(): void
    {
        $create = require __DIR__.'/../database/migrations/2026_09_22_000000_create_product_prices_table.php';
        $create->up();

        $grouped = require __DIR__.'/../database/migrations/2026_09_26_000200_add_customer_group_id_to_product_prices_table.php';
        $grouped->up();
    }
}

/**
 * Signed-out auth factory/guard so model hooks that ask "who is signed in?"
 * can run in a DB-free test container.
 */
final class NullAuth implements AuthFactory, AuthGuard
{
    public function guard($name = null): AuthGuard
    {
        return $this;
    }

    public function shouldUse($name): void {}

    public function check(): bool
    {
        return false;
    }

    public function guest(): bool
    {
        return true;
    }

    public function user(): ?Authenticatable
    {
        return null;
    }

    public function id(): mixed
    {
        return null;
    }

    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function hasUser(): bool
    {
        return false;
    }

    public function setUser(Authenticatable $user): void {}
}
