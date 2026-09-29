<?php

namespace Nreljed\ShopModule\Tests\Feature;

use Nreljed\Platform\Support\ResourceDefinition;
use Nreljed\ShopModule\Events\WebhookEvents;
use Nreljed\ShopModule\Resources\WebhookResource;
use Nreljed\ShopModule\ShopServiceProvider;
use Nreljed\ShopModule\Tests\Concerns\BootsFeatureRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Plan 6.1 wave-6: Webhooks on the kernel resource path (platform 0.1.67),
 * exercising the first serialized `relation` field and serialized row actions.
 */
final class WebhookResourceContractTest extends TestCase
{
    use BootsFeatureRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootFeatureRegistry();
        $this->registerFeatureProvider(new ShopServiceProvider($this->container), ShopServiceProvider::class);
        $this->enableFeature('shop');
    }

    public function test_the_shop_provider_ships_a_valid_webhooks_descriptor(): void
    {
        $definition = collect($this->registry()->getAllAdminResources())->first(
            fn (ResourceDefinition $resource): bool => $resource->getKey() === WebhookResource::KEY,
        );

        $this->assertInstanceOf(ResourceDefinition::class, $definition);
        $this->assertSame('Webhooks', $definition->getLabel());
        $this->assertSame('Webhook', $definition->getSingular());
        $this->assertSame([], $definition->violations());
        $definition->assertValid('shop');
    }

    public function test_webhooks_declares_the_six_kernel_route_names_in_order(): void
    {
        $this->assertSame(
            [
                'dashboard.shop.webhooks.index',
                'dashboard.shop.webhooks.create',
                'dashboard.shop.webhooks.store',
                'dashboard.shop.webhooks.edit',
                'dashboard.shop.webhooks.update',
                'dashboard.shop.webhooks.destroy',
            ],
            array_values(WebhookResource::definition()->getRoutes()),
        );
    }

    public function test_the_events_relation_field_uses_the_static_webhook_events_catalog(): void
    {
        $sections = WebhookResource::definition()->getForm();

        $eventsField = collect($sections)
            ->flatMap(fn (array $section): array => $section['fields'])
            ->first(
                fn (array $field): bool => ($field['name'] ?? null) === 'events',
            );

        $this->assertIsArray($eventsField);
        $this->assertSame('relation', $eventsField['type']);
        $this->assertArrayNotHasKey('optionsUrl', $eventsField, 'Events are a small static catalog; the relation field must ship static options, not a lazy URL.');

        $declared = array_column($eventsField['options'] ?? [], 'value');
        $this->assertSame(WebhookEvents::all(), $declared);
        $this->assertNotEmpty($eventsField['options'], 'The events options must be non-empty (the form requires at least one).');
    }

    public function test_the_serialized_row_actions_declare_module_routes_and_verbs(): void
    {
        $table = WebhookResource::definition()->getTable();

        $actions = collect($table['rowActions'] ?? [])->mapWithKeys(
            fn (array $action): array => [$action['label'] => $action],
        )->all();

        $this->assertSame(['Toggle active', 'Send test', 'View calls'], array_keys($actions));
        $this->assertSame('dashboard.shop.webhooks.toggle-active', $actions['Toggle active']['routeName']);
        $this->assertSame('post', $actions['Toggle active']['method']);
        $this->assertSame('dashboard.shop.webhooks.test', $actions['Send test']['routeName']);
        $this->assertSame('post', $actions['Send test']['method']);
        $this->assertSame('Send a test webhook to this endpoint?', $actions['Send test']['confirm']);
        $this->assertSame('dashboard.shop.webhooks.calls', $actions['View calls']['routeName']);
        $this->assertSame('get', $actions['View calls']['method']);
    }

    public function test_webhooks_permission_slots_map_to_existing_shop_rbac(): void
    {
        $permissions = WebhookResource::definition()->getPermissions();

        $this->assertSame([
            'view' => 'shop.webhooks.manage',
            'create' => 'shop.webhooks.manage',
            'edit' => 'shop.webhooks.manage',
            'delete' => 'shop.webhooks.manage',
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
