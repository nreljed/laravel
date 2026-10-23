<?php

namespace Nreljed\MarketingModule\Tests\Feature;

use Nreljed\MarketingModule\Enums\ContactStatus;
use Nreljed\MarketingModule\MarketingServiceProvider;
use Nreljed\MarketingModule\Resources\CampaignResource;
use Nreljed\MarketingModule\Resources\ContactResource;
use Nreljed\MarketingModule\Resources\SegmentResource;
use Nreljed\MarketingModule\Resources\SocialAccountResource;
use Nreljed\MarketingModule\Resources\SocialPostResource;
use Nreljed\MarketingModule\Resources\TagResource;
use Nreljed\MarketingModule\Resources\WorkflowResource;
use Nreljed\MarketingModule\Tests\Concerns\BootsFeatureRegistry;
use Nreljed\Platform\Support\ResourceDefinition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The module's resources on the kernel's declarative admin path.
 */
final class ResourceContractTest extends TestCase
{
    use BootsFeatureRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootFeatureRegistry();
        $this->registerFeatureProvider(new MarketingServiceProvider($this->container), MarketingServiceProvider::class);
        $this->enableFeature('marketing');
    }

    /**
     * @return array<string, array{0: class-string, 1: string}>
     */
    public static function resources(): array
    {
        return [
            'accounts' => [SocialAccountResource::class, 'marketing.accounts'],
            'posts' => [SocialPostResource::class, 'marketing.posts'],
            'contacts' => [ContactResource::class, 'marketing.contacts'],
            'tags' => [TagResource::class, 'marketing.tags'],
            'segments' => [SegmentResource::class, 'marketing.segments'],
            'campaigns' => [CampaignResource::class, 'marketing.campaigns'],
            'workflows' => [WorkflowResource::class, 'marketing.workflows'],
        ];
    }

    #[DataProvider('resources')]
    public function test_the_provider_ships_a_valid_descriptor(string $resourceClass, string $key): void
    {
        /** @var ResourceDefinition $definition */
        $definition = $resourceClass::definition();

        $this->assertSame($key, $definition->getKey());
        $this->assertSame([], $definition->violations());
        $definition->assertValid('marketing');
    }

    #[DataProvider('resources')]
    public function test_the_registry_aggregates_the_descriptor(string $resourceClass, string $key): void
    {
        $definition = collect($this->registry()->getAllAdminResources())->first(
            fn (ResourceDefinition $resource): bool => $resource->getKey() === $key,
        );

        $this->assertInstanceOf(ResourceDefinition::class, $definition);
    }

    #[DataProvider('resources')]
    public function test_every_declared_route_lives_under_the_marketing_namespace(string $resourceClass, string $key): void
    {
        /** @var ResourceDefinition $definition */
        $definition = $resourceClass::definition();

        foreach ($definition->getRoutes() as $slot => $route) {
            $this->assertStringStartsWith(
                'dashboard.marketing.',
                $route,
                "The [{$slot}] route must live under the marketing namespace."
            );
        }
    }

    public function test_the_accounts_resource_has_no_create_slot(): void
    {
        // An account comes into existence through an OAuth handshake, never
        // through a form submission.
        $this->assertArrayNotHasKey('create', SocialAccountResource::definition()->getRoutes());
        $this->assertArrayNotHasKey('store', SocialAccountResource::definition()->getRoutes());
    }

    public function test_every_permission_used_by_a_resource_is_declared_by_the_provider(): void
    {
        $declared = array_map(
            fn (array $permission): string => $permission['slug'],
            $this->registry()->permissions(),
        );

        foreach ($this->registry()->getAllAdminResources() as $descriptor) {
            foreach ($descriptor->getPermissions() as $slug) {
                $this->assertContains($slug, $declared, "The permission slug [{$slug}] must be declared in permissions().");
            }
        }
    }

    public function test_the_provider_declares_the_publishing_command_and_permission(): void
    {
        $provider = new MarketingServiceProvider($this->container);

        $tasks = $provider->getScheduledTasks();

        $this->assertSame('marketing:publish-due', $tasks[0]['command'] ?? null);
        $this->assertSame('every:60', $tasks[0]['frequency'] ?? null);

        $slugs = array_column($provider->permissions(), 'slug');

        $this->assertContains('marketing.posts.manage', $slugs);
    }

    public function test_the_workflow_contact_status_field_submits_its_visible_default(): void
    {
        $fields = collect(WorkflowResource::definition()->getForm())
            ->flatMap(fn (array $section): array => $section['fields'] ?? [])
            ->keyBy('name');

        $this->assertSame(ContactStatus::Lead->value, $fields['contact_status']['defaultValue'] ?? null);
    }

    public function test_the_post_form_offers_click_tracking(): void
    {
        $fields = collect(SocialPostResource::definition()->getForm())
            ->flatMap(fn (array $section): array => $section['fields'] ?? [])
            ->keyBy('name');

        $this->assertSame('boolean', $fields['track_clicks']['type'] ?? null);
        $this->assertFalse($fields['track_clicks']['defaultValue'] ?? null);
    }
}
