<?php

namespace Nreljed\ShopModule\Tests;

use Illuminate\Container\Container;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidationFactory;
use Nreljed\ShopModule\Http\Controllers\ProductFieldsController;
use Nreljed\ShopModule\Http\Controllers\StorefrontController;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductField;
use Nreljed\ShopModule\Models\ProductFieldValue;
use Nreljed\ShopModule\Policies\ProductFieldPolicy;
use Nreljed\ShopModule\Requests\StoreProductFieldRequest;
use Nreljed\ShopModule\Requests\StoreProductRequest;
use Nreljed\ShopModule\ShopServiceProvider;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Contract tests for PR 10B (custom product fields).
 *
 * DB-free like ScheduledPublishingContractTest: an in-memory connection
 * resolver is registered so Eloquent can render SQL and the two additive
 * migrations can run against SQLite, but nothing touches real storage.
 */
final class ProductFieldsContractTest extends TestCase
{
    private SQLiteConnection $connection;

    private static ?EventDispatcher $dispatcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = new SQLiteConnection(new PDO('sqlite::memory:'));

        $resolver = new ConnectionResolver;
        $resolver->addConnection('default', $this->connection);
        $resolver->setDefaultConnection('default');
        Model::setConnectionResolver($resolver);

        $container = new Container;
        $container->instance('db', $resolver);
        $container->instance('db.schema', $this->connection->getSchemaBuilder());

        self::$dispatcher ??= new EventDispatcher;
        $container->instance('events', self::$dispatcher);
        Model::setEventDispatcher(self::$dispatcher);

