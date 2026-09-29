<?php

namespace Nreljed\ShopModule\Tests\Feature;

use Nreljed\Platform\Support\ResourceDefinition;
use Nreljed\ShopModule\Resources\TagResource;
use Nreljed\ShopModule\ShopServiceProvider;
use Nreljed\ShopModule\Tests\Concerns\BootsFeatureRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Plan 6.1 wave-4: Tags on the kernel resource path.
 */
final class TagResourceContractTest extends TestCase
{
    use BootsFeatureRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootFeatureRegistry();
        $this->registerFeatureProvider(new ShopServiceProvider($this->container), ShopServiceProvider::class);
        $this->enableFeature('shop');
    }

    public function test_the_shop_provider_ships_a_valid_tags_descriptor(): void
    {
        $definition = collect($this->registry()->getAllAdminResources())->first(
            fn (ResourceDefinition $resource): bool => $resource->getKey() === TagResource::KEY,
        );

        $this->assertInstanceOf(ResourceDefinition::class, $definition);
        $this->assertSame('Tags', $definition->getLabel());
        $this->assertSame('Tag', $definition->getSingular());
        $this->assertSame([], $definition->violations());
        $definition->assertValid('shop');
    }

    public function test_tags_declares_the_six_kernel_route_names_in_order(): void
    {
        $this->assertSame(
            [
                'dashboard.shop.tags.index',
                'dashboard.shop.tags.create',
                'dashboard.shop.tags.store',
                'dashboard.shop.tags.edit',
                'dashboard.shop.tags.update',
                'dashboard.shop.tags.destroy',
            ],
            array_values(TagResource::definition()->getRoutes()),
        );
    }

    public function test_tags_permission_slots_map_to_existing_shop_rbac(): void
    {
        $permissions = TagResource::definition()->getPermissions();

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