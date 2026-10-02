<?php

namespace Tests\Feature\Modules\Shop;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\ShopModule\Models\Attribute;
use Nreljed\ShopModule\Models\AttributeTerm;
use Nreljed\ShopModule\Models\ShippingMethod;
use Nreljed\ShopModule\Models\ShippingZone;
use Tests\BootsFeatures;
use Tests\TestCase;

/**
 * Shipping zones and attributes render through the kernel resource path
 * (nreljed/platform 0.1.70 kit: the lazy-options `select` and the `nested`
 * field). These assertions are the render smoke for the 0.1.74 conversion:
 * the controllers hand ResourceRenderer a definition, and the kernel pages are
 * what an admin actually gets.
 */
class ShopNestedResourceRenderTest extends TestCase
{
    use BootsFeatures, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFeatures();

        $this->admin = User::factory()->create(['email' => 'nested-admin@test.com']);
        $this->admin->assignRole('admin');
    }

    public function test_shipping_zones_index_renders_the_kernel_resource_page(): void
    {
        ShippingZone::create(['name' => 'Europe Zone', 'countries' => ['DE'], 'is_active' => true]);

        $this->actingAs($this->admin)
            ->get(route('dashboard.shop.shipping-zones.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard/admin/resources/index', false)
                ->where('resource.key', 'shop.shipping-zones')
                ->where('resource.label', 'Shipping Zones')
                ->where('resource.table.columns.0.name', 'name')
            );
    }

    public function test_shipping_zone_create_renders_the_form_with_nested_method_rows(): void
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard.shop.shipping-zones.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard/admin/resources/form', false)
                ->where('resource.key', 'shop.shipping-zones')
                ->where('record', null)
                ->where('resource.form.1.fields.0.name', 'methods')
                ->where('resource.form.1.fields.0.type', 'nested')
                // The method picker cannot ship static options (descriptors are
                // DB-free), so it must lazy-load the catalog.
                ->where('resource.form.1.fields.0.rows.0.type', 'select')
                ->where('resource.form.1.fields.0.rows.0.optionsUrl', 'dashboard.shop.shipping-methods.options')
            );
    }

    public function test_shipping_zone_edit_seeds_the_nested_rows_from_the_methods_pivot(): void
    {
        $zone = ShippingZone::create(['name' => 'Zone With Methods', 'countries' => ['FR'], 'is_active' => true]);
        $method = ShippingMethod::create(['name' => 'Zone Express', 'price' => 12, 'is_active' => true, 'sort_order' => 1]);
        $zone->methods()->attach($method->id, ['override_price' => 9.5]);

        $this->actingAs($this->admin)
            ->get(route('dashboard.shop.shipping-zones.edit', $zone))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard/admin/resources/form', false)
                ->where('record.name', 'Zone With Methods')
                ->where('record.countries', ['FR'])
                ->where('record.methods.0.id', $method->id)
                ->where('record.methods.0.override_price', 9.5)
            );
    }

    public function test_the_method_options_endpoint_feeds_the_nested_picker(): void
    {
        ShippingMethod::create(['name' => 'Pickable', 'price' => 4, 'is_active' => true, 'sort_order' => 1]);
        ShippingMethod::create(['name' => 'Hidden', 'price' => 9, 'is_active' => false, 'sort_order' => 2]);

        $this->actingAs($this->admin)
            ->get(route('dashboard.shop.shipping-methods.options'))
            ->assertOk()
            ->assertJsonFragment(['label' => 'Pickable'])
            ->assertJsonMissing(['label' => 'Hidden']);
    }

    public function test_shipping_zone_store_syncs_the_nested_method_rows_to_the_pivot(): void
    {
        $standard = ShippingMethod::create(['name' => 'Standard', 'price' => 5, 'is_active' => true, 'sort_order' => 1]);
        $express = ShippingMethod::create(['name' => 'Express', 'price' => 15, 'is_active' => true, 'sort_order' => 2]);

        $this->actingAs($this->admin)
            ->post(route('dashboard.shop.shipping-zones.store'), [
                'name' => 'Nested Zone',
                'countries' => ['IT'],
                'methods' => [
                    ['id' => $express->id, 'override_price' => 11],
                    ['id' => $standard->id, 'override_price' => null],
                ],
            ])
            ->assertRedirect(route('dashboard.shop.shipping-zones.index'));

        $this->assertDatabaseHas('shipping_zones', ['name' => 'Nested Zone']);

        $zone = ShippingZone::where('name', 'Nested Zone')->firstOrFail();

        $this->assertSame(2, $zone->methods()->count());
        $this->assertSame('11', (string) $zone->methods()->find($express->id)->pivot->override_price);
        $this->assertNull($zone->methods()->find($standard->id)->pivot->override_price);
    }

    public function test_shipping_zone_update_removes_methods_dropped_from_the_rows(): void
    {
        $zone = ShippingZone::create(['name' => 'Sync Zone', 'countries' => ['ES'], 'is_active' => true]);
        $keep = ShippingMethod::create(['name' => 'Keep', 'price' => 5, 'is_active' => true, 'sort_order' => 1]);
        $drop = ShippingMethod::create(['name' => 'Drop', 'price' => 6, 'is_active' => true, 'sort_order' => 2]);
        $zone->methods()->attach([$keep->id => ['override_price' => null], $drop->id => ['override_price' => 3]]);

        $this->actingAs($this->admin)
            ->put(route('dashboard.shop.shipping-zones.update', $zone), [
                'name' => 'Sync Zone Renamed',
                'countries' => ['ES'],
                'methods' => [['id' => $keep->id, 'override_price' => 7]],
            ])
            ->assertRedirect(route('dashboard.shop.shipping-zones.index'));

        $zone->refresh()->load('methods');

        $this->assertSame('Sync Zone Renamed', $zone->name);
        $this->assertSame([$keep->id], $zone->methods->pluck('id')->all());
        $this->assertSame('7', (string) $zone->methods->first()->pivot->override_price);
    }

    public function test_attributes_index_renders_the_kernel_resource_page(): void
    {
        Attribute::create(['name' => 'Colour', 'display_type' => 'color', 'sort_order' => 0, 'is_active' => true]);

        $this->actingAs($this->admin)
            ->get(route('dashboard.shop.attributes.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard/admin/resources/index', false)
                ->where('resource.key', 'shop.attributes')
                ->where('resource.table.columns.2.name', 'terms_count')
            );
    }

    public function test_attribute_create_renders_nested_term_rows(): void
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard.shop.attributes.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard/admin/resources/form', false)
                ->where('resource.key', 'shop.attributes')
                ->where('resource.form.1.fields.0.name', 'terms')
                ->where('resource.form.1.fields.0.type', 'nested')
                ->where('resource.form.1.fields.0.rowTitleField', 'name')
            );
    }

    public function test_attribute_edit_seeds_term_rows_in_persisted_order(): void
    {
        $attribute = Attribute::create(['name' => 'Size', 'display_type' => 'text', 'sort_order' => 1, 'is_active' => true]);
        $small = $attribute->terms()->create(['name' => 'Small', 'sort_order' => 0]);
        $large = $attribute->terms()->create(['name' => 'Large', 'sort_order' => 1]);

        $this->actingAs($this->admin)
            ->get(route('dashboard.shop.attributes.edit', $attribute))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard/admin/resources/form', false)
                ->where('record.terms.0.id', $small->id)
                ->where('record.terms.0.name', 'Small')
                ->where('record.terms.1.name', 'Large')
            );
    }

    public function test_attribute_update_diffs_terms_keep_create_and_delete(): void
    {
        $attribute = Attribute::create(['name' => 'Material', 'display_type' => 'text', 'sort_order' => 2, 'is_active' => true]);
        $keep = $attribute->terms()->create(['name' => 'Cotton', 'sort_order' => 0]);
        $remove = $attribute->terms()->create(['name' => 'Linen', 'sort_order' => 1]);

        $this->actingAs($this->admin)
            ->put(route('dashboard.shop.attributes.update', $attribute), [
                'name' => 'Material',
                'display_type' => 'text',
                'terms' => [
                    ['id' => $remove->id, 'name' => 'Linen', 'swatch_value' => ''],
                    ['id' => $keep->id, 'name' => 'Organic Cotton', 'swatch_value' => '#ffffff'],
                ],
            ])
            ->assertRedirect(route('dashboard.shop.attributes.index'));

        $terms = $attribute->terms()->orderBy('sort_order')->get();

        // Array order is the persisted order — the drag ordering survives.
        $this->assertSame(['Linen', 'Organic Cotton'], $terms->pluck('name')->all());
        $this->assertSame('#ffffff', $terms->last()->swatch_value);
        $this->assertNull($terms->first()->swatch_value);
    }

    public function test_attribute_update_creates_new_term_rows_and_deletes_removed_ones(): void
    {
        $attribute = Attribute::create(['name' => 'Fit', 'display_type' => 'text', 'sort_order' => 3, 'is_active' => true]);
        $stale = $attribute->terms()->create(['name' => 'Stale', 'sort_order' => 0]);

        $this->actingAs($this->admin)
            ->put(route('dashboard.shop.attributes.update', $attribute), [
                'name' => 'Fit',
                'display_type' => 'text',
                'terms' => [['name' => 'Slim'], ['name' => 'Regular']],
            ])
            ->assertRedirect(route('dashboard.shop.attributes.index'));

        $this->assertDatabaseMissing('attribute_terms', ['id' => $stale->id]);
        $this->assertSame(['Slim', 'Regular'], $attribute->terms()->orderBy('sort_order')->pluck('name')->all());
    }

    public function test_a_term_row_without_a_name_is_rejected(): void
    {
        $attribute = Attribute::create(['name' => 'Colour', 'display_type' => 'color', 'sort_order' => 4, 'is_active' => true]);

        $existing = $attribute->terms()->create(['name' => 'Blue', 'sort_order' => 0]);

        $this->actingAs($this->admin)
            ->from(route('dashboard.shop.attributes.edit', $attribute))
            ->put(route('dashboard.shop.attributes.update', $attribute), [
                'name' => 'Colour',
                'display_type' => 'color',
                'terms' => [['name' => 'Red'], ['name' => '']],
            ])
            ->assertRedirect(route('dashboard.shop.attributes.edit', $attribute))
            ->assertSessionHasErrors('terms.1.name');

        // Validation gates the whole diff: a rejected batch must not half-write,
        // so the persisted term is untouched rather than partially synced.
        $this->assertSame(['Blue'], $attribute->terms()->pluck('name')->all());
        $this->assertDatabaseHas('attribute_terms', ['id' => $existing->id, 'name' => 'Blue']);
    }

    public function test_attributes_destroy_uses_the_record_route_param(): void
    {
        $attribute = Attribute::create(['name' => 'Obsolete', 'display_type' => 'text', 'sort_order' => 5, 'is_active' => true]);
        $attribute->terms()->create(['name' => 'Term', 'sort_order' => 0]);

        $this->actingAs($this->admin)
            ->delete(route('dashboard.shop.attributes.destroy', $attribute));

        $this->assertDatabaseMissing('attributes', ['id' => $attribute->id]);
    }

    public function test_a_guest_cannot_reach_the_converted_screens(): void
    {
        $this->get(route('dashboard.shop.shipping-zones.index'))->assertRedirect();
        $this->get(route('dashboard.shop.attributes.index'))->assertRedirect();
        $this->get(route('dashboard.shop.shipping-methods.options'))->assertRedirect();
    }

    public function test_terms_listing_endpoint_still_serves_the_variation_builder(): void
    {
        $attribute = Attribute::create(['name' => 'Style', 'display_type' => 'button', 'sort_order' => 6, 'is_active' => true]);
        $term = AttributeTerm::create(['attribute_id' => $attribute->id, 'name' => 'Modern', 'sort_order' => 0]);

        $this->actingAs($this->admin)
            ->get(route('dashboard.shop.attributes.terms', $attribute))
            ->assertOk()
            ->assertJsonFragment(['id' => $term->id, 'name' => 'Modern']);
    }
}
