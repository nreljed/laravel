<?php

namespace Tests\Feature\Modules\Shop;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Nreljed\ShopModule\Models\Cart;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ShippingMethod;
use Tests\BootsFeatures;
use Tests\TestCase;

/**
 * The storefront had two contradictory answers about the same product.
 *
 * `catalog_visibility` stores four values and the admin form offers all four,
 * but the shop only ever asked about two of them. `search` was filtered out of
 * every storefront query by a list naming `visible` and `catalog`, so a product
 * set to it was unlistable and unfindable - unreachable from the shop, which is
 * not what a value called "search" is for. `hidden` went the other way: it was
 * excluded from the catalog, but `show()` read only `status` and `unpublish_at`,
 * so the product's own page still rendered its name, price, stock and reviews
 * to anyone holding the link - more than "hidden" discloses at any URL.
 *
 * Both directions were invisible from the outside. The form saved the value and
 * the page looked fine; the wrong answer only appeared to a shopper who went
 * looking for a product that was there. These tests drive the real routes,
 * because the package suite runs on a bare kernel where no middleware, route
 * group or controller body executes - a query-level test cannot tell whether
 * `/store/{product}` 404s, only whether a method returns false.
 *
 * The rules being pinned:
 *   visible, catalog - listed, searchable, reachable by link, buyable
 *   search           - searchable and reachable, never listed, buyable
 *   hidden           - nowhere, including a 404 at its own URL, not buyable
 */
class StorefrontCatalogVisibilityTest extends TestCase
{
    use BootsFeatures, RefreshDatabase;

    private User $shopper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootFeatures();