        Container::setInstance($container);
        Facade::setFacadeApplication($container);
    }

    public function test_product_field_value_relation_targets_the_values_table(): void
    {
        $relation = (new Product)->productFieldValues();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertInstanceOf(ProductFieldValue::class, $relation->getRelated());
        $this->assertSame('product_field_values', $relation->getRelated()->getTable());
        $this->assertSame('product_id', $relation->getForeignKeyName());
        $this->assertSame('id', $relation->getLocalKeyName());
    }

    public function test_active_scope_filters_inactive_fields(): void
    {
        $query = ProductField::query()->active();

        $this->assertStringContainsString('"is_active" = ?', $query->toSql());
        $this->assertSame([true], $query->getBindings());
    }

    public function test_type_catalog_covers_every_supported_input(): void
    {
        $this->assertSame(
            ['text', 'textarea', 'number', 'boolean', 'date', 'select'],
            ProductField::TYPES,
        );
    }

    public function test_option_values_are_extracted_from_option_rows(): void
    {
        $field = new ProductField([
            'options' => [
                ['value' => 'warranty_1y', 'label' => '1 year'],
                ['value' => 'warranty_2y', 'label' => null],
            ],
        ]);

        $this->assertSame(['warranty_1y', 'warranty_2y'], $field->optionValues());
    }

    public function test_option_label_falls_back_to_the_raw_value(): void
    {
        $field = new ProductField(['options' => [['value' => 'black', 'label' => 'Jet Black']]]);

        $this->assertSame('Jet Black', $field->optionLabel('black'));
        $this->assertSame('silver', $field->optionLabel('silver'));
    }

    public function test_normalize_value_trims_text_and_drops_blanks(): void
    {
        $this->assertSame('42', ProductField::normalizeValue('text', '  42  '));
        $this->assertNull(ProductField::normalizeValue('text', '   '));
        $this->assertNull(ProductField::normalizeValue('text', null));
    }

    public function test_normalize_value_coerces_booleans(): void
    {
        $this->assertTrue(ProductField::normalizeValue('boolean', '1'));
        $this->assertFalse(ProductField::normalizeValue('boolean', '0'));
        $this->assertNull(ProductField::normalizeValue('boolean', ''));
    }

    public function test_value_error_rejects_non_numeric_numbers(): void
    {
        $this->assertNull(ProductField::valueError('number', [], '12.5'));
        $this->assertSame('must be a number', ProductField::valueError('number', [], 'twelve'));
    }

    public function test_value_error_requires_iso_dates(): void
    {
        $this->assertNull(ProductField::valueError('date', [], '2026-02-28'));
        $this->assertSame('must be a valid date', ProductField::valueError('date', [], 'not-a-date'));
    }

    public function test_value_error_requires_select_values_to_match_an_option(): void
    {
        $options = ['small', 'large'];

        $this->assertNull(ProductField::valueError('select', $options, 'large'));
        $this->assertSame('must match one of the options', ProductField::valueError('select', $options, 'medium'));
    }

    public function test_formatted_value_renders_booleans_as_yes_or_no(): void
    {
        $this->assertSame('Yes', (new ProductFieldValue(['value' => true]))->formattedValue());
        $this->assertSame('No', (new ProductFieldValue(['value' => false]))->formattedValue());
    }

    public function test_formatted_value_is_null_when_nothing_is_filled_in(): void
    {
        $this->assertNull((new ProductFieldValue(['value' => '']))->formattedValue());
        $this->assertNull((new ProductFieldValue(['value' => null]))->formattedValue());
    }

    public function test_migrations_create_the_product_field_tables(): void
    {
        $this->runProductFieldMigrations();

        $schema = $this->connection->getSchemaBuilder();

        $this->assertTrue($schema->hasTable('product_fields'));
        $this->assertTrue($schema->hasTable('product_field_values'));

        $this->assertTrue($schema->hasColumns('product_fields', [
            'name', 'slug', 'type', 'description', 'options', 'default_value',
            'is_required', 'is_active', 'show_on_storefront', 'sort_order',
        ]));

        $this->assertTrue($schema->hasColumns('product_field_values', [
            'product_id', 'product_field_id', 'value', 'sort_order',
        ]));
    }

    public function test_one_value_per_field_and_product_is_enforced_by_a_unique_index(): void
    {
        $this->runProductFieldMigrations();

        $indexes = $this->connection->getSchemaBuilder()->getIndexes('product_field_values');

        $unique = collect($indexes)->first(
            fn (array $index) => $index['unique'] === true && $index['columns'] === ['product_id', 'product_field_id']
        );

        $this->assertNotNull($unique, 'product_field_values must be unique per product and field.');
    }

    public function test_migrations_roll_back_cleanly(): void
    {
        $this->runProductFieldMigrations();
        $this->rollbackProductFieldMigrations();

        $schema = $this->connection->getSchemaBuilder();

        $this->assertFalse($schema->hasTable('product_field_values'));
        $this->assertFalse($schema->hasTable('product_fields'));
    }

    public function test_admin_routes_expose_the_product_field_manager(): void
    {
        $routes = (string) file_get_contents(__DIR__.'/../src/Routes/web.php');

        $this->assertStringContainsString("ProductFieldsController::class, 'index'", $routes);
        $this->assertStringContainsString("name('product-fields.store')", $routes);
        $this->assertStringContainsString("name('product-fields.update')", $routes);
        $this->assertStringContainsString("name('product-fields.destroy')", $routes);
        $this->assertStringContainsString("name('product-fields.reorder')", $routes);
    }

    public function test_manager_renders_the_dedicated_inertia_page(): void
    {
        $source = (string) file_get_contents((new ReflectionClass(ProductFieldsController::class))->getFileName());

        $this->assertStringContainsString("Inertia::render('shop/productfields/index'", $source);
        $this->assertStringContainsString('withCount', $source);
    }

    public function test_provider_registers_the_product_field_policy(): void
    {
        $source = (string) file_get_contents((new ReflectionClass(ShopServiceProvider::class))->getFileName());

        $this->assertStringContainsString(
            'Gate::policy(ProductField::class, ProductFieldPolicy::class)',
            $source,
        );
        $this->assertStringContainsString('dashboard.shop.product-fields.index', $source);
        $this->assertTrue(class_exists(ProductFieldPolicy::class));
    }

    public function test_storefront_exposes_filled_specifications(): void
    {
        $source = (string) file_get_contents((new ReflectionClass(StorefrontController::class))->getFileName());

        $this->assertStringContainsString('show_on_storefront', $source);
        $this->assertStringContainsString("'specifications' => \$specifications", $source);
    }

    public function test_duplicate_field_name_is_rejected_instead_of_hitting_the_unique_index(): void
    {
        $this->runProductFieldMigrations();

        ProductField::create(['name' => 'Warranty', 'type' => 'text']);

        $request = StoreProductFieldRequest::create('/dashboard/shop/product-fields', 'POST', [
            'name' => 'Warranty',
            'type' => 'text',
        ]);

        $translator = new Translator(new ArrayLoader, 'en');

        $validator = (new ValidationFactory($translator))->make($request->all(), $request->rules());

        foreach ($request->after() as $after) {
            $validator->after($after);
        }

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'A product field with this name already exists.',
            $validator->errors()->first('name'),
        );
    }

    public function test_product_form_accepts_remote_images_without_a_local_path(): void
    {
        $request = StoreProductRequest::create('/dashboard/shop/products/1', 'PUT', [
            'name' => 'Xiaomi 14 Pro',
            'type' => 'simple',
            'status' => 'published',
            'price' => '899',
        ]);
        $request->setContainer(Container::getInstance());

        $rules = $request->rules();

        $this->assertSame('nullable|string', $rules['images.*.path']);
        $this->assertStringStartsWith('nullable|array', $rules['product_field_values']);
    }

    private function runProductFieldMigrations(): void
    {
        $fields = require __DIR__.'/../database/migrations/2026_09_26_000000_create_product_fields_table.php';
        $values = require __DIR__.'/../database/migrations/2026_09_26_000100_create_product_field_values_table.php';

        $fields->up();
        $values->up();
    }

    private function rollbackProductFieldMigrations(): void
    {
        $values = require __DIR__.'/../database/migrations/2026_09_26_000100_create_product_field_values_table.php';
        $fields = require __DIR__.'/../database/migrations/2026_09_26_000000_create_product_fields_table.php';

        $values->down();
        $fields->down();
    }
}
