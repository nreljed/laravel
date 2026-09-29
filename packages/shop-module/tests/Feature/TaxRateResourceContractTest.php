<?php

namespace Nreljed\ShopModule\Tests\Feature;

use Illuminate\Container\Container;
use Nreljed\Platform\Support\ResourceDefinition;
use Nreljed\ShopModule\ShopServiceProvider;
use Nreljed\ShopModule\Tests\Concerns\BootsFeatureRegistry;
use Nreljed\ShopModule\Resources\TaxRateResource;
use PHPUnit\Framework\TestCase;

/**
 * The plan 6.1 wave-1 proof that the kernel resource path (platform 0.1.66)
 * accepts the shop module's first real conversion. Unlike the fake modules
 * used in ModuleResourceContractTest, this test drives the actual
 * ShopServiceProvider and TaxRateResource: the descriptor is aggregated by
 * the real registry, its route names match the module's registered routes,
 * and its permission slots map onto the module's existing RBAC slug.
 */
final class TaxRateResourceContractTest extends TestCase
{
    use BootsFeatureRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootFeatureRegistry();
        $this->registerFeatureProvider(new ShopServiceProvider(Container::getInstance()), ShopServiceProvider::class);
        $this->enableFeature('shop');
    }

    public function test_the_shop_provider_ships_a_valid_tax_rates_descriptor(): void
    {
        $resources = $this->registry()->getAllAdminResources();

        $this->assertNotEmpty($resources);
        $this->assertContainsOnlyInstancesOf(ResourceDefinition::class, $resources);

        $definition = collect($resources)->first(
            fn (ResourceDefinition $resource): bool => $resource->getKey() === TaxRateResource::KEY,
        );

        $this->assertInstanceOf(ResourceDefinition::class, $definition);
        $this->assertSame('Tax Rates', $definition->getLabel());
        $this->assertSame('Tax Rate', $definition->getSingular());

        $this->assertSame([], $definition->violations());
        $definition->assertValid('shop');
    }

    public function test_default_tax_rate_factory_is_registered_and_serialized_for_admin_inventory(): void
    {
        $all = $this->registry()->getAllAdminResourcesArray();

        $this->assertArrayHasKey(TaxRateResource::KEY, $all);
        $this->assertSame('Tax Rates', $all[TaxRateResource::KEY]['label']);
        $this->assertSame(TaxRateResource::ROUTES, $all[TaxRateResource::KEY]['routes']);
        $this->assertSame('name', $all[TaxRateResource::KEY]['table']['searchKey']);

        $routeNames = array_map(
            fn (array $route): string => $route['name'],
            $all[TaxRateResource::KEY]['table']['columns'],
        );
        $this->assertSame(['id', 'name', 'country', 'rate', 'is_active', 'created_at'], $routeNames);
    }

    public function test_declared_route_names_match_the_module_route_aliases_the_kernel_resolves(): void
    {
        $definition = TaxRateResource::definition();

        $this->assertSame(
            ['index', 'create', 'store', 'edit', 'update', 'destroy'],
            array_keys($definition->getRoutes()),
        );
        $this->assertSame(TaxRateResource::ROUTES, $definition->getRoutes());
        $this->assertSame(
            [
                'dashboard.shop.tax-rates.index',
                'dashboard.shop.tax-rates.create',
                'dashboard.shop.tax-rates.store',
                'dashboard.shop.tax-rates.edit',
                'dashboard.shop.tax-rates.update',
                'dashboard.shop.tax-rates.destroy',
            ],
            array_values($definition->getRoutes()),
        );
    }

    public function test_every_descriptor_permission_slot_resolves_to_existing_shop_rbac(): void
    {
        $definition = TaxRateResource::definition();
        $permissions = $definition->getPermissions();

        $this->assertSame(['view', 'create', 'edit', 'delete'], array_keys($permissions));
        $this->assertSame(array_fill(0, 4, 'shop.taxes.manage'), array_values($permissions));

        $declared = array_map(
            fn (array $permission): string => $permission['slug'],
            $this->registry()->permissions(),
        );
        $this->assertContains('shop.taxes.manage', $declared, 'The permission slug must be declared in permissions().');
    }
}