        $this->shopper = $this->createUserWithRole('customer');
    }

    // ---------------------------------------------------------------------
    // Listing, searching, direct link.
    // ---------------------------------------------------------------------

    public function test_a_search_only_product_is_not_listed_but_is_findable(): void
    {
        $needle = $this->product(['name' => 'Unlisted Kettle', 'catalog_visibility' => 'search']);
        $this->product(['name' => 'Listed Kettle']);

        $this->assertGridMissing(
            'Unlisted Kettle',
            [],
            'A search-only product in a browse is unlistable again - the state the value was meant to fix.'
        );

        $this->assertGridContains(
            'Unlisted Kettle',
            ['search' => 'Kettle'],
            'A search-only product that cannot be found by searching does not exist as a setting.'
        );

        $this->get(route('store.show', $needle))->assertOk();
    }

    public function test_a_hidden_product_is_gone_from_everywhere_including_its_own_page(): void
    {
        $hidden = $this->product(['name' => 'Secret Kettle', 'catalog_visibility' => 'hidden']);

        $this->assertGridMissing('Secret Kettle');
        $this->assertGridMissing('Secret Kettle', ['search' => 'Kettle']);
        $this->get(route('store.show', $hidden))->assertNotFound();
    }

    public function test_a_hidden_product_is_never_advertised_on_another_products_page(): void
    {
        $visible = $this->product(['name' => 'Anchor Kettle']);
        $hidden = $this->product(['name' => 'Secret Kettle', 'catalog_visibility' => 'hidden']);

        // Four products, so the related rail's four slots have to be filled from
        // the three that are actually listed - and it used to fill them from all
        // of them, because `relatedProducts()` carried no visibility filter.
        $this->product(['name' => 'Second Kettle']);
        $this->product(['name' => 'Third Kettle']);
        $this->product(['name' => 'Fourth Kettle']);
        $this->product(['name' => 'Search Only Kettle', 'catalog_visibility' => 'search']);

        $this->get(route('store.show', $visible))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('related', fn (Collection $related) => $related
                    ->pluck('name')
                    ->intersect(['Secret Kettle', 'Search Only Kettle'])
                    ->isEmpty())
            );
    }

    public function test_the_homepage_rails_carry_listed_products_only(): void
    {
        $this->product(['name' => 'Featured Listed', 'is_featured' => true]);
        $this->product(['name' => 'Featured Hidden', 'catalog_visibility' => 'hidden', 'is_featured' => true]);
        $this->product(['name' => 'Featured Search Only', 'catalog_visibility' => 'search', 'is_featured' => true]);

        $this->get(route('store.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('homepage.featured', fn (Collection $featured) => $featured
                    ->pluck('name')
                    ->sort()
                    ->values()
                    ->all() === ['Featured Listed'])
            );
    }

    // ---------------------------------------------------------------------
    // The public feed agrees with the shop.
    // ---------------------------------------------------------------------

    public function test_the_api_and_the_shop_answer_alike_about_reachability(): void
    {
        $searchOnly = $this->product(['name' => 'Feed Kettle', 'catalog_visibility' => 'search']);
        $hidden = $this->product(['name' => 'Feed Secret', 'catalog_visibility' => 'hidden']);

        // The API used to hold a narrower list of its own, so a search-only
        // product was readable in the shop and 404'd here: the same product, two
        // answers, decided by which URL the caller had.
        $this->get(route('api.products.show', $searchOnly))->assertOk();
        $this->get(route('api.products.show', $hidden))->assertNotFound();

        $this->assertSame(
            ['Feed Kettle'],
            $this->apiNames(['search' => 'Kettle']),
            'A public feed answers a search the way the shop does.'
        );

        $this->assertSame(
            [],
            $this->apiNames(),
            'A feed listing carries the listing set. A search term is what earns the wider one.'
        );
    }

    // ---------------------------------------------------------------------
    // It cannot be bought.
    //
    // The storefront page 404ing was never a lock on the cart: `add-item` takes
    // a bare `product_id` from an anonymous caller, and the cart pipeline
    // checked the variant, the price and the stock - never whether the shop
    // still offered the product.
    // ---------------------------------------------------------------------

    public function test_an_unavailable_product_cannot_be_added_to_a_cart(): void
    {
        foreach (['hidden' => 'hidden', 'a draft' => 'draft'] as $what => $state) {
            $product = $this->product([
                'name' => 'Cart Kettle',
                'catalog_visibility' => $state === 'draft' ? 'visible' : $state,
                'status' => $state === 'draft' ? 'draft' : 'published',
            ]);

            $this->post(route('store.cart.add-item'), [
                'product_id' => $product->id,
                'quantity' => 1,
            ])->assertStatus(422);

            // Nothing reached the cart: `add-item` takes a bare product_id from
            // an anonymous caller, so a 422 is the only thing standing between a
            // withdrawn product and an order for it.
            $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
        }
    }

    public function test_a_search_only_product_stays_buyable(): void
    {
        $product = $this->product(['name' => 'Buyable Kettle', 'catalog_visibility' => 'search']);

        $this->actingAs($this->shopper)
            ->post(route('store.cart.add-item'), ['product_id' => $product->id, 'quantity' => 2])
            ->assertOk();

        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 2]);
    }

    public function test_a_cart_quantity_cannot_be_raised_on_a_withdrawn_product(): void
    {
        $product = $this->product(['name' => 'Withdrawn Kettle']);
        ShippingMethod::create(['name' => 'Standard', 'price' => 5, 'is_active' => true, 'sort_order' => 1]);

        $cart = Cart::create(['user_id' => $this->shopper->id]);
        $item = $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 20,
        ]);

        $product->update(['catalog_visibility' => 'hidden']);

        $this->actingAs($this->shopper)
            ->put(route('store.cart.update-item', $item), ['quantity' => 5])
            ->assertStatus(422);

        // A rejected update must not have changed the line it rejected.
        $this->assertDatabaseHas('cart_items', [
            'id' => $item->id,
            'quantity' => 1,
        ]);

        // Removing it has to keep working, or the shopper is stuck holding the
        // one line they are being told to remove.
        $this->actingAs($this->shopper)
            ->put(route('store.cart.update-item', $item), ['quantity' => 0])
            ->assertOk();

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_checkout_refuses_a_cart_whose_product_was_withdrawn_after_it_was_added(): void
    {
        $product = $this->product(['name' => 'Late Withdrawal Kettle']);
        ShippingMethod::create(['name' => 'Standard', 'price' => 5, 'is_active' => true, 'sort_order' => 1]);

        $cart = Cart::create(['user_id' => $this->shopper->id]);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 20]);

        $product->update(['catalog_visibility' => 'hidden']);

        $address = [
            'first_name' => 'Nemo',
            'last_name' => 'Client',
            'address_line1' => '12 Rue X',
            'city' => 'Tunis',
            'country' => 'TN',
            'postcode' => '1000',
        ];

        $this->actingAs($this->shopper)
            ->post(route('store.checkout.store'), [
                'shipping_method_id' => ShippingMethod::first()->id,
                'payment_method' => 'manual',
                'billing_address' => $address,
                'shipping_address' => $address,
            ])
            ->assertSessionHasErrors('cart');

        // No order at all: the product was withdrawn, so there is nothing to sell.
        $this->assertDatabaseMissing('orders', [
            'user_id' => $this->shopper->id,
        ]);
    }

    // ---------------------------------------------------------------------
    // Helpers.
    // ---------------------------------------------------------------------

    /**
     * Product names on the catalog grid, for the given query string.
     *
     * Asserted against the rendered page rather than the props array: the grid
     * prop is a serialised paginator, so a test that reaches into
     * `props.products.data` is coupled to a shape the controller is free to
     * change, and it fails with `null` instead of a diagnosis.
     */
    private function assertGridContains(string $name, array $query = [], string $message = ''): void
    {
        $this->get(route('store.products', $query))
            ->assertOk()
            ->assertSee($name, false, $message !== '' ? $message : "`{$name}` is missing from the catalog grid for ".json_encode($query));
    }

    private function assertGridMissing(string $name, array $query = [], string $message = ''): void
    {
        $this->get(route('store.products', $query))
            ->assertOk()
            ->assertDontSee($name, false, $message !== '' ? $message : "`{$name}` is in the catalog grid for ".json_encode($query));
    }

    /**
     * @return list<string>
     */
    private function apiNames(array $query = []): array
    {
        return collect($this->getJson(route('api.products.index', $query))->json('data'))
            ->pluck('name')
            ->all();
    }

    private function product(array $attributes = []): Product
    {
        return Product::create($attributes + [
            'name' => 'Kettle',
            'slug' => 'kettle-'.uniqid(),
            'status' => 'published',
            'type' => 'simple',
            'catalog_visibility' => 'visible',
            'price' => 20,
            'stock_quantity' => 10,
            'track_stock' => false,
        ]);
    }
}
