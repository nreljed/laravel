<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ContentSecurityPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_csp_header_is_present_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertHeader('Content-Security-Policy');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("script-src 'self' 'nonce-", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString('report-to csp-endpoint', $csp);
    }

    public function test_csp_uses_nonce_for_inline_scripts(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $response = $this->get('/');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9+\/=]+'/", $csp);
    }

    public function test_csp_connect_src_includes_websocket_url(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $response = $this->get('/');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('connect-src ', $csp);

        $appUrl = config('app.url');
        $expectedWs = str_starts_with($appUrl, 'https://')
            ? str_replace('https://', 'wss://', $appUrl)
            : str_replace('http://', 'ws://', $appUrl);
        $this->assertStringContainsString($expectedWs, $csp);
    }

    public function test_csp_emits_reporting_endpoints_header(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $response = $this->get('/');

        $response->assertHeader('Reporting-Endpoints');
        $this->assertStringContainsString('/csp-report', $response->headers->get('Reporting-Endpoints'));
    }

    public function test_csp_header_is_present_in_staging(): void
    {
        $this->app->detectEnvironment(fn () => 'staging');

        $response = $this->get('/');

        $response->assertHeader('Content-Security-Policy');
    }

    public function test_csp_header_is_absent_in_local_by_default(): void
    {
        $this->app->detectEnvironment(fn () => 'local');

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertHeaderMissing('Content-Security-Policy');
    }

    public function test_csp_header_present_in_local_when_config_enabled(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        Config::set('core.csp_enabled', true);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertHeader('Content-Security-Policy');
    }
}
