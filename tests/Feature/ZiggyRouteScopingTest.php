<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZiggyRouteScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_ziggy_payload_excludes_scoped_backend_routes(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('ziggy'));

        $page = $response->viewData('page');
        $routes = $page['props']['ziggy']['routes'];

        $this->assertArrayHasKey('dashboard.index', $routes, 'Frontend-referenced routes must stay in the ziggy payload.');
        $this->assertArrayHasKey('blog.index', $routes);
        $this->assertArrayHasKey('login', $routes);
        $this->assertArrayHasKey('home', $routes);

        $this->assertNotPresent($routes, 'deploy.*');
        $this->assertNotPresent($routes, 'maintenance.*');
        $this->assertNotPresent($routes, 'csp.report');
        $this->assertNotPresent($routes, 'robots');
        $this->assertNotPresent($routes, 'sitemap');
        $this->assertNotPresent($routes, 'storage.local');
        $this->assertNotPresent($routes, 'storage.local.upload');
        $this->assertNotPresent($routes, 'sanctum.csrf-cookie');
        $this->assertNotPresent($routes, 'verification.notice');
        $this->assertNotPresent($routes, 'verification.verified');
        $this->assertNotPresent($routes, 'verification.verify');
        $this->assertNotPresent($routes, 'password.reset');
        $this->assertNotPresent($routes, 'password.update');
    }

    private function assertNotPresent(array $routes, string $pattern): void
    {
        if (str_ends_with($pattern, '.*')) {
            $prefix = rtrim($pattern, '*');
            $matches = array_filter(array_keys($routes), fn ($name) => str_starts_with($name, $prefix));

            $this->assertEmpty($matches, "Ziggy payload must not contain routes matching {$pattern}, found: ".implode(', ', $matches));
        } else {
            $this->assertArrayNotHasKey($pattern, $routes, "Ziggy payload must not contain {$pattern}.");
        }
    }
}
