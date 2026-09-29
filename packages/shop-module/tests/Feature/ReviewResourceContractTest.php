<?php

namespace Nreljed\ShopModule\Tests\Feature;

use Nreljed\Platform\Support\ResourceDefinition;
use Nreljed\ShopModule\Resources\ReviewResource;
use Nreljed\ShopModule\ShopServiceProvider;
use Nreljed\ShopModule\Tests\Concerns\BootsFeatureRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Plan 6.1 wave-6: Reviews on the kernel resource path (platform 0.1.67).
 *
 * Reviews are a moderation surface: the descriptor declares only the slots
 * the generic pages render (`index`, `destroy`) because `store` is owned by
 * the customer submission route, and the approve/reject mutations ship as
 * serialized row actions.
 */
final class ReviewResourceContractTest extends TestCase
{
    use BootsFeatureRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootFeatureRegistry();
        $this->registerFeatureProvider(new ShopServiceProvider($this->container), ShopServiceProvider::class);
        $this->enableFeature('shop');
    }

    public function test_the_shop_provider_ships_a_valid_reviews_descriptor(): void
    {
        $definition = collect($this->registry()->getAllAdminResources())->first(
            fn (ResourceDefinition $resource): bool => $resource->getKey() === ReviewResource::KEY,
        );

        $this->assertInstanceOf(ResourceDefinition::class, $definition);
        $this->assertSame('Reviews', $definition->getLabel());
        $this->assertSame('Review', $definition->getSingular());
        $this->assertSame([], $definition->violations());
        $definition->assertValid('shop');
    }

    public function test_reviews_declares_only_the_slots_the_generic_pages_render(): void
    {
        // `store` belongs to the customer submission route
        // (dashboard.shop.reviews.store, POST /products/{product}/reviews), so
        // a create/store pair for admin moderation would collide with it. The
        // descriptor must not declare routes the module does not wire.
        $this->assertSame(
            [
                'index' => 'dashboard.shop.reviews.index',
                'destroy' => 'dashboard.shop.reviews.destroy',
            ],
            ReviewResource::definition()->getRoutes(),
        );
    }

    public function test_moderation_actions_are_serialized_row_actions(): void
    {
        $table = ReviewResource::definition()->getTable();

        $actions = collect($table['rowActions'] ?? [])->mapWithKeys(
            fn (array $action): array => [$action['label'] => $action],
        )->all();

        $this->assertSame(['Approve', 'Reject'], array_keys($actions));
        $this->assertSame('dashboard.shop.reviews.approve', $actions['Approve']['routeName']);
        $this->assertSame('post', $actions['Approve']['method']);
        $this->assertSame('Approve this review?', $actions['Approve']['confirm']);
        $this->assertSame('dashboard.shop.reviews.reject', $actions['Reject']['routeName']);
        $this->assertSame('post', $actions['Reject']['method']);
        $this->assertSame('Reject this review?', $actions['Reject']['confirm']);
    }

    public function test_reviews_declares_flat_relation_accessor_columns(): void
    {
        $columns = array_column(ReviewResource::definition()->getTable()['columns'], 'name');

        $this->assertContains('product_name', $columns);
        $this->assertContains('user_name', $columns);
        $this->assertContains('is_approved', $columns);
    }

    public function test_reviews_permission_slots_map_to_existing_shop_rbac(): void
    {
        $permissions = ReviewResource::definition()->getPermissions();

        $this->assertSame([
            'view' => 'shop.view',
            'delete' => 'shop.reviews.moderate',
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
