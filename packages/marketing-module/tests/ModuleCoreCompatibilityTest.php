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
        if (! preg_match('/^\^0(?:\.(\d+)){0,2}$/', $constraint, $m)) {
            $this->fail("unsupported constraint $constraint - expected a ^0.x caret range");
        }

        [$major, $minor, $patch] = array_pad(explode('.', $version), 3, '0');

        if ($major !== '0') {
            return false;
        }

        $declaredMinor = count($m) > 1 && $m[1] !== '' ? (int) $m[1] : 0;

        return (int) $minor >= $declaredMinor;
    }
}
