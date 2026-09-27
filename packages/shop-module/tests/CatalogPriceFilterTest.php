<?php

namespace Nreljed\ShopModule\Tests;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;
use Nreljed\ShopModule\Http\Controllers\StorefrontController;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Tests\Concerns\ProvidesAnonymousAuth;
use PHPUnit\Framework\TestCase;

/**
 * Executes the catalog price-window SQL against a real in-memory database.
 *
 * The other price tests assert against source text, which cannot catch a query
 * that is syntactically or structurally wrong: an early revision of
 * `effectivePriceBounds()` type-hinted a closure as the Eloquent builder while
 * `DB::table()` returns the query builder, so every catalog request 500'd while
 * the suite stayed green. These tests build the real tables, insert rows, and
 * read back the ids the filter actually returns.
 *
 * The controller is built without its constructor: the window helpers only
 * touch the query, so the pricing services are not needed.
 */
class CatalogPriceFilterTest extends TestCase
{
    use ProvidesAnonymousAuth;

    private static bool $booted = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$booted) {
            $this->bootDatabase();
            self::$booted = true;
        }

        Capsule::connection()->table('products')->delete();
        Capsule::connection()->table('product_variations')->delete();
        Capsule::connection()->table('product_grouped_products')->delete();
    }

    private function bootDatabase(): void
    {
        $capsule = new Capsule;
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        // `effectivePriceBounds()` reaches for the DB facade, so give the
        // facades a container to resolve against.
        $container = new Container;
        $container->instance('db', $capsule->getDatabaseManager());
        Container::setInstance($container);
        Facade::setFacadeApplication($container);
        Facade::clearResolvedInstances();

        // `Product::creating()` stamps `slug` and reads the current user, so
        // both the auth contract and the model dispatcher have to resolve.
        $this->bindAnonymousAuth($container);
        Model::setEventDispatcher(new Dispatcher($container));
        Model::clearBootedModels();

        $schema = Capsule::schema();

        $schema->create('products', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('type')->default('simple');
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('min_variation_price', 10, 2)->nullable();
            $table->decimal('max_variation_price', 10, 2)->nullable();
            $table->string('status')->default('published');
            $table->timestamp('unpublish_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        $schema->create('product_variations', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('product_id');
            $table->decimal('active_price', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $schema->create('product_grouped_products', function ($table) {
            $table->unsignedInteger('group_product_id');
            $table->unsignedInteger('product_id');
        });
    }

    private function controller(): StorefrontController
    {
        return (new \ReflectionClass(StorefrontController::class))->newInstanceWithoutConstructor();
    }

    private function product(string $name, string $type, float $price, ?float $min = null, ?float $max = null): Product
    {
        $product = new Product(['name' => $name, 'type' => $type, 'price' => $price]);
        $product->status = 'published';
        $product->min_variation_price = $min;
        $product->max_variation_price = $max;
        $product->save();

        return $product;
    }

    private function variation(Product $product, float $activePrice, bool $isActive = true): void
    {
        Capsule::connection()->table('product_variations')->insert([
            'product_id' => $product->id,
            'active_price' => $activePrice,
            'is_active' => $isActive,
        ]);
    }

    /**
     * Run the real filter and return the matching product names.
     *
     * @return array<int, string>
     */
    private function matching(?float $floor, ?float $ceiling): array
    {
        $controller = $this->controller();
        $method = new \ReflectionMethod($controller, 'applyPriceWindow');
        $method->setAccessible(true);

        $query = Product::query();
        $method->invoke($controller, $query, $floor, $ceiling);

        return $query->orderBy('id')->pluck('name')->all();
    }

    public function test_simple_product_matches_only_its_own_price(): void
    {
        $this->product('Widget', 'simple', 25);
        $this->product('Gadget', 'simple', 500);

        $this->assertSame(['Widget'], $this->matching(0, 50));
        $this->assertSame(['Gadget'], $this->matching(400, 600));
    }

    public function test_variable_product_matches_when_its_range_overlaps(): void
    {
        $redmi = $this->product('Redmi', 'variable', 0, 349, 409);
        $this->variation($redmi, 349);
        $this->variation($redmi, 409);

        $this->assertSame(['Redmi'], $this->matching(300, 500));
        $this->assertSame(['Redmi'], $this->matching(400, 450));
    }

    public function test_grouped_product_matches_through_a_bundled_item(): void
    {
        $charger = $this->product('Charger', 'simple', 29);
        $bundle = $this->product('Bundle', 'grouped', 0);

        Capsule::connection()->table('product_grouped_products')->insert([
            'group_product_id' => $bundle->id,
            'product_id' => $charger->id,
        ]);

        $this->assertSame(['Charger', 'Bundle'], $this->matching(20, 35));
    }

    /**
     * A product with no price of its own must not satisfy a window that starts
     * at 0, which would otherwise sweep every variable and grouped product
     * into an "under 50" filter.
     */
    public function test_parent_price_of_zero_never_matches_a_window(): void
    {
        $redmi = $this->product('Redmi', 'variable', 0, 349, 409);
        $this->variation($redmi, 349);
        $this->product('Bundle', 'grouped', 0);

        $this->assertSame([], $this->matching(0, 50));
    }

    /**
     * The cached columns are only resynced by the variation model events, so a
     * bulk update can leave them null. The live-variation branch is the safety
     * net that stops such a product disappearing from every filter.
     */
    public function test_variable_product_with_no_cached_range_still_matches(): void
    {
        $redmi = $this->product('Redmi', 'variable', 0);
        $this->variation($redmi, 349);
        $this->variation($redmi, 409);

        $this->assertSame(['Redmi'], $this->matching(300, 500));
    }

    public function test_inactive_variations_do_not_match(): void
    {
        $redmi = $this->product('Redmi', 'variable', 0);
        $this->variation($redmi, 349, isActive: false);

        $this->assertSame([], $this->matching(300, 500));
    }

    public function test_open_ended_windows_are_supported(): void
    {
        $this->product('Cheap', 'simple', 25);
        $this->product('Pricey', 'simple', 500);
        $redmi = $this->product('Redmi', 'variable', 0, 349, 409);
        $this->variation($redmi, 349);

        // A floor only keeps what reaches it; a ceiling only keeps what fits
        // under it. Cheap at 25 is below the 50 floor, so it drops out.
        $this->assertSame(['Pricey', 'Redmi'], $this->matching(50, null));
        $this->assertSame(['Cheap', 'Redmi'], $this->matching(null, 400));
    }

    /**
     * The filter advertises bounds the results then have to honour: the slider
     * must never offer a range that no product can satisfy.
     */
    public function test_bounds_span_every_price_a_filter_can_return(): void
    {
        $this->product('Cheap', 'simple', 19);
        $this->product('Pricey', 'simple', 899);

        $uncached = $this->product('Uncached', 'variable', 0);
        $this->variation($uncached, 300);

        $bundled = $this->product('Bundle', 'grouped', 0);
        $item = $this->product('Item', 'simple', 45);
        Capsule::connection()->table('product_grouped_products')->insert([
            'group_product_id' => $bundled->id,
            'product_id' => $item->id,
        ]);

        $controller = $this->controller();
        $method = new \ReflectionMethod($controller, 'effectivePriceBounds');
        $method->setAccessible(true);
        $bounds = $method->invoke($controller);

        // The uncached variable product's live price has to be counted, or the
        // slider would start above 300 and the filter could never return it.
        $this->assertSame(19.0, $bounds['min']);
        $this->assertSame(899.0, $bounds['max']);

        foreach ($this->matching($bounds['min'], $bounds['max']) as $name) {
            $this->assertContains($name, ['Cheap', 'Pricey', 'Uncached', 'Bundle', 'Item']);
        }
    }
}
