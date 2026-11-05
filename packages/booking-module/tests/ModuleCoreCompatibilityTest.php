<?php

namespace Nreljed\BookingModule\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Structural guard for module ↔ platform version alignment:
 *
 *   - the module must pin platform with a caret ^0.x constraint that the
 *     installed platform release actually satisfies (matching how consumers pin
 *     platform via composer.lock).
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
        $installed = $this->root.'/vendor/nreljed/platform/composer.json';

        if (! is_file($installed)) {
            $this->markTestSkipped('platform not installed in vendor (run composer install)');
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

    public function test_module_version_field_is_semver_shaped(): void
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

        $this->fail("unsupported constraint $constraint - expected ^M.x or >=M.x <(M+1).0");

        return false;
    }
}
