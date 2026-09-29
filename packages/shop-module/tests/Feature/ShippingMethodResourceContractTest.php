<?php

namespace Nreljed\ShopModule\Tests\Feature;

use Nreljed\Platform\Support\ResourceDefinition;
use Nreljed\ShopModule\Resources\ShippingMethodResource;
use Nreljed\ShopModule\ShopServiceProvider;
use Nreljed\ShopModule\Tests\Concerns\BootsFeatureRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Plan 6.1 wave-3: Shipping Methods on the kernel resource path. Shipping
 * zones intentionally stay on their hand-rolled form until the kit grows the
 * relation() picker (§5.3) their method pivot needs.
 */
final class ShippingMethodResourceContractTest extends TestCase
{
    use BootsFeatureRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootFeatureRegistry();
        $this->registerFeatureProvider(new ShopServiceProvider($this->container), ShopServiceProvider::class);
        $this->enableFeature('shop');
    }

    public function test_the_shop_provider_ships_a_valid_shipping_methods_descriptor(): void
    {
        $resources = $this->registry()->getAllAdminResources();

        $this->assertNotEmpty($resources);
        $this->assertContainsOnlyInstancesOf(ResourceDefinition::class, $resources);

        $definition = collect($resources)->first(
            fn (ResourceDefinition $resource): bool => $resource->getKey() === ShippingMethodResource::KEY,
        );

        $this->assertInstanceOf(ResourceDefinition::class, $definition);
        $this->assertSame('Shipping Methods', $definition->getLabel());
        $this->assertSame('Shipping Method', $definition->getSingular());

        $this->assertSame([], $definition->violations());
        $definition->assertValid('shop');
    }

    public function test_shipping_methods_is_serialized_for_the_admin_inventory(): void
    {
        $all = $this->registry()->getAllAdminResourcesArray();

        $this->assertArrayHasKey(ShippingMethodResource::KEY, $all);
        $this->assertSame(ShippingMethodResource::ROUTES, $all[ShippingMethodResource::KEY]['routes']);
        $this->assertSame('name', $all[ShippingMethodResource::KEY]['table']['searchKey']);
    }

    public function test_shipping_methods_declares_the_six_kernel_route_names_in_order(): void
    {
        $definition = ShippingMethodResource::definition();

        $this->assertSame(
            ['index', 'create', 'store', 'edit', 'update', 'destroy'],
            array_keys($definition->getRoutes()),
        );
        $this->assertSame(
            [
                'dashboard.shop.shipping-methods.index',
                'dashboard.shop.shipping-methods.create',
                'dashboard.shop.shipping-methods.store',
                'dashboard.shop.shipping-methods.edit',
                'dashboard.shop.shipping-methods.update',
                'dashboard.shop.shipping-methods.destroy',
            ],
            array_values($definition->getRoutes()),
        );
    }

    public function test_every_shipping_method_permission_slot_resolves_to_existing_shop_rbac(): void
    {
        $permissions = ShippingMethodResource::definition()->getPermissions();

        $this->assertSame(['view', 'create', 'edit', 'delete'], array_keys($permissions));
        $this->assertSame(array_fill(0, 4, 'shop.shipping.manage'), array_values($permissions));

        $declared = array_map(
            fn (array $permission): string => $permission['slug'],
            $this->registry()->permissions(),
        );
        $this->assertContains('shop.shipping.manage', $declared, 'The permission slug must be declared in permissions().');
    }
}