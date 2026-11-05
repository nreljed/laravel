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
 *   - the module declares nreljed/platform with a platform-major-bounded
 *     range — either the caret-zero shorthand (`^0.2`) or its explicit
 *     equivalent (`>=0.2 <1.0`), both of which mean "any 0.x at or above the
 *     floor, but never 1.0",
 *   - the CURRENT core release satisfies that constraint,
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
    public function test_registered_modules_declare_a_platform_major_bounded_range(): void
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

            $range = $this->platformRange($constraint);

            $this->assertNotNull(
                $range,
                "[{$package}] must pin platform with a platform-major-bounded range "
                ."(`^M.x` or `>=M.x <(M+1).0`), got [{$constraint}]"
            );

            $coreVersion = (string) ($this->composer($this->coreRoot, 'nreljed/platform')['version'] ?? '');

            $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $coreVersion, 'core composer.json version must be semver shaped');

            $this->assertTrue(
                $this->withinRange($range, $coreVersion),
                "[{$package}] declares [{$constraint}] but current core is {$coreVersion} — "
                .'bump the module range alongside the core minor'
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
     * Guards the guard: the range parser must reject constraints that would
     * let a platform minor become breaking.
     */
    public function test_the_range_parser_accepts_only_platform_major_bounded_constraints(): void
    {
        $accepted = ['^0', '^0.2', '^0.2.3', '^1', '^1.0', '>=0.2 <1.0', '>=0.2.0 <1.0.0', '>=1.0 <2.0'];

        foreach ($accepted as $constraint) {
            $this->assertNotNull($this->platformRange($constraint), "[{$constraint}] should be accepted");
        }

        $rejected = ['*', '0.2.0', '>=0.2', '>=0.2 <2.0', '>=1.0 <3.0'];

        foreach ($rejected as $constraint) {
            $this->assertNull($this->platformRange($constraint), "[{$constraint}] should be rejected");
        }

        $this->assertTrue($this->withinRange($this->platformRange('>=0.2 <1.0'), '0.3.0'));
        $this->assertFalse($this->withinRange($this->platformRange('>=0.2 <1.0'), '0.1.9'));
        $this->assertFalse($this->withinRange($this->platformRange('>=0.2 <1.0'), '1.0.0'));
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

    /**
     * Parse a platform constraint into an inclusive floor and exclusive ceiling.
     *
     * In this ecosystem a module range is deliberately platform-major bounded:
     * an additive platform minor never forces a module release, so a module
     * built against 0.2 keeps working on 0.3, 0.4, ... up to but not including
     * the 1.0 major. Only a platform MAJOR bump requires modules to update.
     *
     * Accepts the caret-zero shorthand (`^0`, `^0.2`, `^0.2.3`) and the
     * explicit equivalent (`>=0.2 <1.0`, `>=0.2.0 <1.0.0`). Returns null for
     * anything that is not platform-major bounded — including a caret range on
     * a non-zero major, which would make a platform minor breaking.
     *
     * @return array{min: array{int, int, int}, max: array{int, int, int}}|null
     */
    private function platformRange(string $constraint): ?array
    {
        $constraint = trim($constraint);

        if (preg_match('/^\^(\d+)(?:\.(\d+))?(?:\.(\d+))?$/', $constraint, $m) === 1) {
            $major = (int) $m[1];

            return [
                'min' => [$major, isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0, isset($m[3]) && $m[3] !== '' ? (int) $m[3] : 0],
                'max' => [$major + 1, 0, 0],
            ];
        }

        if (preg_match('/^>=\s*(\d+)(?:\.(\d+))?(?:\.(\d+))?\s+<\s*(\d+)(?:\.(\d+))?(?:\.(\d+))?$/', $constraint, $m) === 1) {
            $min = [(int) $m[1], (int) ($m[2] ?? 0), (int) ($m[3] ?? 0)];
            $max = [(int) $m[4], (int) ($m[5] ?? 0), (int) ($m[6] ?? 0)];

            // The range must be platform-major bounded: ceiling must be exactly
            // one major above the floor's major, with no minor/patch on the ceiling.
            if ($max[0] !== $min[0] + 1 || $max[1] !== 0 || $max[2] !== 0) {
                return null;
            }

            return ['min' => $min, 'max' => $max];
        }

        return null;
    }

    /**
     * @param  array{min: array{int, int, int}, max: array{int, int, int}}  $range
     */
    private function withinRange(array $range, string $version): bool
    {
        $v = array_pad(array_map('intval', explode('.', $version)), 3, 0);

        return $v >= $range['min'] && $v < $range['max'];
    }
}
