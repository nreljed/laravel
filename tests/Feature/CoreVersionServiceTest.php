<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Nreljed\Platform\Services\CoreVersionService;
use Tests\TestCase;

class CoreVersionServiceTest extends TestCase
{
    protected string $jsonPath;

    protected string $shaPath;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = getmypid();
        $this->jsonPath = storage_path("app/core-{$suffix}.json");
        $this->shaPath = storage_path("app/core-{$suffix}.sha");

        Config::set('core.json_path', $this->jsonPath);
        Config::set('core.sha_path', $this->shaPath);

        File::delete([$this->jsonPath, $this->shaPath]);
    }

    protected function tearDown(): void
    {
        File::delete([$this->jsonPath, $this->shaPath]);

        parent::tearDown();
    }

    public function test_info_reads_from_core_json_when_present(): void
    {
        File::put($this->jsonPath, json_encode([
            'name' => 'platform',
            'version' => '1.2.3',
            'sha' => '50e9c0fcf28d365295c46af725da4822456c3574',
            'repo' => 'nreljed/platform',
        ]));

        $service = new CoreVersionService;
        $info = $service->info();

        $this->assertSame('platform', $info['name']);
        $this->assertSame('1.2.3', $info['version']);
        $this->assertSame('50e9c0fcf28d365295c46af725da4822456c3574', $info['sha']);
        $this->assertSame('nreljed/platform', $info['repo']);
        $this->assertSame('50e9c0f', $service->shortSha());
        $this->assertSame(
            'https://github.com/nreljed/platform/commit/50e9c0fcf28d365295c46af725da4822456c3574',
            $service->commitUrl()
        );
    }

    public function test_commit_url_uses_recorded_repo_when_set(): void
    {
        File::put($this->jsonPath, json_encode([
            'name' => 'platform',
            'version' => '1.2.3',
            'sha' => '50e9c0fcf28d365295c46af725da4822456c3574',
            'repo' => 'nreljed/laravel',
        ]));

        $service = new CoreVersionService;

        $this->assertSame(
            'https://github.com/nreljed/laravel/commit/50e9c0fcf28d365295c46af725da4822456c3574',
            $service->commitUrl()
        );
    }

    public function test_commit_url_defaults_to_core_repo_when_unrecorded(): void
    {
        File::put($this->shaPath, '50e9c0fcf28d365295c46af725da4822456c3574'.PHP_EOL);

        $service = new CoreVersionService;

        $this->assertSame(
            'https://github.com/nreljed/platform/commit/50e9c0fcf28d365295c46af725da4822456c3574',
            $service->commitUrl()
        );
    }

    public function test_info_falls_back_to_legacy_core_sha_marker(): void
    {
        File::put($this->shaPath, '50e9c0fabcd1234567890'.PHP_EOL);

        $info = (new CoreVersionService)->info();

        $this->assertSame('platform', $info['name']);
        $this->assertSame('50e9c0fabcd1234567890', $info['sha']);
    }

    public function test_info_returns_null_sha_when_unrecorded(): void
    {
        $info = (new CoreVersionService)->info();

        $expectedVersion = json_decode(
            (string) file_get_contents(base_path('vendor/nreljed/platform/composer.json')),
            true
        )['version'];

        $this->assertSame('platform', $info['name']);
        $this->assertSame($expectedVersion, $info['version']);
        $this->assertNull($info['sha']);
    }

    public function test_core_version_command_prints_table(): void
    {
        File::put($this->jsonPath, json_encode([
            'name' => 'platform',
            'version' => '2.0.0',
            'sha' => 'abcdef0123456789',
        ]));

        $this->artisan('core:version')
            ->expectsOutputToContain('platform')
            ->expectsOutputToContain('2.0.0')
            ->expectsOutputToContain('abcdef0')
            ->assertExitCode(0);
    }

    public function test_core_version_command_prints_json(): void
    {
        File::put($this->jsonPath, json_encode([
            'name' => 'platform',
            'version' => '2.0.0',
            'sha' => 'abcdef0123456789',
        ]));

        $code = Artisan::call('core:version', ['--format' => 'json']);

        $output = Artisan::output();
        $this->assertSame(0, $code);
        $this->assertStringContainsString('"name": "platform"', $output);
        $this->assertStringContainsString('"version": "2.0.0"', $output);
        $this->assertStringContainsString('"sha": "abcdef0123456789"', $output);
    }
}
