<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Nreljed\Platform\Services\CoreVersionService;
use Tests\TestCase;

class CoreVersionServiceTest extends TestCase
{
    private const PACKAGE = 'nreljed/platform';

    /**
     * @return array{version: string|null, reference: string|null}
     */
    private function installedState(): array
    {
        $installedJson = base_path('vendor/composer/installed.json');
        $lockJson = base_path('composer.lock');

        $meta = json_decode((string) file_get_contents($installedJson), true);
        foreach ($meta['packages'] ?? [] as $package) {
            if (($package['name'] ?? null) === self::PACKAGE) {
                return [
                    'version' => $package['version'] ?? null,
                    'reference' => $package['source']['reference'] ?? $package['dist']['reference'] ?? null,
                    'url' => $package['source']['url'] ?? $package['dist']['url'] ?? null,
                ];
            }
        }

        $lock = json_decode((string) file_get_contents($lockJson), true);
        foreach (array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []) as $package) {
            if (($package['name'] ?? null) === self::PACKAGE) {
                return [
                    'version' => $package['version'] ?? null,
                    'reference' => $package['source']['reference'] ?? $package['dist']['reference'] ?? null,
                    'url' => $package['source']['url'] ?? $package['dist']['url'] ?? null,
                ];
            }
        }

        return ['version' => null, 'reference' => null, 'url' => null];
    }

    public function test_info_reads_authoritative_install_state(): void
    {
        $state = $this->installedState();
        $this->assertNotNull($state['version'], 'nreljed/platform must be installed');

        $info = (new CoreVersionService)->info();

        $this->assertSame('platform', $info['name']);
        $this->assertSame($state['version'], $info['version']);
        $this->assertSame($state['reference'], $info['sha']);
        $this->assertSame('nreljed/platform', $info['repo']);
    }

    public function test_short_sha_truncates_to_seven_characters(): void
    {
        $sha = (new CoreVersionService)->info()['sha'];

        if ($sha === null) {
            $this->markTestSkipped('Installed platform package has no resolved commit reference');
        }

        $this->assertSame(substr($sha, 0, 7), (new CoreVersionService)->shortSha());
    }

    public function test_commit_url_points_to_platform_repo(): void
    {
        $sha = (new CoreVersionService)->info()['sha'];

        if ($sha === null) {
            $this->markTestSkipped('Installed platform package has no resolved commit reference');
        }

        $this->assertSame(
            'https://github.com/nreljed/platform/commit/'.$sha,
            (new CoreVersionService)->commitUrl()
        );
    }

    public function test_core_version_command_prints_table(): void
    {
        $state = $this->installedState();

        $this->artisan('core:version')
            ->expectsOutputToContain('platform')
            ->expectsOutputToContain($state['version'])
            ->assertExitCode(0);
    }

    public function test_core_version_command_prints_json(): void
    {
        $state = $this->installedState();

        $code = Artisan::call('core:version', ['--format' => 'json']);

        $output = Artisan::output();
        $this->assertSame(0, $code);
        $this->assertStringContainsString('"name": "platform"', $output);
        $this->assertStringContainsString('"version": "'.$state['version'].'"', $output);
        $this->assertStringContainsString('"repo": "nreljed/platform"', $output);
    }
}
