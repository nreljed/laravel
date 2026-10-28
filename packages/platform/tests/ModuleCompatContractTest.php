<?php

namespace Nreljed\Platform\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Core-side guard for the module ↔ platform compatibility contract
 * (core-compat.json).
 *
 * The core repo is the hub of the ecosystem, so it owns the registry: every
 * known consumer module is listed in core-compat.json. This test locates each
 * module's checkout (sibling of the core repo by default, overridable via the
 * CORE_MODULE_PATHS env — semicolon separated absolute paths) and asserts:
 *
 *   - the module declares nreljed/platform with a caret-zero (^0.x)
 *     constraint,
 *   - the CURRENT core release satisfies that constraint — a core minor bump
 *     therefore must ship together with the modules updating their ranges,
 *   - the module's own version field stays semver shaped.
 *
 * The reverse direction (the installed core must satisfy the module's
 * constraint) is asserted by each module's own ModuleCoreCompatibilityTest, so
 * both sides of the contract are enforced without a constraint solver.
 *
 * @coversNothing
 */
final class ModuleCompatContractTest extends TestCase
{
    private string $coreRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->coreRoot = dirname(__DIR__);
    }

    public function test_core_compat_manifest_exists_and_is_valid(): void
    {
        $manifest = $this->manifest();

        $this->assertSame(1, $manifest['schema'], 'core-compat.json schema must be 1');
        $this->assertSame('nreljed/platform', $manifest['package']);
        $this->assertIsArray($manifest['modules']);
        $this->assertNotEmpty($manifest['modules'], 'at least one module must be registered in core-compat.json');
    }

    /**
     * @depends test_core_compat_manifest_exists_and_is_valid
     */
    public function test_registered_modules_resolve_and_satisfy_caret_zero(): void
    {
        foreach ($this->manifest()['modules'] as $entry) {
            $package = (string) ($entry['package'] ?? '');
            $moduleRoot = $this->resolveModuleRoot($entry);

            if (! is_dir($moduleRoot)) {
                $this->markTestSkipped("module [{$package}] checkout not found at {$moduleRoot} (set CORE_MODULE_PATHS)");
            }

            $composer = $this->composer($moduleRoot, $package);

            $this->assertArrayHasKey(
                'nreljed/platform',
                $composer['require'],
                "[{$package}] composer.json must require nreljed/platform"
            );

            $constraint = (string) $composer['require']['nreljed/platform'];

            $this->assertMatchesRegularExpression(
                '/^\^0(?:\.\d+){0,2}$/',
                $constraint,
                "[{$package}] must pin platform with a caret-zero ^0.x range, got [{$constraint}]"
            );

            $coreVersion = (string) ($this->composer($this->coreRoot, 'nreljed/platform')['version'] ?? '');

            $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $coreVersion, 'core composer.json version must be semver shaped');

            $this->assertTrue(
                $this->satisfiesCaretZero($constraint, $coreVersion),
                "[{$package}] declares [{$constraint}] but current core is {$coreVersion} — bump the module range alongside the core minor"
            );

            $moduleVersion = (string) ($composer['version'] ?? '');

            $this->assertMatchesRegularExpression(
                '/^\d+\.\d+\.\d+$/',
                $moduleVersion,
                "[{$package}] composer.json version must be semver shaped (X.Y.Z)"
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(): array
    {
        $manifest = json_decode((string) file_get_contents($this->coreRoot.'/core-compat.json'), true);

        if (! is_array($manifest)) {
            $this->fail('core-compat.json is not valid JSON');
        }

        return $manifest;
    }

    private function resolveModuleRoot(array $entry): string
    {
        $paths = array_values(array_filter(array_map(
            'trim',
            explode(PATH_SEPARATOR, (string) getenv('CORE_MODULE_PATHS')),
        )));

        $probe = $paths ?: [dirname($this->coreRoot).DIRECTORY_SEPARATOR.($entry['repo_dir'] ?? '')];

        foreach ($probe as $path) {
            if (is_dir($path)) {
                $candidate = $path.DIRECTORY_SEPARATOR.'composer.json';

                if (is_file($candidate)) {
                    return $path;
                }
            }
        }

        return $probe[0] ?? '';
    }

    /**
     * @return array<string, mixed>
     */
    private function composer(string $root, string $package): array
    {
        $composer = json_decode((string) file_get_contents($root.'/composer.json'), true);

        if (! is_array($composer)) {
            $this->fail("[{$package}] composer.json is not valid JSON");
        }

        return $composer;
    }

    private function satisfiesCaretZero(string $constraint, string $version): bool
    {
        if (! preg_match('/^\^0(?:\.(\d+))?(?:\.(\d+))?$/', $constraint, $m)) {
            $this->fail("unsupported constraint [{$constraint}] — expected ^0.x caret-zero");
        }

        $v = array_pad(array_map('intval', explode('.', $version)), 3, 0);

        $min = [0, isset($m[1]) && $m[1] !== '' ? (int) $m[1] : 0, isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0];
        $max = isset($m[1]) && $m[1] !== ''
            ? [0, $min[1] + 1, 0]
            : [1, 0, 0];

        return $v >= $min && $v < $max;
    }
}
