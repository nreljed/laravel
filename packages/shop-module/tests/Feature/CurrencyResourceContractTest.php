<?php

namespace Nreljed\ShopModule\Tests\Feature;

use Illuminate\Container\Container;
use Nreljed\Platform\Support\ResourceDefinition;
use Nreljed\ShopModule\Resources\CurrencyResource;
use Nreljed\ShopModule\ShopServiceProvider;
use Nreljed\ShopModule\Tests\Concerns\BootsFeatureRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Plan 6.1 wave-2: Currencies on the kernel resource path (platform 0.1.66).
 * Proves the real ShopServiceProvider aggregates the currency descriptor, the
 * six route names the kernel binds resolve to the module's routes, and the
 * permission slots reuse the existing `shop.currencies.manage` RBAC slug.
 */
final class CurrencyResourceContractTest extends TestCase
{
    use BootsFeatureRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootFeatureRegistry();
        $this->registerFeatureProvider(new ShopServiceProvider($this->container), ShopServiceProvider::class);
        $this->enableFeature('shop');
    }

    public function test_the_shop_provider_ships_a_valid_currencies_descriptor(): void
    {
        $resources = $this->registry()->getAllAdminResources();

        $this->assertNotEmpty($resources);
        $this->assertContainsOnlyInstancesOf(ResourceDefinition::class, $resources);

        $definition = collect($resources)->first(
            fn (ResourceDefinition $resource): bool => $resource->getKey() === CurrencyResource::KEY,
        );

        $this->assertInstanceOf(ResourceDefinition::class, $definition);
        $this->assertSame('Currencies', $definition->getLabel());
        $this->assertSame('Currency', $definition->getSingular());

        $this->assertSame([], $definition->violations());
        $definition->assertValid('shop');
    }

    public function test_currencies_is_serialized_for_the_admin_inventory(): void
    {
        $all = $this->registry()->getAllAdminResourcesArray();

        $this->assertArrayHasKey(CurrencyResource::KEY, $all);
        $this->assertSame('Currencies', $all[CurrencyResource::KEY]['label']);
        $this->assertSame(CurrencyResource::ROUTES, $all[CurrencyResource::KEY]['routes']);
        $this->assertSame('name', $all[CurrencyResource::KEY]['table']['searchKey']);

        $routeNames = array_map(
            fn (array $route): string => $route['name'],
            $all[CurrencyResource::KEY]['table']['columns'],
        );
        $this->assertSame(['code', 'name', 'symbol', 'exchange_rate', 'is_default', 'is_active', 'created_at'], $routeNames);
    }

    public function test_currencies_declares_the_four_record_easter_routes_for_the_generic_pages(): void
    {
        $definition = CurrencyResource::definition();

        $this->assertSame(
            [
                'dashboard.shop.currencies.index',
                'dashboard.shop.currencies.create',
                'dashboard.shop.currencies.store',
                'dashboard.shop.currencies.edit',
                'dashboard.shop.currencies.update',
                'dashboard.shop.currencies.destroy',
            ],
            array_values($definition->getRoutes()),
        );
    }

    public function test_every_currency_permission_slot_resolves_to_existing_shop_rbac(): void
    {
        $permissions = CurrencyResource::definition()->getPermissions();

        $this->assertSame(['view', 'create', 'edit', 'delete'], array_keys($permissions));
        $this->assertSame(array_fill(0, 4, 'shop.currencies.manage'), array_values($permissions));

        $declared = array_map(
            fn (array $permission): string => $permission['slug'],
            $this->registry()->permissions(),
        );
        $this->assertContains('shop.currencies.manage', $declared, 'The permission slug must be declared in permissions().');
    }
}