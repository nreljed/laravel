<?php

namespace Nreljed\ShopModule\Tests\Feature;

use Nreljed\Platform\Support\ResourceDefinition;
use Nreljed\ShopModule\Resources\CategoryResource;
use Nreljed\ShopModule\ShopServiceProvider;
use Nreljed\ShopModule\Tests\Concerns\BootsFeatureRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Plan 6.1 wave-4: Categories on the kernel resource path.
 */
final class CategoryResourceContractTest extends TestCase
{
    use BootsFeatureRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootFeatureRegistry();
        $this->registerFeatureProvider(new ShopServiceProvider($this->container), ShopServiceProvider::class);
        $this->enableFeature('shop');
    }

    public function test_the_shop_provider_ships_a_valid_categories_descriptor(): void
    {
        $definition = collect($this->registry()->getAllAdminResources())->first(
            fn (ResourceDefinition $resource): bool => $resource->getKey() === CategoryResource::KEY,
        );

        $this->assertInstanceOf(ResourceDefinition::class, $definition);
        $this->assertSame('Categories', $definition->getLabel());
        $this->assertSame('Category', $definition->getSingular());
        $this->assertSame([], $definition->violations());
        $definition->assertValid('shop');
    }

    public function test_categories_declares_the_six_kernel_route_names_in_order(): void
    {
        $this->assertSame(
            [
                'dashboard.shop.categories.index',
                'dashboard.shop.categories.create',
                'dashboard.shop.categories.store',
                'dashboard.shop.categories.edit',
                'dashboard.shop.categories.update',
                'dashboard.shop.categories.destroy',
            ],
            array_values(CategoryResource::definition()->getRoutes()),
        );
    }

    public function test_categories_permission_slots_map_to_existing_shop_rbac(): void
    {
        $permissions = CategoryResource::definition()->getPermissions();

        $this->assertSame([
            'view' => 'shop.manage',
            'create' => 'shop.products.create',
            'edit' => 'shop.products.edit',
            'delete' => 'shop.products.delete',
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