<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Nreljed\DashboardCore\Services\CoreVersionService;
use Tests\TestCase;

class CoreVersionServiceTest extends TestCase
{
    protected string $jsonPath;

    protected string $shaPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jsonPath = storage_path('app/core.json');
        $this->shaPath = storage_path('app/core.sha');

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
            'name' => 'dashboard-core',
            'version' => '1.2.3',
            'sha' => '50e9c0fcf28d365295c46af725da4822456c3574',
        ]));

        $service = new CoreVersionService;
        $info = $service->info();

        $this->assertSame('dashboard-core', $info['name']);
        $this->assertSame('1.2.3', $info['version']);
        $this->assertSame('50e9c0fcf28d365295c46af725da4822456c3574', $info['sha']);
        $this->assertSame('50e9c0f', $service->shortSha());
        $this->assertSame(
            'https://github.com/nreljed/laravel/commit/50e9c0fcf28d365295c46af725da4822456c3574',
            $service->commitUrl()
        );
    }

    public function test_info_falls_back_to_legacy_core_sha_marker(): void
    {
        File::put($this->shaPath, '50e9c0fabcd1234567890'.PHP_EOL);

        $info = (new CoreVersionService)->info();

        $this->assertSame('dashboard-core', $info['name']);
        $this->assertSame('50e9c0fabcd1234567890', $info['sha']);
    }

    public function test_info_returns_nulls_when_unrecorded(): void
    {
        $info = (new CoreVersionService)->info();

        $this->assertSame('dashboard-core', $info['name']);
        $this->assertNull($info['version']);
        $this->assertNull($info['sha']);
    }

    public function test_core_version_command_prints_table(): void
    {
        File::put($this->jsonPath, json_encode([
            'name' => 'dashboard-core',
            'version' => '2.0.0',
            'sha' => 'abcdef0123456789',
        ]));

        $this->artisan('core:version')
            ->expectsOutputToContain('dashboard-core')
            ->expectsOutputToContain('2.0.0')
            ->expectsOutputToContain('abcdef0')
            ->assertExitCode(0);
    }

    public function test_core_version_command_prints_json(): void
    {
        File::put($this->jsonPath, json_encode([
            'name' => 'dashboard-core',
            'version' => '2.0.0',
            'sha' => 'abcdef0123456789',
        ]));

        $code = Artisan::call('core:version', ['--format' => 'json']);

        $output = Artisan::output();
        $this->assertSame(0, $code);
        $this->assertStringContainsString('"name": "dashboard-core"', $output);
        $this->assertStringContainsString('"version": "2.0.0"', $output);
        $this->assertStringContainsString('"sha": "abcdef0123456789"', $output);
    }
}
