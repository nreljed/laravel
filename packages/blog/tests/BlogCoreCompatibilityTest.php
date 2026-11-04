<?php

namespace Nreljed\Blog\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Structural guard for blog ↔ platform kernel version alignment:
 *
 *   - the package must pin nreljed/platform with a caret ^0.x constraint that
 *     the installed platform release actually satisfies.
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
            $this->satisfiesCaretZero($constraint, $platformVersion),
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

    private function satisfiesCaretZero(string $constraint, string $version): bool
    {
        if (preg_match('/^>=\s*0\.(\d+)(?:\.(\d+))?\s*<\s*1\.0(?:\.0)?$/', $constraint, $m)) {
            [$major, $minor, $patch] = array_pad(explode('.', $version), 3, '0');

            if ($major !== '0') {
                return false;
            }

            $declaredMinor = (int) $m[1];
            $declaredPatch = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0;

            if ((int) $minor < $declaredMinor) {
                return false;
            }

            if ((int) $minor === $declaredMinor && (int) $patch < $declaredPatch) {
                return false;
            }

            return true;
        }

        if (! preg_match('/^\^0(?:\.(\d+)){0,2}$/', $constraint, $m)) {
            $this->fail("unsupported constraint $constraint - expected ^0.x or >=0.x <1.0");
        }

        [$major, $minor, $patch] = array_pad(explode('.', $version), 3, '0');

        if ($major !== '0') {
            return false;
        }

        $declaredMinor = count($m) > 1 && $m[1] !== '' ? (int) $m[1] : 0;

        return (int) $minor >= $declaredMinor;
    }
}