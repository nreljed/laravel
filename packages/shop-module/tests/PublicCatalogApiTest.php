<?php

namespace Nreljed\ShopModule\Tests;

use Illuminate\Http\Request;
use Nreljed\ShopModule\Http\Controllers\Api\ProductsController;
use Nreljed\ShopModule\Http\Resources\ProductResource;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\ShopServiceProvider;
use Nreljed\ShopModule\Tests\Concerns\BootsShopSchema;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The public catalog API published the shop's unpublished work.
 *
 * `ProductsController::index()` started from `Product::query()` with no
 * status constraint, so drafts, archived products, products scheduled for a
 * future `publish_date` and products whose `unpublish_at` had passed were all
 * returned - and `?status=draft` asked for them by name, which made the
 * endpoint a way to enumerate the editorial pipeline from outside.
 *
 * `show()` was worse in one respect: route-model binding resolved any product
 * id, so a single draft could be fetched directly, with no filter needed.
 *
 * `catalog_visibility` was ignored on both, so products a merchant had marked
 * `search` (reachable by direct link, absent from listings) or `hidden` were
 * served in the public feed.
 *
 * The endpoint was also unthrottled and pageable, which together made it a
 * bulk export of the whole catalog.
 */
final class PublicCatalogApiTest extends TestCase
{
    use BootsShopSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootShopSchema();
        $this->resetShopTables();
    }

    // ---------------------------------------------------------------------
    // Unpublished products.
    // ---------------------------------------------------------------------

    public function test_draft_products_are_not_listed(): void
    {
        $this->product(['name' => 'Secret Draft', 'status' => 'draft']);

        $this->assertNotContains(
            'Secret Draft',
            $this->listedNames(),
            'A draft was served by the public catalog API before it launched.'
        );
    }

    public function test_a_status_filter_cannot_reach_unpublished_products(): void
    {
        $this->product(['name' => 'Secret Draft', 'status' => 'draft']);
        $this->product(['name' => 'Archived Thing', 'status' => 'archived']);
        $this->product(['name' => 'Live Thing', 'status' => 'published']);

        // The endpoint used to honour this parameter, which turned a public
        // feed into a listing of whatever the shop had decided not to publish.
        foreach (['draft', 'archived', 'pending', 'published'] as $status) {
            $names = $this->listedNames(['status' => $status]);

            $this->assertNotContains('Secret Draft', $names, "?status={$status} exposed a draft.");
            $this->assertNotContains('Archived Thing', $names, "?status={$status} exposed an archived product.");
        }

        $this->assertContains('Live Thing', $this->listedNames(['status' => 'published']));
    }

    public function test_a_draft_is_not_fetchable_by_id(): void
    {
        $draft = $this->product(['name' => 'Secret Draft', 'status' => 'draft']);

        $this->assertNotFound(
            fn () => $this->show($draft),
            'A draft was readable one id at a time through the show endpoint, which needed no filter at all.'
        );
    }

    public function test_an_archived_product_is_not_fetchable_by_id(): void
    {
        $archived = $this->product(['name' => 'Archived Thing', 'status' => 'archived']);

        $this->assertNotFound(fn () => $this->show($archived));
    }

    public function test_a_scheduled_product_is_not_listed_before_its_date(): void
    {
        // `future` is how the shop schedules a launch: the admin form parks a
        // product in that status and `shop:publish-scheduled` promotes it once
        // `publish_date` arrives.
        $this->product([
            'name' => 'Future Thing',
            'status' => 'future',
            'publish_date' => now()->addWeek(),
        ]);

        $this->assertNotContains('Future Thing', $this->listedNames());
    }

    public function test_a_published_product_is_served_even_with_a_future_publish_date(): void
    {
        // Publishing now and leaving a stale future `publish_date` is
        // reachable through the admin form, which only *requires* the date when
        // the status is `future`. A merchant who published should see it
        // listed. This also pins the API to the storefront's rule: both use
        // `scopePublished()`, which reads `status` and `unpublish_at` and
        // ignores `publish_date`.
        $this->product([
            'name' => 'Live Thing',
            'status' => 'published',
            'publish_date' => now()->addWeek(),
        ]);

        $this->assertContains('Live Thing', $this->listedNames());
    }

    public function test_a_product_past_its_unpublish_date_is_not_listed(): void
    {
        $this->product([
            'name' => 'Retired Thing',
            'status' => 'published',
            'unpublish_at' => now()->subMinute(),
        ]);

        $this->assertNotContains('Retired Thing', $this->listedNames());
    }

    public function test_a_product_past_its_unpublish_date_is_not_fetchable_by_id(): void
    {
        $retired = $this->product([
            'name' => 'Retired Thing',
            'status' => 'published',
            'unpublish_at' => now()->subMinute(),
        ]);

        $this->assertNotFound(fn () => $this->show($retired));
    }

    public function test_a_live_product_is_still_served(): void
    {
        $live = $this->product(['name' => 'Live Thing', 'status' => 'published']);

        $this->assertContains('Live Thing', $this->listedNames(), 'The fix must not empty the public catalog.');
        $this->assertSame(200, $this->showStatus($live), 'The fix must not break the show endpoint for a live product.');
    }

    // ---------------------------------------------------------------------
    // catalog_visibility.
    // ---------------------------------------------------------------------

    public function test_products_marked_search_only_are_not_listed(): void
    {
        $this->product(['name' => 'Unlisted Thing', 'status' => 'published', 'catalog_visibility' => 'search']);

        $this->assertNotContains(
            'Unlisted Thing',
            $this->listedNames(),
            '`search` means reachable by direct link but absent from listings; a public feed is a listing.'
        );
    }

    public function test_hidden_products_are_not_listed_or_fetchable(): void
    {
        $hidden = $this->product(['name' => 'Concealed Thing', 'status' => 'published', 'catalog_visibility' => 'hidden']);

        $this->assertNotContains('Concealed Thing', $this->listedNames());
        $this->assertNotFound(fn () => $this->show($hidden));
    }

    public function test_catalog_and_visible_products_are_served(): void
    {
        $this->product(['name' => 'Visible Thing', 'status' => 'published', 'catalog_visibility' => 'visible']);
        $this->product(['name' => 'Catalog Thing', 'status' => 'published', 'catalog_visibility' => 'catalog']);

        $names = $this->listedNames();

        $this->assertContains('Visible Thing', $names);
        $this->assertContains('Catalog Thing', $names);
    }

    /**
     * The API and the storefront page now answer the same question the same
     * way, which is the whole point of putting the rule on the model.
     *
     * These two used to disagree in both directions, and each divergence was
     * defensible in isolation: the storefront's `show()` read only `status` and
     * `unpublish_at`, so it served a `hidden` product's page; the API held a
     * narrower list of its own, so it 404'd a `search` product the storefront
     * happily rendered. One product, two answers, decided by which URL a caller
     * had - and a `search` value that no route would find it by.
     *
     * Reachable by link is now one predicate, `isVisibleOnStorefront()`, used by
     * both. The two cases that used to differ are pinned from each side: a
     * search-only product is served, a hidden one is not.
     */
    public function test_the_api_show_matches_the_storefront_show(): void
    {
        $searchOnly = $this->product(['name' => 'Search Only Thing', 'status' => 'published', 'catalog_visibility' => 'search']);
        $hidden = $this->product(['name' => 'Hidden Thing', 'status' => 'published', 'catalog_visibility' => 'hidden']);

        foreach ([$searchOnly, $hidden] as $product) {
            $expected = $product->isVisibleOnStorefront() ? 200 : 404;

            $this->assertSame(
                $expected,
                $this->showStatus($product),
                "The API's answer for a `{$product->catalog_visibility}` product disagrees with the storefront gate."
            );
        }

        $this->assertSame(200, $this->showStatus($searchOnly), 'A search-only product is findable and reachable, or the value is dead.');
        $this->assertSame(404, $this->showStatus($hidden), 'A hidden product must not be readable at its own URL, in the feed or in the shop.');
    }

    public function test_a_search_only_product_is_findable_through_the_api_too(): void
    {
        $this->product(['name' => 'Listed Thing', 'status' => 'published', 'catalog_visibility' => 'visible']);
        $this->product(['name' => 'Needle In The Catalog', 'status' => 'published', 'catalog_visibility' => 'search']);
        $this->product(['name' => 'Hidden Needle', 'status' => 'published', 'catalog_visibility' => 'hidden']);

        $this->assertSame(
            ['Needle In The Catalog'],
            $this->listedNames(['search' => 'Needle']),
            'A public feed answers a search the same way the shop does, so a search-only product is findable and a hidden one is not.'
        );

        $this->assertSame(
            ['Listed Thing'],
            $this->listedNames(),
            'A listing carries the listing set. A search term is what earns the wider one, in the feed exactly as in the shop.'
        );
    }

    // ---------------------------------------------------------------------
    // The published payload.
    // ---------------------------------------------------------------------

    public function test_the_public_payload_does_not_carry_editorial_state(): void
    {
        $product = $this->product(['name' => 'Live Thing', 'status' => 'published']);
        $payload = (new ProductResource($product))->toArray(Request::create('/api/products'));

        $this->assertArrayNotHasKey(
            'status',
            $payload,
            'The endpoint is published-only, so `status` could only ever read "published" while implying the caller had a say in it.'
        );
        $this->assertArrayNotHasKey(
            'catalog_visibility',
            $payload,
            'Catalog visibility is an editorial setting, not catalog data.'
        );
    }

    public function test_the_public_payload_still_carries_what_a_buyer_needs(): void
    {
        $product = $this->product(['name' => 'Live Thing', 'status' => 'published', 'price' => 42.5, 'sku' => 'SKU-1']);
        $payload = (new ProductResource($product))->toArray(Request::create('/api/products'));

        foreach (['id', 'name', 'slug', 'price', 'sku', 'stock_quantity', 'thumbnail_url'] as $field) {
            $this->assertArrayHasKey($field, $payload, "Trimming the payload dropped `{$field}`, which the storefront already publishes.");
        }
    }

    // ---------------------------------------------------------------------
    // Bulk-extraction resistance.
    // ---------------------------------------------------------------------

    public function test_the_public_api_is_throttled(): void
    {
        $source = file_get_contents(dirname(__DIR__).'/src/Routes/api.php');

        $this->assertMatchesRegularExpression(
            "/Route::middleware\(\s*'throttle:[\d,]+\s*'\s*\)\s*->group\(\s*function\s*\(\s*\)\s*\{[\s\S]*?api\.products\.index[\s\S]*?api\.products\.show/",
            $source,
            'The unauthenticated catalog API must be rate limited: it is pageable, so without a throttle it is a bulk export.'
        );
    }

    public function test_page_size_is_clamped(): void
    {
        $controller = $this->controller();

        $perPage = new ReflectionClass($controller);
        $method = $perPage->getMethod('perPage');

        $this->assertSame(20, $method->invoke($controller, Request::create('/api/products')), 'Default page size should stay 20.');
        $this->assertSame(100, $method->invoke($controller, Request::create('/api/products?per_page=100')));
        $this->assertSame(100, $method->invoke($controller, Request::create('/api/products?per_page=100000')), 'per_page must be capped, or the cap that reads as 100 is not real.');
        $this->assertSame(20, $method->invoke($controller, Request::create('/api/products?per_page=0')), 'per_page=0 returned an empty page that reads as "no products" rather than a bad request.');
        $this->assertSame(20, $method->invoke($controller, Request::create('/api/products?per_page=abc')));
        $this->assertSame(1, $method->invoke($controller, Request::create('/api/products?per_page=-5')));
    }

    public function test_a_search_term_is_capped(): void
    {
        $controller = $this->controller();
        $method = (new ReflectionClass($controller))->getMethod('searchTerm');

        $this->assertSame('boots', $method->invoke($controller, Request::create('/api/products?search=boots')));
        $this->assertSame('boots', $method->invoke($controller, Request::create('/api/products?search=%20%20boots%20%20')), 'Whitespace around a search term should not change the match.');

        $this->assertNull($method->invoke($controller, Request::create('/api/products')), 'No search term should add no filter, not filter on empty.');
        $this->assertNull($method->invoke($controller, Request::create('/api/products?search=')));
        $this->assertNull($method->invoke($controller, Request::create('/api/products?search=%20%20')));

        $this->assertSame(
            100,
            mb_strlen((string) $method->invoke($controller, Request::create('/api/products?search='.str_repeat('a', 5000)))),
            'The term is interpolated into a LIKE pattern; an unbounded string is unbounded scan work per anonymous caller.'
        );
    }

    public function test_a_non_numeric_price_filter_is_ignored_rather_than_emptying_the_catalog(): void
    {
        $this->product(['name' => 'Live Thing', 'status' => 'published', 'price' => 42.5]);

        // `(float) 'not-a-number'` is 0.0, so an unguarded `max_price` becomes
        // `price <= 0` and the catalog silently comes back empty - which reads
        // as "nothing is in this price range" rather than "your filter was
        // junk". A junk filter is ignored instead.
        $this->assertContains(
            'Live Thing',
            $this->listedNames(['max_price' => 'not-a-number']),
            'A junk max_price emptied the catalog instead of being ignored.'
        );

        $this->assertContains(
            'Live Thing',
            $this->listedNames(['min_price' => 'not-a-number']),
        );
    }

    public function test_a_numeric_price_filter_still_applies(): void
    {
        $this->product(['name' => 'Cheap Thing', 'status' => 'published', 'price' => 10]);
        $this->product(['name' => 'Pricey Thing', 'status' => 'published', 'price' => 900]);

        $names = $this->listedNames(['max_price' => '100']);

        $this->assertContains('Cheap Thing', $names);
        $this->assertNotContains('Pricey Thing', $names, 'Ignoring junk must not mean ignoring real filters.');
    }

    // ---------------------------------------------------------------------
    // Helpers.
    // ---------------------------------------------------------------------

    private function listedNames(array $query = []): array
    {
        $request = Request::create('/api/products'.($query ? '?'.http_build_query($query) : ''));

        $resource = $this->controller()->index($request);

        return collect($resource->resolve($request))
            ->pluck('name')
            ->all();
    }

    /**
     * `show()` aborts when the product is not browsable. Only the rejection is
     * asserted here: rendering the resource on the allow path needs a response
     * factory the bare harness does not build, and `ProductResource` itself is
     * covered by the payload tests below.
     */
    private function show(Product $product): void
    {
        $this->controller()->show($product);
    }

    private function showStatus(Product $product): int
    {
        try {
            $this->show($product);

            return 200;
        } catch (NotFoundHttpException) {
            return 404;
        }
    }

    private function controller(): ProductsController
    {
        return (new ReflectionClass(ProductsController::class))->newInstanceWithoutConstructor();
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

    private function assertNotFound(callable $callback, string $message = ''): void
    {
        try {
            $callback();
        } catch (NotFoundHttpException) {
            $this->assertTrue(true);

            return;
        } catch (\Throwable $e) {
            $this->fail("Leaked: the guard let the request past and reached ".get_class($e)." (".$e->getMessage()."). ".($message !== '' ? $message : ''));
        }

        $this->fail("Leaked: the product was served. ".($message !== '' ? $message : ''));
    }
}
