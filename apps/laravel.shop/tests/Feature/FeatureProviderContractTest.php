<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\Platform\Concerns\InteractsWithFeatures;
use Nreljed\Platform\Contracts\FeatureProvider;
use Nreljed\Platform\Services\FeatureRegistry;
use Nreljed\Platform\Support\ContentDescription;
use Nreljed\Platform\Support\SettingsSection;
use Tests\BootsFeatures;
use Tests\TestCase;

/**
 * Booted conformance test for the FeatureProvider contract. Unlike the
 * package's structural test (which does not boot the framework), this test
 * boots the real application + FeatureRegistry and asserts that every
 * registered feature provider implements the full capability surface and
 * returns contract-compliant data shapes.
 */
class FeatureProviderContractTest extends TestCase
{
    use BootsFeatures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootFeatures();
    }

    public function test_every_registered_provider_implements_the_contract_and_uses_the_trait(): void
    {
        $violations = [];

        foreach (app(FeatureRegistry::class)->allProviders() as $provider) {
            $reflection = new \ReflectionClass($provider);

            if (! $reflection->implementsInterface(FeatureProvider::class)) {
                $violations[] = sprintf('%s must implement FeatureProvider', $provider::class);

                continue;
            }

            if (! in_array(InteractsWithFeatures::class, $this->usedTraits($reflection), true)) {
                $violations[] = sprintf('%s must use the InteractsWithFeatures trait', $provider::class);
            }

            foreach (['name', 'slug'] as $mandatory) {
                if (! $reflection->hasMethod($mandatory)) {
                    $violations[] = sprintf('%s must implement %s()', $provider::class, $mandatory);
                }
            }
        }

        $this->assertNotSame([], app(FeatureRegistry::class)->allProviders(), 'No feature providers are registered.');
        $this->assertSame([], $violations, 'Feature provider contract violations:'.PHP_EOL.implode(PHP_EOL, $violations));
    }

    public function test_providers_return_contract_compliant_shapes(): void
    {
        $violations = [];

        foreach (app(FeatureRegistry::class)->allProviders() as $provider) {
            if (! $provider instanceof FeatureProvider) {
                continue;
            }

            $label = $provider::class;

            if (trim((string) $provider->name()) === '') {
                $violations[] = "$label must return a non-empty name()";
            }

            if (trim((string) $provider->slug()) === '') {
                $violations[] = "$label must return a non-empty slug()";
            }

            foreach (['getNavItems', 'getDashboardData', 'getSharedData', 'getEmailTemplates', 'getNotificationTypes', 'permissions', 'getHealthChecks', 'getScheduledTasks'] as $arrayMethod) {
                if (! is_array($provider->{$arrayMethod}())) {
                    $violations[] = "$label $arrayMethod() must return an array";
                }
            }

            foreach (['getSettingsRoute', 'getNavGroupLabel', 'description', 'version'] as $nullableMethod) {
                $value = $provider->{$nullableMethod}();
                if ($value !== null && ! is_string($value)) {
                    $violations[] = "$label $nullableMethod() must return a string or null";
                }
            }

            foreach (['getMediaSubdirectory', 'getDeactivationNotice'] as $nullableStringMethod) {
                $value = $provider->{$nullableStringMethod}();
                if ($value !== null && ! is_string($value)) {
                    $violations[] = "$label $nullableStringMethod() must return a string or null";
                }
            }

            $description = $provider->getContentDescription();
            if ($description !== null && ! $description instanceof ContentDescription) {
                $violations[] = "$label getContentDescription() must return a ContentDescription or null";
            }

            $section = $provider->getSettingsSection();
            if ($section !== null && ! $section instanceof SettingsSection) {
                $violations[] = "$label getSettingsSection() must return a SettingsSection or null";
            }
        }

        $this->assertSame([], $violations, 'Feature provider shape violations:'.PHP_EOL.implode(PHP_EOL, $violations));
    }

    public function test_content_descriptions_and_media_subdirectories_are_aggregated(): void
    {
        $registry = app(FeatureRegistry::class);

        $subdirs = $registry->getAllMediaSubdirectories();
        $this->assertIsArray($subdirs);
        $this->assertSame('products', $subdirs['shop'] ?? null);

        $descriptions = $registry->getAllContentDescriptions();
        $this->assertIsArray($descriptions);
        foreach ($descriptions as $description) {
            $this->assertInstanceOf(ContentDescription::class, $description);
            $this->assertNotSame('', $description->getSlug());
            $this->assertNotSame('', $description->getModelClass());
        }
    }

    public function test_providers_declare_scoped_permission_slugs(): void
    {
        $violations = [];

        foreach (app(FeatureRegistry::class)->allProviders() as $provider) {
            if (! $provider instanceof FeatureProvider) {
                continue;
            }

            foreach ($provider->permissions() as $permission) {
                $slug = $permission['slug'] ?? null;

                if ($slug === null || $slug === '') {
                    $violations[] = sprintf('%s declares a permission without a slug', $provider::class);

                    continue;
                }

                if (str_contains($slug, '.')) {
                    continue;
                }

                // Generic admin permissions may legitimately live outside the
                // feature scope; only namespaced slugs are mandatory.
                $violations[] = sprintf('%s declares unscoped permission slug [%s]', $provider::class, $slug);
            }
        }

        $this->assertSame([], $violations, 'Permission slug scoping violations:'.PHP_EOL.implode(PHP_EOL, $violations));
    }

    /**
     * @return list<string>
     */
    private function usedTraits(\ReflectionClass $reflection): array
    {
        $traits = [];

        foreach ($reflection->getTraits() as $trait) {
            $traits[] = $trait->getName();
        }

        return $traits;
    }
}
