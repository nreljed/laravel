<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Nreljed\Platform\Models\Role;
use Nreljed\Platform\Services\PackageVersionService;
use Tests\TestCase;

class SystemHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::where('slug', 'admin')->first();
        $user->roles()->attach($role);

        return $user;
    }

    public function test_system_health_page_loads_for_admin(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.system-health'));

        $response->assertStatus(200);
    }

    public function test_system_health_exposes_core_package_and_modules(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.system-health'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('core_package')
            ->has('modules'));
    }

    public function test_system_health_reports_module_composer_versions(): void
    {
        $installed = json_decode(
            (string) file_get_contents(base_path('vendor/composer/installed.json')),
            true
        );

        $shopVersion = null;
        $blogVersion = null;
        $marketingVersion = null;
        foreach ($installed['packages'] as $package) {
            if (($package['name'] ?? null) === 'nreljed/shop-module') {
                $shopVersion = $package['version'] ?? null;
            }
            if (($package['name'] ?? null) === 'nreljed/blog') {
                $blogVersion = $package['version'] ?? null;
            }
            if (($package['name'] ?? null) === 'nreljed/marketing-module') {
                $marketingVersion = $package['version'] ?? null;
            }
        }

        $this->assertNotNull($shopVersion, 'nreljed/shop-module not found in installed.json');
        $this->assertNotNull($marketingVersion, 'nreljed/marketing-module not found in installed.json');

        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.system-health'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('modules', 5)
            ->where('modules.4.slug', 'shop')
            ->where('modules.4.package', 'nreljed/shop-module')
            ->where('modules.4.composer_version', $shopVersion)
            ->where('modules.2.slug', 'marketing')
            ->where('modules.2.package', 'nreljed/marketing-module')
            ->where('modules.2.composer_version', $marketingVersion)
            ->where('modules.1.composer_version', $blogVersion)
            ->where('modules.0.composer_version', null)
            ->where('modules.3.composer_version', null));
    }

    public function test_package_version_service_resolves_installed_versions(): void
    {
        $service = app(PackageVersionService::class);

        $this->assertNotNull($service->installedVersion('nreljed/shop-module'));
        $this->assertNotNull($service->installedVersion('nreljed/platform'));
        $this->assertNull($service->installedVersion('nreljed/does-not-exist'));
    }

    public function test_system_health_refresh_returns_json(): void
    {
        $response = $this->actingAs($this->admin())
            ->postJson(route('dashboard.admin.system-health.refresh'));

        $response->assertStatus(200);
        $response->assertJsonStructure(['core_package', 'modules']);
    }
}
