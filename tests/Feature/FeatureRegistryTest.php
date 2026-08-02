<?php

namespace Tests\Feature;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\DashboardCore\Services\FeatureRegistry;
use Tests\TestCase;

class FeatureRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_feature_registry_returns_nav_items(): void
    {
        $registry = app(FeatureRegistry::class);
        $items = $registry->getAllNavItems();
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
    }

    public function test_feature_registry_returns_permissions(): void
    {
        $registry = app(FeatureRegistry::class);
        $permissions = $registry->permissions();
        $this->assertIsArray($permissions);
        $this->assertNotEmpty($permissions);
    }

    public function test_feature_registry_get_all_returns_registered_features(): void
    {
        $registry = app(FeatureRegistry::class);
        $features = $registry->getAll();
        $this->assertIsArray($features);
        $this->assertNotEmpty($features);
    }

    public function test_feature_registry_shared_data_is_array(): void
    {
        $registry = app(FeatureRegistry::class);
        $data = $registry->getAllSharedData();
        $this->assertIsArray($data);
    }

    public function test_feature_registry_notification_types_is_array(): void
    {
        $registry = app(FeatureRegistry::class);
        $types = $registry->getAllNotificationTypes();
        $this->assertIsArray($types);
    }
}
