<?php

namespace Tests\Feature\Modules\Shop;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeployCheckCoreVersionTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = 'test-deploy-token-'.Str::random(16);
        Config::set('deploy.token', $this->token);
    }

    /**
     * @return array{version: string|null, reference: string|null}
     */
    private function installedPlatformState(): array
    {
        foreach (['vendor/composer/installed.json', 'composer.lock'] as $relative) {
            $path = base_path($relative);
            if (! is_file($path)) {
                continue;
            }
            $data = json_decode((string) file_get_contents($path), true);
            $packages = array_merge($data['packages'] ?? [], $data['packages-dev'] ?? []);
            foreach ($packages as $package) {
                if (($package['name'] ?? null) === 'nreljed/platform') {
                    return [
                        'version' => $package['version'] ?? null,
                        'reference' => $package['source']['reference'] ?? $package['dist']['reference'] ?? null,
                    ];
                }
            }
        }

        return ['version' => null, 'reference' => null];
    }

    public function test_check_reports_core_commit_from_installed_state(): void
    {
        $versionFile = base_path('.deployed-version');
        $lockFile = base_path('.deploy-lock');

        Http::fake([
            'api.github.com/repos/nreljed/laravel.shop/releases*' => Http::response([
                ['tag_name' => 'v0.1.34', 'assets' => []],
            ], 200),
        ]);

        File::put($versionFile, 'v0.1.34');

        try {
            $response = $this->getJson('/deploy/check?token='.$this->token);

            $response->assertOk();
            $response->assertJsonPath('status', 'current');
            $response->assertJsonPath('tag', 'v0.1.34');
            $response->assertJsonPath('core.name', 'platform');
            $response->assertJsonPath('core.repo', 'nreljed/platform');

            $payload = $response->json();
            $this->assertIsString($payload['core']['version'] ?? null);
            $this->assertNotNull($payload['core']['sha'], 'core sha must resolve from composer.lock');
            $this->assertSame(
                $this->installedPlatformState()['reference'],
                $payload['core']['sha']
            );
        } finally {
            File::delete($versionFile);
            File::delete($lockFile);
        }
    }

    public function test_check_reports_core_version_even_without_marker_files(): void
    {
        $versionFile = base_path('.deployed-version');
        $lockFile = base_path('.deploy-lock');

        Http::fake([
            'api.github.com/repos/nreljed/laravel.shop/releases*' => Http::response([
                ['tag_name' => 'v0.1.34', 'assets' => []],
            ], 200),
        ]);

        File::put($versionFile, 'v0.1.34');
        File::delete(storage_path('app/core.json'));
        File::delete(storage_path('app/core.sha'));

        try {
            $response = $this->getJson('/deploy/check?token='.$this->token);

            $response->assertOk();
            $response->assertJsonPath('status', 'current');
            $response->assertJsonPath('tag', 'v0.1.34');
            $response->assertJsonPath('core.name', 'platform');
            $response->assertJsonPath('core.repo', 'nreljed/platform');

            // No build-time markers exist, yet the core block still resolves
            // from composer.lock/installed.json (ground truth).
            $payload = $response->json();
            $this->assertSame(
                $this->installedPlatformState()['version'],
                $payload['core']['version']
            );
            $this->assertNotNull($payload['core']['sha']);
        } finally {
            File::delete($versionFile);
            File::delete($lockFile);
        }
    }
}
