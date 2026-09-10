<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\DashboardCore\Services\FeatureRegistry;
use Tests\TestCase;

/**
 * Integration tests verifying that the core config merge works correctly
 * when consuming apps override core config values.
 */
class CoreConfigMergeTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_config_loads_with_app_overrides(): void
    {
        $features = config('core.features');

        $this->assertIsArray($features);
        $this->assertNotEmpty($features, 'At least one feature provider must be configured.');
    }

    public function test_app_can_disable_core_migrations(): void
    {
        $loadMigrations = config('core.load_migrations');

        $this->assertFalse(
            $loadMigrations,
            'The master app should set load_migrations to false since it ships its own migration files.'
        );
    }

    public function test_app_can_register_additional_features(): void
    {
        $features = config('core.features');

        $featureClasses = array_map(class_basename(...), $features);

        $this->assertContains('PostsServiceProvider', $featureClasses, 'Posts should be registered as a feature.');
        $this->assertContains('TestPostsServiceProvider', $featureClasses, 'TestPosts should be registered as an app-owned feature.');
    }

    public function test_feature_registry_resolves_all_configured_providers(): void
    {
        $registry = app(FeatureRegistry::class);
        $all = $registry->getAll();

        $this->assertIsArray($all);
        $this->assertGreaterThanOrEqual(2, count($all), 'Registry should resolve at least Posts and TestPosts providers.');

        foreach ($all as $feature) {
            $this->assertArrayHasKey('name', $feature);
            $this->assertArrayHasKey('slug', $feature);
            $this->assertArrayHasKey('version', $feature);
            $this->assertTrue($feature['enabled']);
        }
    }

    public function test_feature_registry_validate_returns_no_critical_issues(): void
    {
        $registry = app(FeatureRegistry::class);
        $issues = $registry->validate();

        $critical = array_filter($issues, fn ($issue) => $issue['severity'] === 'critical');

        $this->assertEmpty($critical, 'FeatureRegistry validation found critical issues: '.json_encode($critical));
    }

    public function test_feature_registry_collects_nav_items_from_all_providers(): void
    {
        $registry = app(FeatureRegistry::class);
        $navItems = $registry->getAllNavItems();

        $this->assertIsArray($navItems);
        $this->assertNotEmpty($navItems);

        foreach ($navItems as $item) {
            $this->assertArrayHasKey('title', $item);
            $this->assertArrayHasKey('url', $item);
            $this->assertArrayHasKey('icon', $item);
        }
    }

    public function test_feature_registry_collects_permissions_from_all_providers(): void
    {
        $registry = app(FeatureRegistry::class);
        $permissions = $registry->permissions();

        $this->assertIsArray($permissions);
        $this->assertNotEmpty($permissions);

        foreach ($permissions as $permission) {
            $this->assertArrayHasKey('slug', $permission);
            $this->assertArrayHasKey('name', $permission);
        }
    }

    public function test_app_orphan_media_config_includes_core_and_app_models(): void
    {
        $orphanMedia = config('core.orphan_media');

        $this->assertIsArray($orphanMedia);
        $this->assertArrayHasKey('posts', $orphanMedia, 'Core posts model should be in orphan_media config.');
        $this->assertArrayHasKey('avatars', $orphanMedia, 'Core avatars model should be in orphan_media config.');
        $this->assertArrayHasKey('test-posts', $orphanMedia, 'App-owned TestPost model should be in orphan_media config.');
    }
}
