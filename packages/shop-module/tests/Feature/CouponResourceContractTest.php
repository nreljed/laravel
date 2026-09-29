<?php

namespace Nreljed\ShopModule\Tests\Feature;

use Nreljed\Platform\Support\ResourceDefinition;
use Nreljed\ShopModule\Resources\CouponResource;
use Nreljed\ShopModule\ShopServiceProvider;
use Nreljed\ShopModule\Tests\Concerns\BootsFeatureRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Plan 6.1 wave-5: Coupons on the kernel resource path.
 */
final class CouponResourceContractTest extends TestCase
{
    use BootsFeatureRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootFeatureRegistry();
        $this->registerFeatureProvider(new ShopServiceProvider($this->container), ShopServiceProvider::class);
        $this->enableFeature('shop');
    }

    public function test_the_shop_provider_ships_a_valid_coupons_descriptor(): void
    {
        $definition = collect($this->registry()->getAllAdminResources())->first(
            fn (ResourceDefinition $resource): bool => $resource->getKey() === CouponResource::KEY,
        );

        $this->assertInstanceOf(ResourceDefinition::class, $definition);
        $this->assertSame('Coupons', $definition->getLabel());
        $this->assertSame('Coupon', $definition->getSingular());
        $this->assertSame([], $definition->violations());
        $definition->assertValid('shop');
    }

    public function test_coupons_declares_the_six_kernel_route_names_in_order(): void
    {
        $this->assertSame(
            [
                'dashboard.shop.coupons.index',
                'dashboard.shop.coupons.create',
                'dashboard.shop.coupons.store',
                'dashboard.shop.coupons.edit',
                'dashboard.shop.coupons.update',
                'dashboard.shop.coupons.destroy',
            ],
            array_values(CouponResource::definition()->getRoutes()),
        );
    }

    public function test_product_restrictions_use_the_lazy_products_options_url(): void
    {
        $field = collect(CouponResource::definition()->getForm())
            ->flatMap(fn (array $section): array => $section['fields'])
            ->first(fn (array $field): bool => ($field['name'] ?? null) === 'product_ids');

        $this->assertIsArray($field);
        $this->assertSame('relation', $field['type']);
        $this->assertSame('dashboard.shop.products.options', $field['optionsUrl']);
        $this->assertArrayNotHasKey('options', $field, 'Products are not a bounded enum; the relation field must fetch its catalog lazily via optionsUrl.');
    }

    public function test_coupons_permission_slots_map_to_existing_shop_rbac(): void
    {
        $permissions = CouponResource::definition()->getPermissions();

        $this->assertSame([
            'view' => 'shop.view',
            'create' => 'shop.coupons.manage',
            'edit' => 'shop.coupons.manage',
            'delete' => 'shop.coupons.manage',
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