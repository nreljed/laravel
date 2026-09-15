<?php

namespace Tests\Feature\Modules\Shop;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductCategory;
use Tests\BootsFeatures;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use BootsFeatures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFeatures();
    }

    public function test_sitemap_includes_store_urls(): void
    {
        $category = ProductCategory::create(['name' => 'Gadgets', 'slug' => 'gadgets', 'is_active' => true]);

        $product = Product::create([
            'name' => 'Sitemap Widget',
            'slug' => 'sitemap-widget',
            'price' => 9.99,
            'status' => 'published',
        ]);
        $product->categories()->attach($category);

        Product::create([
            'name' => 'Hidden Draft',
            'slug' => 'hidden-draft',
            'price' => 9.99,
            'status' => 'draft',
        ]);

        $base = rtrim((string) config('app.url'), '/');

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee($base.'/store');
        $response->assertSee($base.'/store/sitemap-widget');
        $response->assertSee($base.'/store/category/gadgets');
        $response->assertDontSee('hidden-draft');
    }

    public function test_robots_txt_is_dynamic(): void
    {
        $base = rtrim((string) config('app.url'), '/');

        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertSee('Disallow: /dashboard');
        $response->assertSee('Disallow: /login');
        $response->assertSee('Sitemap: '.$base.'/sitemap.xml');
    }

    public function test_storefront_show_passes_product_seo_fields(): void
    {
        $product = Product::create([
            'name' => 'Seo Shirt',
            'slug' => 'seo-shirt',
            'price' => 25.0,
            'status' => 'published',
            'seo' => [
                'meta_title' => 'Custom SEO Title',
                'meta_description' => 'Custom SEO description here',
                'og_image' => 'https://example.com/og.jpg',
            ],
        ]);

        $response = $this->get("/store/{$product->slug}");

        $response->assertOk();
        $response->assertSee('Custom SEO Title');
        $response->assertSee('Custom SEO description here');
        $response->assertSee('https:\\/\\/example.com\\/og.jpg');
    }
}
