<?php

namespace Nreljed\ShopModule\Tests;

use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\SQLiteConnection;
use Nreljed\ShopModule\Commands\PublishScheduledProducts;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\ShopServiceProvider;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Contract tests for PR 10A (scheduled publishing).
 *
 * DB-free like PriceResolverTest: an in-memory connection resolver is
 * registered so Eloquent can build the query object and render SQL, but no
 * query ever runs against real storage.
 */
final class ScheduledPublishingContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $resolver = new ConnectionResolver;
        $resolver->addConnection('default', new SQLiteConnection(new PDO('sqlite::memory:')));
        $resolver->setDefaultConnection('default');
        Model::setConnectionResolver($resolver);
    }

    public function test_published_scope_is_time_aware_against_unpublish_at(): void
    {
        $sql = Product::query()->published()->toSql();

        $this->assertStringContainsString('"status" = ?', $sql);
        $this->assertStringContainsString('"unpublish_at" is null', $sql);
        $this->assertStringContainsString('"unpublish_at" > ?', $sql);
    }

    public function test_published_product_without_unpublish_at_is_visible(): void
    {
        $product = new Product(['status' => 'published', 'unpublish_at' => null]);

        $this->assertTrue($product->isVisibleOnStorefront());
    }

    public function test_published_product_with_future_unpublish_at_is_visible(): void
    {
        $product = new Product(['status' => 'published', 'unpublish_at' => now()->addDay()]);

        $this->assertTrue($product->isVisibleOnStorefront());
    }

    public function test_published_product_with_past_unpublish_at_is_hidden(): void
    {
        $product = new Product(['status' => 'published', 'unpublish_at' => now()->subMinute()]);

        $this->assertFalse($product->isVisibleOnStorefront());
    }

    public function test_non_published_status_is_hidden(): void
    {
        $product = new Product(['status' => 'future', 'unpublish_at' => null]);

        $this->assertFalse($product->isVisibleOnStorefront());
    }

    public function test_provider_declares_scheduled_publish_task(): void
    {
        $provider = new ShopServiceProvider(app());

        $tasks = $provider->getScheduledTasks();

        $this->assertNotEmpty($tasks, 'Shop feature must declare its scheduled task.');
        $this->assertSame(PublishScheduledProducts::class, $tasks[0]['command'] ?? null);
        $this->assertNotEmpty($tasks[0]['frequency'] ?? '');
    }

    public function test_schedule_publish_is_wired_into_shop_schedule(): void
    {
        $source = (string) file_get_contents((new ReflectionClass(ShopServiceProvider::class))->getFileName());

        $this->assertStringContainsString('shop:publish-scheduled', $source, 'Shop provider must register shop:publish-scheduled.');
        $this->assertStringContainsString('everyMinute', $source, 'shop:publish-scheduled must run every minute.');
        $this->assertStringContainsString('withoutOverlapping', $source, 'shop:publish-scheduled must not overlap runs.');
    }
}
