<?php

namespace Tests\Feature\Modules\Shop;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Nreljed\ShopModule\Models\Product;
use Tests\BootsFeatures;
use Tests\TestCase;

class StorefrontRateLimitTest extends TestCase
{
    use BootsFeatures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootFeatures();

        Product::create(['name' => 'Rated Product', 'slug' => 'rated-product', 'price' => 9.99, 'status' => 'published']);
    }

    public function test_storefront_browse_surface_respects_base_rate_limit(): void
    {
        Config::set('shop.rate_limiting.store', 3);

        $this->get(route('store.products'))->assertOk();
        $this->get(route('store.products'))->assertOk();
        $this->get(route('store.products'))->assertOk();
        $this->get(route('store.products'))->assertStatus(429);
    }

    public function test_cart_mutations_respect_the_strict_rate_limit(): void
    {
        Config::set('shop.rate_limiting.mutations', 2);

        $response = $this->post(route('store.cart.add-item'), ['product_id' => Product::first()->id]);

        $this->assertNotEquals(429, $response->getStatusCode());
        $this->assertNotEquals(419, $response->getStatusCode());

        $response2 = $this->post(route('store.cart.add-item'), ['product_id' => Product::first()->id]);
        $this->assertNotEquals(429, $response2->getStatusCode());

        $response3 = $this->post(route('store.cart.add-item'), ['product_id' => Product::first()->id]);
        $this->assertEquals(429, $response3->getStatusCode());
    }

    public function test_stripe_webhook_respects_tight_rate_limit(): void
    {
        Config::set('shop.rate_limiting.webhook', 1);

        $this->post('/store/webhook/stripe', [], ['Stripe-Signature' => 'invalid'])
            ->assertStatus(400);

        $this->post('/store/webhook/stripe', [], ['Stripe-Signature' => 'invalid'])
            ->assertStatus(429);
    }
}
