<?php

namespace Tests\Feature\Modules\Shop;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\Webhook;
use Nreljed\ShopModule\Services\WebhookService;
use Tests\BootsFeatures;
use Tests\TestCase;

class ApiAndWebhookTest extends TestCase
{
    use BootsFeatures, RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFeatures();

        $this->admin = User::factory()->create(['email' => 'admin@test.com']);
        $this->admin->assignRole('admin');

        Product::create(['name' => 'API Product 1', 'slug' => 'api-product-1', 'price' => 19.99, 'status' => 'published']);
        Product::create(['name' => 'API Product 2', 'slug' => 'api-product-2', 'price' => 29.99, 'status' => 'published']);
    }

    public function test_api_products_index(): void
    {
        $response = $this->getJson('/api/products');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment(['name' => 'API Product 1']);
    }

    public function test_api_products_show(): void
    {
        $product = Product::first();

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertOk();
        $response->assertJsonFragment(['slug' => $product->slug]);
    }

    public function test_api_products_search(): void
    {
        $response = $this->getJson('/api/products?search=Product+2');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['name' => 'API Product 2']);
    }

    public function test_api_orders_requires_auth(): void
    {
        $response = $this->getJson('/api/orders');

        $response->assertUnauthorized();
    }

    public function test_api_orders_index_with_token(): void
    {
        $token = $this->admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/orders');

        $response->assertOk();
    }

    public function test_webhook_crud(): void
    {
        $this->actingAs($this->admin);

        // Create
        $response = $this->post(route('dashboard.shop.webhooks.store'), [
            'name' => 'Test Webhook',
            'url' => 'https://example.com/hook',
            'events' => ['order.created', 'order.status_changed'],
        ]);

        $response->assertSessionHas('flash.success');
        $this->assertDatabaseHas('webhooks', ['name' => 'Test Webhook']);

        // Index
        $response = $this->get(route('dashboard.shop.webhooks.index'));
        $this->assertNotEquals(404, $response->getStatusCode());
        $response->assertSee('Test Webhook');

        // Update
        $webhook = Webhook::first();
        $response = $this->put(route('dashboard.shop.webhooks.update', $webhook->id), [
            'name' => 'Updated Webhook',
            'url' => 'https://example.com/hook-v2',
            'events' => ['order.created'],
        ]);
        $response->assertSessionHas('flash.success');
        $this->assertDatabaseHas('webhooks', ['name' => 'Updated Webhook']);

        // Toggle active
        $response = $this->post(route('dashboard.shop.webhooks.toggle-active', $webhook->id));
        $response->assertSessionHas('flash.success');
        $this->assertFalse($webhook->fresh()->is_active);

        // Delete
        $response = $this->delete(route('dashboard.shop.webhooks.destroy', $webhook->id));
        $response->assertSessionHas('flash.success');
        $this->assertDatabaseMissing('webhooks', ['id' => $webhook->id]);
    }

    public function test_webhook_test_ping(): void
    {
        $this->actingAs($this->admin);

        $webhook = Webhook::create([
            'name' => 'Ping Test',
            'url' => 'https://example.com/hook',
            'events' => ['test.ping'],
            'is_active' => true,
        ]);

        // We expect this to fail (URL unreachable) but the call should be created
        $response = $this->post(route('dashboard.shop.webhooks.test', $webhook->id));
        $response->assertSessionHas('flash.success');

        $this->assertDatabaseHas('webhook_calls', [
            'webhook_id' => $webhook->id,
            'event' => 'test.ping',
        ]);
    }

    public function test_webhook_calls_page(): void
    {
        $this->actingAs($this->admin);

        $webhook = Webhook::create([
            'name' => 'Calls Test',
            'url' => 'https://example.com/hook',
            'events' => ['order.created'],
            'is_active' => true,
        ]);

        $response = $this->get(route('dashboard.shop.webhooks.calls', $webhook->id));
        $this->assertNotEquals(404, $response->getStatusCode());
        $response->assertSee('Calls Test');
    }

    public function test_webhook_service_dispatches_to_active_webhooks(): void
    {
        $webhook = Webhook::create([
            'name' => 'Service Test',
            'url' => 'https://example.com/hook',
            'events' => ['order.created'],
            'is_active' => true,
        ]);

        $service = app(WebhookService::class);
        $service->dispatch('order.created', ['test' => true]);

        $this->assertDatabaseHas('webhook_calls', [
            'webhook_id' => $webhook->id,
            'event' => 'order.created',
        ]);
    }

    public function test_webhook_service_skips_inactive_webhooks(): void
    {
        Webhook::create([
            'name' => 'Inactive Test',
            'url' => 'https://example.com/hook',
            'events' => ['order.created'],
            'is_active' => false,
        ]);

        $service = app(WebhookService::class);
        $service->dispatch('order.created', ['test' => true]);

        $this->assertDatabaseCount('webhook_calls', 0);
    }

    public function test_webhook_service_skips_unregistered_events(): void
    {
        $webhook = Webhook::create([
            'name' => 'Selective Test',
            'url' => 'https://example.com/hook',
            'events' => ['order.created'],
            'is_active' => true,
        ]);

        $service = app(WebhookService::class);
        $service->dispatch('product.updated', ['test' => true]);

        $this->assertDatabaseCount('webhook_calls', 0);
    }

    public function test_webhook_process_pending_command(): void
    {
        $webhook = Webhook::create([
            'name' => 'Process Test',
            'url' => 'https://example.com/hook',
            'events' => ['order.created'],
            'is_active' => true,
        ]);

        $service = app(WebhookService::class);
        $service->dispatch('order.created', ['test' => true]);

        $this->assertDatabaseHas('webhook_calls', ['webhook_id' => $webhook->id, 'status' => 0]);

        $this->artisan('webhooks:process')
            ->assertSuccessful();

        // After processing (failed because URL unreachable), the call should be marked as failed
        $call = $webhook->calls()->first();
        $this->assertNotNull($call);
        $this->assertGreaterThan(0, $call->attempts);
    }

    public function test_api_token_management(): void
    {
        $this->actingAs($this->admin);

        // Account page loads with Sanctum token management support
        $response = $this->get(route('dashboard.account'));
        $this->assertNotEquals(404, $response->getStatusCode());

        // Create token
        $this->admin->createToken('Test Token');
        $this->assertCount(1, $this->admin->fresh()->tokens);

        // Delete token
        $this->admin->tokens()->delete();
        $this->assertCount(0, $this->admin->fresh()->tokens);
    }

    public function test_storefront_index(): void
    {
        $response = $this->get(route('store.products'));

        $response->assertOk();
        $response->assertSee('API Product 1');
        $response->assertSee('Shop App');
    }

    public function test_storefront_show(): void
    {
        $product = Product::where('status', 'published')->first();

        $response = $this->get(route('store.show', $product->id));

        $response->assertOk();
        $response->assertSee($product->name);
    }

    public function test_storefront_hides_unpublished(): void
    {
        $unpublished = Product::create(['name' => 'Hidden', 'slug' => 'hidden', 'price' => 5, 'status' => 'draft']);

        $response = $this->get(route('store.show', $unpublished->id));
        $response->assertNotFound();
    }

    public function test_storefront_search(): void
    {
        $response = $this->get(route('store.products', ['search' => 'Product 2']));

        $response->assertOk();
        $response->assertSee('API Product 2');
        $response->assertDontSee('API Product 1');
    }
}
