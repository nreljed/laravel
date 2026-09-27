<?php

namespace Nreljed\ShopModule\Tests;

use Illuminate\Container\Container;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Validation\Factory as ValidationFactory;
use Illuminate\Validation\ValidationException;
use Nreljed\ShopModule\Http\Controllers\Api\ProductsController;
use Nreljed\ShopModule\Http\Controllers\CartController;
use Nreljed\ShopModule\Http\Controllers\OrdersController;
use Nreljed\ShopModule\Http\Controllers\StorefrontController;
use Nreljed\ShopModule\Http\Controllers\WishlistController;
use Nreljed\ShopModule\Models\Cart;
use Nreljed\ShopModule\Models\CartItem;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Pipelines\Cart\ValidateProductAvailability;
use Nreljed\ShopModule\Pipelines\CartContext;
use Nreljed\ShopModule\Pipelines\Checkout\ValidateAvailability;
use Nreljed\ShopModule\Pipelines\CheckoutContext;
use Nreljed\ShopModule\Tests\Concerns\BootsShopSchema;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * `catalog_visibility` had four values and one of them did nothing.
 *
 * `search` was written to the database and offered by the admin form, then
 * filtered out of every storefront query by a list that named only `visible`
 * and `catalog` - so a product set to it was unreachable from the shop and
 * unlistable, which is the exact opposite of what the value is for. `hidden`
 * behaved the other way: it was excluded from the catalog, but the product page
 * checked only publication state, so a hidden product's page, price and reviews
 * stayed online for anyone with the link.
 *
 * Worse, each surface carried its own copy of the list - the catalog query, the
 * homepage rails, the public JSON API - and `relatedProducts()` carried none at
 * all, so a hidden product could be advertised on another product's page. A
 * value that only some surfaces know about is a value no editor can reason
 * about, which is why the rules now live on the model and the surfaces read
 * them from there.
 */
final class CatalogVisibilityTest extends TestCase
{
    use BootsShopSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootShopSchema();
        $this->resetShopTables();

        // The checkout step's failure message goes through `__()`, and raising
        // it needs a validator to build the error bag from. The package harness
        // binds neither: `ValidateStock` has the same dependencies and has
        // never been executed by a test for exactly that reason. Both are real
        // objects here - these assertions are about which cart lines are
        // refused, not about translation wiring.
        $translator = new class implements Translator
        {
            public function get($key, array $replace = [], $locale = null)
            {
                foreach ($replace as $name => $value) {
                    $key = str_replace(':'.$name, (string) $value, $key);
                }

                return $key;
            }

            public function choice($key, $number, array $replace = [], $locale = null)
            {
                return $this->get($key, $replace, $locale);
            }

            public function getLocale()
            {
                return 'en';
            }

            public function setLocale($locale)
            {
                //
            }
        };

