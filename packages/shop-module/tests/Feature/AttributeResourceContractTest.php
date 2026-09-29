<?php

namespace Nreljed\ShopModule\Tests\Feature;

use Nreljed\Platform\Support\ResourceDefinition;
use Nreljed\ShopModule\Resources\AttributeResource;
use Nreljed\ShopModule\ShopServiceProvider;
use Nreljed\ShopModule\Tests\Concerns\BootsFeatureRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Plan 6.1 wave-11: Attributes on the kernel resource path.
 */
final class AttributeResourceContractTest extends TestCase
{
    use BootsFeatureRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootFeatureRegistry();
        $this->registerFeatureProvider(new ShopServiceProvider($this->container), ShopServiceProvider::class);
        $this->enableFeature('shop');
    }

    public function test_the_shop_provider_ships_a_valid_attributes_descriptor(): void
    {
        $definition = collect($this->registry()->getAllAdminResources())->first(
            fn (ResourceDefinition $resource): bool => $resource->getKey() === AttributeResource::KEY,
        );

        $this->assertInstanceOf(ResourceDefinition::class, $definition);
        $this->assertSame('Attributes', $definition->getLabel());
        $this->assertSame('Attribute', $definition->getSingular());
        $this->assertSame([], $definition->violations());
        $definition->assertValid('shop');
    }

    public function test_attributes_declares_the_six_kernel_route_names_in_order(): void
    {
        $this->assertSame(
            [
                'dashboard.shop.attributes.index',
                'dashboard.shop.attributes.create',
                'dashboard.shop.attributes.store',
                'dashboard.shop.attributes.edit',
                'dashboard.shop.attributes.update',
                'dashboard.shop.attributes.destroy',
            ],
            array_values(AttributeResource::definition()->getRoutes()),
        );
    }

    public function test_the_terms_rows_edit_name_and_swatch_value_with_a_row_title(): void
    {
        $nested = collect(AttributeResource::definition()->getForm())
            ->flatMap(fn (array $section): array => $section['fields'])
            ->first(fn (array $field): bool => ($field['name'] ?? null) === 'terms');

        $this->assertIsArray($nested);
        $this->assertSame('nested', $nested['type']);
        $this->assertSame('name', $nested['rowTitleField']);

        $names = array_map(fn (array $row): string => $row['name'], $nested['rows']);
        $this->assertSame(['name', 'swatch_value'], $names);

        $this->assertSame('text', $nested['rows'][0]['type']);
        $this->assertTrue($nested['rows'][0]['required'] ?? false);
        $this->assertSame('text', $nested['rows'][1]['type']);
    }

    public function test_attributes_permission_slots_map_to_existing_shop_rbac(): void
    {
        $permissions = AttributeResource::definition()->getPermissions();

        $this->assertSame([
            'view' => 'shop.view',
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