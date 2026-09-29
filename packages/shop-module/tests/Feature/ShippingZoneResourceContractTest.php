<?php

namespace Nreljed\ShopModule\Tests\Feature;

use Nreljed\Platform\Support\ResourceDefinition;
use Nreljed\ShopModule\Resources\ShippingZoneResource;
use Nreljed\ShopModule\ShopServiceProvider;
use Nreljed\ShopModule\Tests\Concerns\BootsFeatureRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Plan 6.1 wave-10: Shipping zones on the kernel resource path.
 */
final class ShippingZoneResourceContractTest extends TestCase
{
    use BootsFeatureRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootFeatureRegistry();
        $this->registerFeatureProvider(new ShopServiceProvider($this->container), ShopServiceProvider::class);
        $this->enableFeature('shop');
    }

    public function test_the_shop_provider_ships_a_valid_shipping_zones_descriptor(): void
    {
        $definition = collect($this->registry()->getAllAdminResources())->first(
            fn (ResourceDefinition $resource): bool => $resource->getKey() === ShippingZoneResource::KEY,
        );

        $this->assertInstanceOf(ResourceDefinition::class, $definition);
        $this->assertSame('Shipping Zones', $definition->getLabel());
        $this->assertSame('Shipping Zone', $definition->getSingular());
        $this->assertSame([], $definition->violations());
        $definition->assertValid('shop');
    }

    public function test_shipping_zones_declares_the_six_kernel_route_names_in_order(): void
    {
        $this->assertSame(
            [
                'dashboard.shop.shipping-zones.index',
                'dashboard.shop.shipping-zones.create',
                'dashboard.shop.shipping-zones.store',
                'dashboard.shop.shipping-zones.edit',
                'dashboard.shop.shipping-zones.update',
                'dashboard.shop.shipping-zones.destroy',
            ],
            array_values(ShippingZoneResource::definition()->getRoutes()),
        );
    }

    public function test_the_country_catalog_is_a_static_bounded_enum(): void
    {
        $field = collect(ShippingZoneResource::definition()->getForm())
            ->flatMap(fn (array $section): array => $section['fields'])
            ->first(fn (array $field): bool => ($field['name'] ?? null) === 'countries');

        $this->assertIsArray($field);
        $this->assertSame('relation', $field['type']);
        $this->assertArrayNotHasKey('optionsUrl', $field, 'The country catalog is a bounded enum, so it must ship as static options.');
        $this->assertGreaterThanOrEqual(27, $field['options'], 'The country enum must be the same bounded catalog the old form shipped.');
        $this->assertSame('DE', $field['options'][0]['value']);
    }

    public function test_the_method_rows_pick_from_the_lazy_method_catalog(): void
    {
        $nested = collect(ShippingZoneResource::definition()->getForm())
            ->flatMap(fn (array $section): array => $section['fields'])
            ->first(fn (array $field): bool => ($field['name'] ?? null) === 'methods');

        $this->assertIsArray($nested);
        $this->assertSame('nested', $nested['type']);

        $select = collect($nested['rows'])->first(fn (array $row): bool => $row['name'] === 'id');

        $this->assertIsArray($select);
        $this->assertSame('select', $select['type']);
        $this->assertSame('dashboard.shop.shipping-methods.options', $select['optionsUrl']);
        $this->assertArrayNotHasKey('options', $select, 'Methods are not a bounded enum; the nested select must fetch its catalog lazily via optionsUrl.');
    }

    public function test_shipping_zones_permission_slots_map_to_existing_shop_rbac(): void
    {
        $permissions = ShippingZoneResource::definition()->getPermissions();

        $this->assertSame([
            'view' => 'shop.view',
            'create' => 'shop.shipping.manage',
            'edit' => 'shop.shipping.manage',
            'delete' => 'shop.shipping.manage',
        ], $permissions);

        $declared = array_map(
            fn (array $permission): string => $permission['slug'],
            $this->registry()->permissions(),
        );
        foreach ($permissions as $slug) {
            $this->assertContains($slug, $declared, "The permission slug [{$slug}] must be declared in permissions().");
        }
    }
}