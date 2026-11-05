<?php

namespace Nreljed\MarketingModule\Tests;

use Composer\InstalledVersions;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Structural guard for module ↔ platform version alignment:
 *
 *   - the module must pin platform with a caret ^0.x constraint that the
 *     installed platform release actually satisfies.
 *   - the module's own composer.json version field must be present and semver
 *     shaped, so releases bump it cleanly.
 */
final class ModuleCoreCompatibilityTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__);
    }

    public function test_declares_a_platform_constraint(): void
    {
        $composer = $this->composer();

        $this->assertArrayHasKey(
            'nreljed/platform',
            $composer['require'],
            'composer.json must require nreljed/platform'
        );
    }

    public function test_installed_core_satisfies_the_declared_constraint(): void
    {
        $installed = $this->installedPlatformManifest();

        if ($installed === null) {
            $this->markTestSkipped('platform is not installed (run composer install)');
        }

        $constraint = (string) $this->composer()['require']['nreljed/platform'];
        $core = json_decode((string) file_get_contents($installed), true);
        $coreVersion = (string) ($core['version'] ?? '');

        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $coreVersion, 'installed core version malformed');
        $this->assertTrue(
            $this->satisfiesCaretZero($constraint, $coreVersion),
            "installed core $coreVersion does not satisfy declared constraint $constraint"
        );
    }

    /**
     * Locate the platform manifest through Composer, so the check works both
     * in a standalone checkout and as a dependency of a consuming app.
     */
    private function installedPlatformManifest(): ?string
    {
        if (! class_exists(InstalledVersions::class)) {
            return null;
        }

        try {
            $path = InstalledVersions::getInstallPath('nreljed/platform');
        } catch (Throwable) {
            return null;
        }

        if (! is_string($path) || $path === '') {
            return null;
        }

        $manifest = $path.DIRECTORY_SEPARATOR.'composer.json';

        return is_file($manifest) ? $manifest : null;
    }

    public function test_module_version_field_is_semver_shaped(): void
    {
        $version = (string) ($this->composer()['version'] ?? '');

        $this->assertMatchesRegularExpression(
            '/^\d+\.\d+\.\d+$/',
            $version,
            'composer.json version must be semver shaped (X.Y.Z)'
        );
    }

    public function test_package_and_frontend_versions_agree(): void
    {
        $composer = $this->composer();
        $package = json_decode((string) file_get_contents($this->root.'/package.json'), true);

        $this->assertSame(
            (string) ($composer['version'] ?? ''),
            (string) ($package['version'] ?? ''),
            'composer.json and package.json must carry the same version'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function composer(): array
    {
        return json_decode((string) file_get_contents($this->root.'/composer.json'), true);
    }

    private function satisfiesCaretZero(string $constraint, string $version): bool
    {
        return $this->satisfiesPlatformConstraint($constraint, $version);
    }

    private function satisfiesPlatformConstraint(string $constraint, string $version): bool
    {
        $constraint = trim($constraint);

        if (preg_match('/^\^(\d+)(?:\.(\d+))?(?:\.(\d+))?$/' , $constraint, $m)) {
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

        if (preg_match('/^>=\s*(\d+)\.(\d+)(?:\.(\d+))?\s*<\s*(\d+)\.0(?:\.0)?$/' , $constraint, $m)) {
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
