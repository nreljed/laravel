<?php

namespace Nreljed\Platform\Tests;

use Nreljed\Platform\Services\CoreVersionService;
use PHPUnit\Framework\TestCase;

/**
 * Contract test for the deploy SBOM mapping.
 *
 * Deliberately framework-free: fromInstalledJson() interprets a decoded
 * composer installed.json document without booting the application, so the
 * bundled-package enumeration can be asserted in the package's own CI.
 */
final class CoreVersionServiceContractTest extends TestCase
{
    public function test_lists_only_nreljed_packages_sorted_by_name(): void
    {
        $data = ['packages' => [
            ['name' => 'laravel/framework', 'version' => 'v13.0.0'],
            ['name' => 'nreljed/shop-module', 'version' => 'v0.4.0'],
            ['name' => 'nreljed/booking-module', 'version' => 'v0.2.0'],
            ['name' => 'nreljed/blog', 'version' => 'v0.9.0'],
            ['name' => 'nreljed/platform', 'version' => 'v0.1.57'],
        ]];

        $result = CoreVersionService::fromInstalledJson($data);

        $this->assertSame(['nreljed/blog', 'nreljed/booking-module', 'nreljed/platform', 'nreljed/shop-module'], array_column($result, 'name'));
    }

    public function test_reads_version_and_sha_from_source_reference(): void
    {
        $data = ['packages' => [[
            'name' => 'nreljed/platform',
            'version' => 'v0.1.57',
            'source' => ['url' => 'https://github.com/nreljed/platform.git', 'reference' => 'b803a13abc'],
        ]]];

        $result = CoreVersionService::fromInstalledJson($data);

        $this->assertSame([
            'name' => 'nreljed/platform',
            'version' => 'v0.1.57',
            'sha' => 'b803a13abc',
            'repo' => 'nreljed/platform',
        ], $result[0]);
    }

    public function test_falls_back_to_package_name_when_no_install_url(): void
    {
        $data = ['packages' => [[
            'name' => 'nreljed/blog',
            'version' => 'dev-main',
            'dist' => ['reference' => 'abc123'],
        ]]];

        $result = CoreVersionService::fromInstalledJson($data);

        $this->assertSame('nreljed/blog', $result[0]['repo']);
        $this->assertSame('abc123', $result[0]['sha']);
    }

    public function test_null_and_blank_members_become_null(): void
    {
        $data = ['packages' => [[
            'name' => 'nreljed/shop-module',
            'version' => '',
            'source' => ['url' => 'https://github.com/nreljed/shop-module.git'],
        ]]];

        $result = CoreVersionService::fromInstalledJson($data);

        $this->assertNull($result[0]['version']);
        $this->assertNull($result[0]['sha']);
        $this->assertSame('nreljed/shop-module', $result[0]['repo']);
    }

    public function test_degraded_input_returns_empty_list(): void
    {
        $this->assertSame([], CoreVersionService::fromInstalledJson(null));
        $this->assertSame([], CoreVersionService::fromInstalledJson([]));
        $this->assertSame([], CoreVersionService::fromInstalledJson(['packages' => 'nope']));
    }
}
