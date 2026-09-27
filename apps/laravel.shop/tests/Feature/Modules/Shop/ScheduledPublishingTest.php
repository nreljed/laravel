<?php

namespace Tests\Feature\Modules\Shop;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\Platform\Services\FeatureRegistry;
use Nreljed\ShopModule\Commands\PublishScheduledProducts;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\ShopServiceProvider;
use Tests\BootsFeatures;
use Tests\TestCase;

class ScheduledPublishingTest extends TestCase
{
    use BootsFeatures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootFeatures();
    }

    public function test_shop_publish_scheduled_is_wired_into_the_provider_schedule(): void
    {
        $source = (string) file_get_contents((new \ReflectionClass(ShopServiceProvider::class))->getFileName());

        $this->assertStringContainsString('shop:publish-scheduled', $source, 'Shop provider must register shop:publish-scheduled.');
        $this->assertStringContainsString('Schedule::class', $source, 'Shop provider must self-register through callAfterResolving(Schedule::class).');
        $this->assertStringContainsString('everyMinute', $source, 'shop:publish-scheduled must run every minute.');
        $this->assertStringContainsString('withoutOverlapping', $source, 'shop:publish-scheduled must not overlap runs.');
    }

    public function test_provider_declares_scheduled_publish_task(): void
    {
        $tasks = app(FeatureRegistry::class)->getAllScheduledTasks();

        $this->assertContains(
            PublishScheduledProducts::class,
            array_column($tasks, 'command'),
            'Shop feature must declare its scheduled task through the FeatureRegistry.'
        );
    }

    public function test_command_publishes_due_scheduled_products(): void
    {
        $due = Product::create([
            'name' => 'Coming Soon',
            'slug' => 'coming-soon-'.uniqid(),
            'price' => 10,
            'status' => 'future',
            'type' => 'simple',
            'publish_date' => now()->subMinute(),
        ]);

        $this->artisan('shop:publish-scheduled')->assertSuccessful();

        $this->assertSame('published', $due->fresh()->status);
        $this->assertNull($due->fresh()->publish_date);
    }

    public function test_command_publishes_only_due_products(): void
    {
        $notDue = Product::create([
            'name' => 'Still Future',
            'slug' => 'still-future-'.uniqid(),
            'price' => 10,
            'status' => 'future',
            'type' => 'simple',
            'publish_date' => now()->addDay(),
        ]);

        $this->artisan('shop:publish-scheduled')->assertSuccessful();

        $this->assertSame('future', $notDue->fresh()->status);
    }

    public function test_command_archives_products_with_past_unpublish_at(): void
    {
        $expired = Product::create([
            'name' => 'Expiring',
            'slug' => 'expiring-'.uniqid(),
            'price' => 10,
            'status' => 'published',
            'type' => 'simple',
            'unpublish_at' => now()->subMinute(),
        ]);

        $this->artisan('shop:publish-scheduled')->assertSuccessful();

        $this->assertSame('archived', $expired->fresh()->status);
    }
}