        Container::getInstance()->instance('translator', $translator);
        Container::getInstance()->singleton('validator', fn ($app) => new ValidationFactory($translator, $app));
    }

    // ---------------------------------------------------------------------
    // The four values, on the model.
    // ---------------------------------------------------------------------

    /**
     * The contract every storefront surface reads from.
     */
    public function test_the_visibility_sets_are_the_ones_the_admin_form_offers(): void
    {
        $this->assertSame(
            ['visible', 'catalog', 'search', 'hidden'],
            Product::CATALOG_VISIBILITIES,
            'The model is the catalogue of valid values. `StoreProductRequest` validates against the same four, and a value added to one without the other is a value an editor can set and the shop cannot read.'
        );
    }

    public function test_a_listing_carries_the_visible_and_catalog_products(): void
    {
        $this->seedOneOfEachVisibility();

        $listed = $this->listed();

        $this->assertSame(['catalog', 'visible'], $listed, 'A browse is a listing, so a search-only product has no place in it.');
    }

    public function test_a_search_carries_the_search_only_products_too(): void
    {
        $this->seedOneOfEachVisibility();

        $found = Product::query()->published()->inSearchResults()->orderBy('catalog_visibility')->pluck('catalog_visibility')->all();

        $this->assertSame(['catalog', 'search', 'visible'], $found, 'A shopper who typed a name is looking for that name; a search-only product that cannot be found by searching does not exist as a setting.');
    }

    public function test_no_query_ever_carries_a_hidden_product(): void
    {
        $this->seedOneOfEachVisibility();

        foreach (['inListing', 'inSearchResults', 'reachable'] as $scope) {
            $this->assertFalse(
                Product::query()->published()->{$scope}()->where('catalog_visibility', Product::HIDDEN)->exists(),
                "`{$scope}()` returned a hidden product."
            );
        }
    }

    /**
     * A saved list, a phone-order picker: surfaces where the product is already
     * known, so a search-only product stays and only `hidden` is dropped.
     */
    public function test_a_reachable_set_keeps_the_search_only_products(): void
    {
        $this->seedOneOfEachVisibility();

        $this->assertSame(
            ['catalog', 'search', 'visible'],
            Product::query()->published()->reachable()->orderBy('catalog_visibility')->pluck('catalog_visibility')->all()
        );
    }

    /**
     * A hidden product's own page is the leak. The catalog hid it, so a link
     * from a search engine, an old email, or a sitemap still rendered its name,
     * price, stock and reviews - which is more than a "hidden" product is
     * supposed to disclose at any URL.
     */
    public function test_a_hidden_product_is_not_reachable_by_direct_link(): void
    {
        $this->assertFalse(
            $this->product(['catalog_visibility' => 'hidden'])->isVisibleOnStorefront(),
            'A hidden product answered its own URL. `hidden` has to mean not there, not out of the sidebar.'
        );
    }

    /**
     * The counterpart, and the reason the gate cannot simply be `inListing()`:
     * a search-only product has no listing of its own, so the link a shopper
     * follows - from a search result, an email, an order - has to land
     * somewhere.
     */
    public function test_a_search_only_product_is_reachable_by_direct_link(): void
    {
        $this->assertTrue(
            $this->product(['catalog_visibility' => 'search'])->isVisibleOnStorefront(),
            'A search-only product must still resolve by URL, or the only route to it leads to a 404.'
        );
    }

    public function test_visibility_never_overrides_publication_state(): void
    {
        $cases = [
            'a draft' => ['status' => 'draft'],
            'a product whose unpublish date has passed' => ['unpublish_at' => now()->subDay()],
        ];

        foreach ($cases as $what => $attributes) {
            foreach (Product::CATALOG_VISIBILITIES as $visibility) {
                $product = $this->product($attributes + ['catalog_visibility' => $visibility]);

                $this->assertFalse(
                    $product->isVisibleOnStorefront(),
                    "{$what} set to `{$visibility}` was still visible on the storefront."
                );
            }
        }

        $this->assertTrue(
            $this->product(['unpublish_at' => now()->addDay()])->isVisibleOnStorefront(),
            'A product scheduled to unpublish tomorrow is still live until tomorrow.'
        );
    }

    // ---------------------------------------------------------------------
    // The cart and checkout, which take a product id rather than a page.
    // ---------------------------------------------------------------------

    public function test_an_unavailable_product_cannot_be_added_to_a_cart(): void
    {
        foreach (['hidden' => 'hidden', 'a draft' => 'draft'] as $what => $state) {
            $attributes = $state === 'draft'
                ? ['status' => 'draft']
                : ['catalog_visibility' => $state];

            $spy = new class
            {
                public bool $called = false;

                public function handle(CartContext $context, \Closure $next)
                {
                    $this->called = true;

                    return $next($context);
                }
            };

            $result = (new Pipeline)
                ->send($this->cartContext($this->product($attributes)))
                ->through([new ValidateProductAvailability, $spy])
                ->thenReturn();

            $this->assertSame('This product is no longer available.', $result->error, "A {$what} product was added to a cart.");
            $this->assertFalse($spy->called, "The cart pipeline kept going after rejecting {$what}.");
        }
    }

    public function test_a_reachable_product_still_reaches_the_rest_of_the_cart_pipeline(): void
    {
        $spy = new class
        {
            public bool $called = false;

            public function handle(CartContext $context, \Closure $next)
            {
                $this->called = true;

                return $next($context);
            }
        };

        foreach (['visible', 'catalog', 'search'] as $visibility) {
            $result = (new Pipeline)
                ->send($this->cartContext($this->product(['catalog_visibility' => $visibility])))
                ->through([new ValidateProductAvailability, $spy])
                ->thenReturn();

            $this->assertNull($result->error, "A `{$visibility}` product was refused by the cart.");
        }

        $this->assertTrue($spy->called, 'The step is not a blanket rejection.');
    }

    /**
     * A cart outlives a product. One added at 09:00 can be unpublished at 09:05
     * with the line still in the basket, and stock is already re-checked at
     * checkout for exactly that reason - availability has to be re-checked
     * beside it, or the order records a sale of a product the shop no longer
     * offers.
     */
    public function test_checkout_refuses_a_cart_whose_product_was_withdrawn(): void
    {
        $cart = $this->cartWith($this->product([
            'name' => 'Withdrawn Thing',
            'catalog_visibility' => 'hidden',
        ]));

        try {
            (new ValidateAvailability)->handle($this->checkoutContext($cart), fn ($context) => $context);

            $this->fail('Checkout accepted a cart containing a hidden product.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cart', $e->errors());
            $this->assertStringContainsString('no longer available', $e->errors()['cart'][0]);
            $this->assertStringContainsString(
                'Withdrawn Thing',
                $e->errors()['cart'][0],
                'The message has to name the product, or a shopper with three lines has no way to know which one to remove.'
            );
        }
    }

    public function test_checkout_still_accepts_a_healthy_cart(): void
    {
        $cart = $this->cartWith(
            $this->product(['name' => 'Listed Thing']),
            $this->product(['name' => 'Search Only', 'catalog_visibility' => 'search']),
        );

        $context = $this->checkoutContext($cart);
        $reached = false;

        (new ValidateAvailability)->handle($context, function ($passed) use (&$reached) {
            $reached = true;

            return $passed;
        });

        $this->assertTrue($reached, 'A cart of listed and search-only products was refused.');
    }

    // ---------------------------------------------------------------------
    // Wiring: the rules are registered where the write paths run.
    // ---------------------------------------------------------------------

    public function test_the_availability_step_runs_before_the_price_and_stock_work(): void
    {
        $cart = config('shop.pipelines.cart');
        $checkout = config('shop.pipelines.checkout');

        $this->assertSame(
            ValidateProductAvailability::class,
            $cart[0] ?? null,
            'The cart pipe must refuse an unavailable product first. Behind `ResolveUnitPrice` it would price a product that may not be bought.'
        );

        $this->assertSame(
            ValidateAvailability::class,
            $checkout[0] ?? null,
            'The checkout pipe must re-check availability before stock, so the shopper is told the product is withdrawn rather than that its stock is short.'
        );
    }

    public function test_the_cart_update_path_applies_the_same_rule_as_the_add_path(): void
    {
        $source = $this->source(CartController::class);

        $this->assertMatchesRegularExpression(
            '/if \(\$validated\[.quantity.\] <= 0\) \{.*?\} else \{.*?isPurchasable\(\).*?is no longer available/s',
            $source,
            '`updateItem()` keeps a line in the cart, which is a purchase. It bypasses the cart pipeline, so the rule has to be restated there - and the removal branch (quantity 0) has to stay outside it, or a shopper could not get rid of the product that is blocking them.'
        );
    }

    /**
     * Source-level, because the package has no request harness - the same
     * compromise `StorefrontProductExposureTest` documents. These fail only if
     * someone reintroduces a private copy of the rule, which is the drift this
     * test exists to prevent.
     */
    public function test_no_surface_keeps_its_own_copy_of_the_visibility_list(): void
    {
        foreach ([StorefrontController::class, ProductsController::class] as $controller) {
            $source = $this->source($controller);

            $this->assertDoesNotMatchRegularExpression(
                "/(whereIn|whereNotIn)\('catalog_visibility',\s*\[/",
                $source,
                "{$controller} still hardcodes a visibility list. Read it from the model (`inListing()` / `inSearchResults()` / the visibility constants) so a new value cannot be missing from one surface and present in another."
            );
        }
    }

    public function test_the_catalog_widens_to_search_only_products_only_when_a_search_is_asked_for(): void
    {
        $source = $this->source(StorefrontController::class);

        $this->assertStringContainsString(
            "return \$request->filled('search')\n            ? Product::SEARCH_VISIBILITIES\n            : Product::LISTING_VISIBILITIES;",
            $source,
            'The wider set is earned by the search term, not by the URL. Widen it unconditionally and a search-only product is browseable by category and paged through - the behaviour the value exists to prevent.'
        );

        $this->assertSame(
            2,
            substr_count($source, '$this->visibilitiesFor($request)'),
            'Both catalog entry points (the catalog page and a category page) take the visibility set from the request; a third entry point added later must too.'
        );
    }

    /**
     * Every storefront query has a visibility rule, and only the catalog
     * builder is allowed to take one as an argument.
     *
     * Written as an invariant rather than a count of call sites: the previous
     * `relatedProducts()` used `Product::published()` with no visibility filter
     * at all, and a tally of the right number of calls would have passed
     * anyway. What matters is that a `published()` chain cannot appear here
     * without a rule attached.
     */
    public function test_every_storefront_query_carries_a_visibility_rule(): void
    {
        $source = $this->source(StorefrontController::class);

        preg_match_all('/Product::published\(\)\s*(?!\s*->inListing\(\))/', $source, $unscoped);

        $this->assertCount(
            1,
            $unscoped[0],
            'Every `published()` chain in the storefront has to either scope itself with `inListing()` or be the catalog builder, which is handed its visibility set. An unscoped one is a surface that forgot the rule.'
        );

        $this->assertMatchesRegularExpression(
            "/protected function baseQuery\(array \\\$visibilities = Product::LISTING_VISIBILITIES\): EloquentBuilder\s*\{\s*return Product::published\(\)\s*->whereIn\('catalog_visibility', \\\$visibilities\)/",
            $source,
            'The one unscoped query is the catalog builder, and it must filter on the set it is given rather than on a literal.'
        );
    }

    public function test_the_public_api_reads_the_same_rule_as_the_storefront(): void
    {
        $source = $this->source(ProductsController::class);

        $this->assertStringContainsString(
            'Product::SEARCH_VISIBILITIES',
            $source,
            'The API takes a search term, so a search-only product must be findable through it exactly as in the shop.'
        );
        $this->assertStringContainsString('Product::LISTING_VISIBILITIES', $source);
    }

    /**
     * The two surfaces that are neither a browse nor a search, and the two
     * write paths that were trusting a posted id.
     *
     * `OrdersController` is filtered on the picker only. Staff are deliberately
     * still allowed to record a phone order for a withdrawn product - that is a
     * real job, and refusing it needs a policy decision and an override, not a
     * scope. Pinned here so a later reader can see the boundary was drawn on
     * purpose rather than missed.
     */
    public function test_the_saved_and_picker_surfaces_drop_only_hidden_products(): void
    {
        foreach ([WishlistController::class, OrdersController::class] as $controller) {
            $source = $this->source($controller);

            $this->assertMatchesRegularExpression(
                '/Product::(published\(\)|whereIn\([^\n]+\)\s*\n?\s*->published\(\))\s*\n\s*->reachable\(\)/',
                $source,
                "{$controller} lists products without the reachable set, so a hidden product is offered on a surface the editor asked to keep it off."
            );
        }
    }

    // ---------------------------------------------------------------------
    // Helpers.
    // ---------------------------------------------------------------------

    /** @return list<string> */
    private function listed(): array
    {
        return Product::query()->published()->inListing()->orderBy('catalog_visibility')->pluck('catalog_visibility')->all();
    }

    /**
     * One published product per visibility value, so a scope assertion reads as
     * a set comparison rather than "the row I just inserted came back".
     */
    private function seedOneOfEachVisibility(): void
    {
        foreach (Product::CATALOG_VISIBILITIES as $visibility) {
            $this->product(['catalog_visibility' => $visibility]);
        }
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

    private function cartContext(Product $product): CartContext
    {
        return new CartContext(
            cart: $this->cartWith(),
            product: $product,
            variation: null,
            quantity: 1,
            existingItem: null,
            variantLabel: null,
            totalQuantity: 1,
        );
    }

    private function cartWith(Product ...$products): Cart
    {
        $cart = Cart::create(['session_id' => uniqid('sess_', true)]);

        foreach ($products as $product) {
            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => (float) $product->price,
                'currency' => 'USD',
            ]);
        }

        return $cart->fresh('items.product');
    }

    private function checkoutContext(Cart $cart): CheckoutContext
    {
        return new CheckoutContext(cart: $cart, validated: [], user: null);
    }

    private function source(string $class): string
    {
        return (string) file_get_contents((new ReflectionClass($class))->getFileName());
    }
}
