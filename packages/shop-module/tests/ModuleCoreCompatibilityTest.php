<?php

namespace Nreljed\ShopModule\Tests;

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
     * Caret check for the ^0.x convention used across the ecosystem.
     *
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