<?php

namespace Nreljed\ShopModule\Tests;

use Closure;
use Illuminate\Http\Request;
use Nreljed\ShopModule\Http\Middleware\RedirectLegacyUrlAliases;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductCategory;
use Nreljed\ShopModule\Observers\SyncsUrlAliases;
use Nreljed\ShopModule\Services\UrlAliasService;
use Nreljed\ShopModule\Tests\Concerns\BootsShopSchema;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Legacy-storefront-URL redirects (PR 11A).
 *
 * The storefront serves `/store/{slug}` and `/store/category/{slug}`. When a
 * product or category is renamed, its old path used to become a 404 - and a
 * 404 is exactly what a search engine keeps. This pins that a slug change
 * records the old path as a `url_aliases` row and that the storefront answers
 * it with a 301 to the new canonical URL.
 */
final class UrlAliasesTest extends TestCase
{
    use BootsShopSchema;

    private static bool $observersRegistered = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootShopSchema();
        $this->resetShopTables();

        // The harness does not boot the feature provider, so mirror the
        // provider's observer registration the way CI would see it.
        if (! self::$observersRegistered) {
            Product::observe(SyncsUrlAliases::class);
            ProductCategory::observe(SyncsUrlAliases::class);

            self::$observersRegistered = true;
        }
    }

    // ---------------------------------------------------------------------
    // Recording.
    // ---------------------------------------------------------------------

    public function test_renaming_a_product_keeps_its_old_url_as_an_alias(): void
    {
        $product = $this->product(['slug' => 'old-sneaker']);

        $product->update(['slug' => 'new-sneaker']);

        $this->assertRowExists('url_aliases', [
            'path' => '/store/old-sneaker',
            'target_type' => Product::class,
            'target_id' => $product->id,
        ], 'A renamed product must record its old storefront path.');
    }

    public function test_renaming_a_category_keeps_its_old_url_as_an_alias(): void
    {
        $category = $this->category(['slug' => 'old-shoes']);

        $category->update(['slug' => 'new-shoes']);

        $this->assertRowExists('url_aliases', [
            'path' => '/store/category/old-shoes',
            'target_type' => ProductCategory::class,
            'target_id' => $category->id,
        ], 'A renamed category must record its old storefront path.');
    }

    public function test_editing_a_product_without_renaming_records_nothing(): void
    {
        $product = $this->product(['slug' => 'stable-thing']);

        $product->update(['price' => 99]);

        $this->assertRowMissing('url_aliases', [
            'target_type' => Product::class,
            'target_id' => $product->id,
        ], 'A price edit must not pollute the alias table.');
    }

    public function test_the_service_will_not_alias_a_path_that_is_already_canonical(): void
    {
        $product = $this->product(['slug' => 'thing']);

        $alias = $this->service()->remember('/store/thing', $product);

        $this->assertNull($alias, 'An alias pointing at itself would redirect 301 back onto the requesting URL: a loop, not a redirect.');
        $this->assertRowMissing('url_aliases', ['path' => '/store/thing']);
    }

    public function test_the_service_resolves_a_recorded_alias_back_to_its_target(): void
    {
        $product = $this->product(['slug' => 'old-thing']);
        $product->update(['slug' => 'new-thing']);

        $target = $this->service()->resolve('/store/old-thing');

        $this->assertInstanceOf(Product::class, $target);
        $this->assertSame($product->id, $target->id);
    }

    // ---------------------------------------------------------------------
    // Redirecting.
    // ---------------------------------------------------------------------

    public function test_the_storefront_answers_a_renamed_products_old_path_with_301(): void
    {
        $product = $this->product(['slug' => 'old-sneaker']);
        $product->update(['slug' => 'new-sneaker']);

        $response = $this->throughMiddleware(Request::create('/store/old-sneaker'));

        $this->assertSame(Response::HTTP_MOVED_PERMANENTLY, $response->getStatusCode(), 'An old product URL must be a permanent redirect, not a 404.');
        $this->assertSame('http://localhost/store/new-sneaker', $response->headers->get('Location'), 'The 301 must point at the product\'s current canonical URL.');
    }

    public function test_the_storefront_answers_a_renamed_categorys_old_path_with_301(): void
    {
        $category = $this->category(['slug' => 'old-shoes']);
        $category->update(['slug' => 'new-shoes']);

        $response = $this->throughMiddleware(Request::create('/store/category/old-shoes'));

        $this->assertSame(Response::HTTP_MOVED_PERMANENTLY, $response->getStatusCode());
        $this->assertSame('http://localhost/store/category/new-shoes', $response->headers->get('Location'));
    }

    public function test_a_path_with_no_alias_is_handed_to_the_next_middleware(): void
    {
        $called = false;
        $response = $this->throughMiddleware(Request::create('/store/products'), function () use (&$called): Response {
            $called = true;

            return new Response('ok');
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($called, 'The middleware must not swallow paths that have no alias.');
    }

    public function test_a_non_get_request_is_never_redirected(): void
    {
        $called = false;
        $response = $this->throughMiddleware(Request::create('/store/old-sneaker', 'POST'), function () use (&$called): Response {
            $called = true;

            return new Response('ok');
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($called, 'Only GETs are redirected; a POST to the same path is shop logic, not an alias lookup.');
    }

    public function test_a_canonical_path_that_was_once_an_alias_does_not_loop(): void
    {
        // A -> B records the alias A. Renaming back B -> A makes A canonical
        // again but leaves the old A row behind. Requesting A must render,
        // not 301 to itself forever.
        $product = $this->product(['slug' => 'first-thing']);
        $product->update(['slug' => 'second-thing']);
        $product->update(['slug' => 'first-thing']);

        $called = false;
        $response = $this->throughMiddleware(Request::create('/store/first-thing'), function () use (&$called): Response {
            $called = true;

            return new Response('ok');
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($called, 'A 301 back onto the requested URL is a redirect loop.');
    }

    // ---------------------------------------------------------------------
    // Deletion.
    // ---------------------------------------------------------------------

    public function test_deleting_a_product_flushes_its_aliases(): void
    {
        $product = $this->product(['slug' => 'old-thing']);
        $product->update(['slug' => 'new-thing']);

        $this->assertRowExists('url_aliases', ['path' => '/store/old-thing']);

        $product->delete();

        $this->assertRowMissing('url_aliases', [
            'target_type' => Product::class,
            'target_id' => $product->id,
        ], 'Deleting a product must not leave a 301 pointing at a route-model binding that can no longer resolve.');
    }

    public function test_deleting_a_category_flushes_its_aliases(): void
    {
        $category = $this->category(['slug' => 'old-shoes']);
        $category->update(['slug' => 'new-shoes']);

        $category->delete();

        $this->assertRowMissing('url_aliases', [
            'target_type' => ProductCategory::class,
            'target_id' => $category->id,
        ]);
    }

    // ---------------------------------------------------------------------
    // Helpers.
    // ---------------------------------------------------------------------

    private function throughMiddleware(Request $request, ?Closure $next = null): Response
    {
        $next ??= fn (): Response => new Response('ok');

        return (new RedirectLegacyUrlAliases($this->service()))->handle($request, $next);
    }

    private function service(): UrlAliasService
    {
        return new UrlAliasService;
    }

    private function product(array $attributes = []): Product
    {
        return Product::create($attributes + [
            'name' => 'Thing',
            'slug' => 'thing-'.uniqid(),
            'status' => 'published',
            'type' => 'simple',
            'catalog_visibility' => 'visible',
            'price' => 10,
            'stock_quantity' => 5,
            'track_stock' => true,
        ]);
    }

    private function category(array $attributes = []): ProductCategory
    {
        return ProductCategory::create($attributes + [
            'name' => 'Category',
            'slug' => 'category-'.uniqid(),
            'is_active' => true,
        ]);
    }
}