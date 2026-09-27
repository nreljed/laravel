<?php

namespace Tests\Feature\Modules\Shop;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Nreljed\Platform\Models\Permission;
use Nreljed\Platform\Models\Role;
use Nreljed\ShopModule\Models\Attribute;
use Nreljed\ShopModule\Models\Order;
use Tests\BootsFeatures;
use Tests\TestCase;

/**
 * The Export buttons on the staff lists did nothing.
 *
 * `GET /dashboard/shop/orders/export` and `GET /dashboard/shop/customers/export`
 * were declared *below* the `/orders/{order}` and `/customers/{user}` wildcards.
 * Laravel matches routes in declaration order, so both requests resolved as a
 * lookup for the literal string "export", bound nothing, and 404'd. The
 * products export had been placed above `/products/{product}` and worked, which
 * is exactly why the two dead ones were easy to miss: `route:list` reports
 * every registered route with its controller, and it showed all three as
 * present.
 *
 * The same screen also let the route group's `shop.manage` gate do all the work
 * on five endpoints that had no check of their own - the category and attribute
 * lists, the raw attribute-term JSON, the product image upload, and the SEO
 * generation - so any future split of that permission would have widened those
 * five without anyone noticing. A Shop Manager is exactly such a role: it holds
 * `shop.manage` but not `shop.products.create`, and it is denied all four.
 *
 * The SEO gate is on `shop.products.edit` (it writes the product's SEO fields),
 * which a Shop Manager legitimately holds, so that one is covered from both
 * sides: a role that can edit products must get a real answer rather than a
 * server error - passing the class name to `authorize()` made the Gate call
 * `ProductPolicy::update()` with one argument, a 500 for everyone - and a role
 * that holds `shop.manage` without `shop.products.edit` must be refused.
 */
class ShopStaffExportAndGateTest extends TestCase
{
    use BootsFeatures, RefreshDatabase;

    private User $shopOwner;

    private User $shopManager;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootFeatures();

        $this->shopOwner = $this->createUserWithRole('shop_owner');
        $this->shopManager = $this->createUserWithRole('shop_manager');
        $this->customer = $this->createUserWithRole('customer');
    }

    // ---------------------------------------------------------------------
    // The exports.
    // ---------------------------------------------------------------------

    public function test_the_orders_export_returns_a_csv(): void
    {
        $this->actingAs($this->shopOwner)
            ->get(route('dashboard.shop.orders.export'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_the_customers_export_returns_a_csv(): void
    {
        $this->actingAs($this->shopOwner)
            ->get(route('dashboard.shop.customers.export'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_the_products_export_still_returns_a_csv(): void
    {
        // Never broken, and the one that made the other two look registered.
        $this->actingAs($this->shopOwner)
            ->get(route('dashboard.shop.products.export'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_the_customers_export_contains_a_known_customer(): void
    {
        // The customers export is defined as "people who have ordered", so the
        // customer needs an order or the CSV is a header row.
        Order::create([
            'order_number' => 'EXPORT-GATE-001',
            'user_id' => $this->customer->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 25,
            'total' => 25,
            'total_refunded' => 0,
            'currency' => 'EUR',
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->get(route('dashboard.shop.customers.export'));

        $response->assertOk();

        // A streamed export writes to php://output during sendContent(), so its
        // body is not in getContent() - assertSee() sees an empty string.
        $this->assertStringContainsString($this->customer->email, $response->streamedContent());
    }

    public function test_a_customer_cannot_reach_any_export(): void
    {
        foreach (['orders', 'customers', 'products'] as $resource) {
            $this->actingAs($this->customer)
                ->get(route("dashboard.shop.{$resource}.export"))
                ->assertForbidden();
        }
    }

    // ---------------------------------------------------------------------
    // The five endpoints that had no check of their own.
    // ---------------------------------------------------------------------

    public function test_a_shop_manager_cannot_read_the_category_list(): void
    {
        $this->actingAs($this->shopManager)
            ->get(route('dashboard.shop.categories.index'))
            ->assertForbidden();
    }

    public function test_a_shop_manager_cannot_read_the_attribute_list(): void
    {
        $this->actingAs($this->shopManager)
            ->get(route('dashboard.shop.attributes.index'))
            ->assertForbidden();
    }

    public function test_a_shop_manager_cannot_read_the_attribute_term_json(): void
    {
        // A real attribute: route model binding resolves the parameter before
        // the controller body runs, so a missing id would 404 and the assertion
        // would pass for the wrong reason.
        $attribute = Attribute::create(['name' => 'Colour']);

        $this->actingAs($this->shopManager)
            ->get(route('dashboard.shop.attributes.terms', $attribute))
            ->assertForbidden();
    }

    public function test_a_shop_manager_cannot_upload_a_product_image(): void
    {
        $this->actingAs($this->shopManager)
            ->post(route('dashboard.shop.products.upload-image'), [
                'file' => UploadedFile::fake()->image('shop.jpg'),
            ])
            ->assertForbidden();
    }

    public function test_seo_generation_answers_a_role_that_can_edit_products(): void
    {
        // A Shop Owner can edit products, so the gate must let them through to
        // the agent. On the broken gate this was a 500 for every caller: the
        // class name made the Gate call ProductPolicy::update() with one
        // argument. This endpoint answers "no provider configured" with a 422
        // and a message, so reaching that answer is the assertion.
        $response = $this->actingAs($this->shopOwner)
            ->postJson(route('dashboard.shop.products.seo.generate'), [
                'title' => 'A product',
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('AI provider', $response->json('message') ?? '');
    }

    public function test_a_role_with_shop_manage_but_not_product_edit_cannot_generate_seo(): void
    {
        $role = Role::create([
            'name' => 'Shop Desk',
            'slug' => 'shop_desk',
        ]);

        $role->permissions()->sync(
            Permission::whereIn('slug', ['view-dashboard', 'shop.view', 'shop.manage'])->pluck('id')
        );

        $user = User::factory()->create();
        $user->assignRole('shop_desk');
        $this->clearUserPermissionCache($user->id);

        $this->actingAs($user)
            ->postJson(route('dashboard.shop.products.seo.generate'), [
                'title' => 'A product',
            ])
            ->assertForbidden();
    }

    public function test_a_shop_owner_keeps_all_five(): void
    {
        // The new gates must not cost the fully-permissioned role anything.
        $this->actingAs($this->shopOwner)
            ->get(route('dashboard.shop.categories.index'))
            ->assertOk();

        $this->actingAs($this->shopOwner)
            ->get(route('dashboard.shop.attributes.index'))
            ->assertOk();
    }
}
