<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\Platform\Http\Middleware\CheckMaintenanceMode;
use Nreljed\Platform\Services\SettingsService;
use Tests\TestCase;

/**
 * Maintenance mode must render, not 500.
 *
 * `CheckMaintenanceMode` lives in the platform package and renders the
 * package's own `maintenance` blade, which is only published under the
 * `core::` namespace. Rendering it by bare name works on a machine where the
 * views were published into `resources/views`, and returns
 * "View [maintenance] not found" - a 500 instead of a 503 - everywhere else.
 * The same mistake made `/maintenance/recovery` 500 in production.
 *
 * This renders the real middleware through a real request, so it fails if the
 * namespace ever regresses.
 *
 * The probe targets `/services` because that is a guest-reachable route that
 * actually carries the `maintenance` middleware. The storefront (`/`, `/store`)
 * and the blog (`/blog`) are deliberately not in this test: they are registered
 * by the shop and blog packages without the middleware, so maintenance mode
 * does not mask them. The second test pins that down so the assumption cannot
 * drift silently.
 */
class MaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function tearDown(): void
    {
        SettingsService::set('maintenance_mode', '0');

        parent::tearDown();
    }

    public function test_guests_see_the_maintenance_page_instead_of_an_error(): void
    {
        SettingsService::set('maintenance_mode', '1');
        SettingsService::set('maintenance_message', 'Back in a moment.');

        $response = $this->get('/services');

        $response->assertStatus(503);
        $response->assertSee('Back in a moment.', false);
        $response->assertHeader('Retry-After');

        $this->assertStringNotContainsString(
            'View [maintenance] not found',
            $response->getContent(),
        );
    }

    public function test_the_package_middleware_is_what_gates_public_pages(): void
    {
        // Guards the assumption the test above rests on: platform-owned public
        // pages carry `maintenance`, while the storefront and blog package
        // routes (`/`, `/store`, `/blog`) do not - so a probe against those
        // would return 200 even when maintenance mode is on.
        $gated = collect(app('router')->getRoutes())
            ->filter(fn ($route) => collect($route->gatherMiddleware())->contains(
                fn ($middleware) => $middleware === 'maintenance'
                    || $middleware === CheckMaintenanceMode::class,
            ))
            ->map(fn ($route) => $route->uri())
            ->values();

        $this->assertContains('services', $gated);
        $this->assertContains('about', $gated);
        $this->assertNotContains('store', $gated);
        $this->assertNotContains('blog', $gated);
    }

    public function test_admins_bypass_maintenance_mode(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        SettingsService::set('maintenance_mode', '1');

        $this->actingAs($user)->get('/services')->assertOk();
    }
}
