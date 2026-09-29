<?php

namespace Nreljed\ShopModule\Tests\Feature;

use Nreljed\Platform\Support\ResourceDefinition;
use Nreljed\ShopModule\Resources\CustomerResource;
use Nreljed\ShopModule\ShopServiceProvider;
use Nreljed\ShopModule\Tests\Concerns\BootsFeatureRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Plan 6.1 wave-8: Customers on the kernel resource path (platform 0.1.68,
 * `searchKeys`).
 */
final class CustomerResourceContractTest extends TestCase
{
    use BootsFeatureRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootFeatureRegistry();
        $this->registerFeatureProvider(new ShopServiceProvider($this->container), ShopServiceProvider::class);
        $this->enableFeature('shop');
    }

    public function test_the_shop_provider_ships_a_valid_customers_descriptor(): void
    {
        $definition = collect($this->registry()->getAllAdminResources())->first(
            fn (ResourceDefinition $resource): bool => $resource->getKey() === CustomerResource::KEY,
        );

        $this->assertInstanceOf(ResourceDefinition::class, $definition);
        $this->assertSame('Customers', $definition->getLabel());
        $this->assertSame('Customer', $definition->getSingular());
        $this->assertSame([], $definition->violations());
        $definition->assertValid('shop');
    }

    public function test_customers_declares_only_the_index_route_slot(): void
    {
        $this->assertSame(
            ['dashboard.shop.customers.index'],
            array_values(CustomerResource::definition()->getRoutes()),
        );
    }

    public function test_customer_search_ors_across_name_and_email(): void
    {
        $searchKeys = CustomerResource::definition()->getTable()['searchKeys'] ?? null;

        $this->assertSame(['name', 'email'], $searchKeys);
        $this->assertArrayNotHasKey(
            'searchKey',
            CustomerResource::definition()->getTable(),
            'Single-column searchKey cannot OR across name and email; searchKeys is required.',
        );
    }

    public function test_the_view_profile_row_action_drills_into_the_module_show_route(): void
    {
        $table = CustomerResource::definition()->getTable();

        $actions = collect($table['rowActions'] ?? [])->mapWithKeys(
            fn (array $action): array => [$action['label'] => $action],
        )->all();

        $this->assertSame(['View profile'], array_keys($actions));
        $this->assertSame('dashboard.shop.customers.show', $actions['View profile']['routeName']);
        $this->assertSame('get', $actions['View profile']['method']);
    }

    public function test_customers_permission_view_maps_to_orders_rbac(): void
    {
        $permissions = CustomerResource::definition()->getPermissions();

        $this->assertSame(['view' => 'shop.orders.view'], $permissions);

        $declared = array_map(
            fn (array $permission): string => $permission['slug'],
            $this->registry()->permissions(),
        );
        foreach ($permissions as $slug) {
            $this->assertContains($slug, $declared, "The permission slug [{$slug}] must be declared in permissions().");
        }
    }
}