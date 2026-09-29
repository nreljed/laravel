<?php

namespace Nreljed\ShopModule\Tests\Feature;

use Nreljed\Platform\Support\ResourceDefinition;
use Nreljed\ShopModule\Resources\OrderResource;
use Nreljed\ShopModule\Services\OrderStatusService;
use Nreljed\ShopModule\ShopServiceProvider;
use Nreljed\ShopModule\Tests\Concerns\BootsFeatureRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Plan 6.1 wave-9: Orders on the kernel resource path (platform 0.1.68,
 * `searchKeys` + `filters`).
 */
final class OrderResourceContractTest extends TestCase
{
    use BootsFeatureRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootFeatureRegistry();
        $this->registerFeatureProvider(new ShopServiceProvider($this->container), ShopServiceProvider::class);
        $this->enableFeature('shop');
    }

    public function test_the_shop_provider_ships_a_valid_orders_descriptor(): void
    {
        $definition = collect($this->registry()->getAllAdminResources())->first(
            fn (ResourceDefinition $resource): bool => $resource->getKey() === OrderResource::KEY,
        );

        $this->assertInstanceOf(ResourceDefinition::class, $definition);
        $this->assertSame('Orders', $definition->getLabel());
        $this->assertSame('Order', $definition->getSingular());
        $this->assertSame([], $definition->violations());
        $definition->assertValid('shop');
    }

    public function test_orders_declares_the_index_and_create_route_slots(): void
    {
        $this->assertSame(
            [
                'dashboard.shop.orders.index',
                'dashboard.shop.orders.create',
            ],
            array_values(OrderResource::definition()->getRoutes()),
        );
    }

    public function test_order_search_ors_across_number_and_customer_fields(): void
    {
        $table = OrderResource::definition()->getTable();

        $this->assertSame(['order_number', 'customer_name', 'customer_email'], $table['searchKeys'] ?? null);
        $this->assertArrayNotHasKey('searchKey', $table, 'Order search spans three columns; searchKeys is required.');
    }

    public function test_the_status_filter_catalogs_every_order_status(): void
    {
        $filters = OrderResource::definition()->getTable()['filters'] ?? [];

        $statusFilter = collect($filters)->first(
            fn (array $filter): bool => ($filter['key'] ?? null) === 'status',
        );

        $this->assertIsArray($statusFilter);
        $this->assertSame(
            OrderStatusService::STATUSES,
            array_column($statusFilter['options'] ?? [], 'value'),
        );
    }

    public function test_the_view_order_row_action_drills_into_the_module_show_route(): void
    {
        $table = OrderResource::definition()->getTable();

        $actions = collect($table['rowActions'] ?? [])->mapWithKeys(
            fn (array $action): array => [$action['label'] => $action],
        )->all();

        $this->assertSame(['View order'], array_keys($actions));
        $this->assertSame('dashboard.shop.orders.show', $actions['View order']['routeName']);
        $this->assertSame('get', $actions['View order']['method']);
    }

    public function test_orders_permission_slots_map_to_existing_shop_rbac(): void
    {
        $permissions = OrderResource::definition()->getPermissions();

        $this->assertSame([
            'view' => 'shop.orders.view',
            'create' => 'shop.orders.edit',
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