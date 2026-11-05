<?php

namespace Nreljed\Blog\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Structural guard for blog  platform kernel version alignment:
 *
 *   - the package must pin nreljed/platform with a platform-major-bounded
 *     constraint that the installed platform release actually satisfies.
 *   - the package's own composer.json version field must be present and semver
 *     shaped, so releases bump it cleanly.
 */
final class BlogCoreCompatibilityTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__);
    }

    public function test_declares_a_platform_kernel_constraint(): void
    {
        $composer = $this->composer();

        $this->assertArrayHasKey(
            'nreljed/platform',
            $composer['require'],
            'composer.json must require nreljed/platform'
        );
    }

    public function test_installed_platform_satisfies_the_declared_constraint(): void
    {
        $installed = $this->root.'/vendor/nreljed/platform/composer.json';

        if (! is_file($installed)) {
            $this->markTestSkipped('platform not installed in vendor (run composer install)');
        }

        $constraint = (string) $this->composer()['require']['nreljed/platform'];
        $platform = json_decode((string) file_get_contents($installed), true);
        $platformVersion = (string) ($platform['version'] ?? '');

        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $platformVersion, 'installed platform version malformed');
        $this->assertTrue(
            $this->satisfiesPlatformConstraint($constraint, $platformVersion),
            "installed platform $platformVersion does not satisfy declared constraint $constraint"
        );
    }

    public function test_package_version_field_is_semver_shaped(): void
    {
        $version = (string) ($this->composer()['version'] ?? '');

        $this->assertMatchesRegularExpression(
            '/^\d+\.\d+\.\d+$/',
            $version,
            'composer.json version must be semver shaped (X.Y.Z)'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function composer(): array
    {
        return json_decode((string) file_get_contents($this->root.'/composer.json'), true);
    }

    private function satisfiesPlatformConstraint(string $constraint, string $version): bool
    {
        $constraint = trim($constraint);

        if (preg_match('/^\^(\d+)(?:\.(\d+))?(?:\.(\d+))?$/', $constraint, $m)) {
            [$major, $minor, $patch] = array_pad(explode('.', $version), 3, '0');
            $declaredMajor = (int) $m[1];
            $declaredMinor = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0;
            $declaredPatch = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : 0;

            if ((int) $major !== $declaredMajor) {
                return false;
            }
            if ((int) $minor < $declaredMinor) {
                return false;
            }
            if ((int) $minor === $declaredMinor && (int) $patch < $declaredPatch) {
                return false;
            }

            return true;
        }

        if (preg_match('/^>=\s*(\d+)\.(\d+)(?:\.(\d+))?\s*<\s*(\d+)\.0(?:\.0)?$/', $constraint, $m)) {
            [$major, $minor, $patch] = array_pad(explode('.', $version), 3, '0');
            $floorMajor = (int) $m[1];
            $floorMinor = (int) $m[2];
            $floorPatch = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : 0;
            $ceilMajor = (int) $m[4];

            if ((int) $major < $floorMajor || (int) $major > $ceilMajor - 1) {
                return false;
            }
            if ((int) $major === $floorMajor) {
                if ((int) $minor < $floorMinor) {
                    return false;
                }
                if ((int) $minor === $floorMinor && (int) $patch < $floorPatch) {
                    return false;
                }
            }

            return (int) $major < $ceilMajor;
        }

        $this->fail("unsupported constraint $constraint - expected ^M.x or >=M.x <(M+1).0");

        return false;
    }
}
