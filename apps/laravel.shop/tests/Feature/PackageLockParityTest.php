<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The vendor tree must be the tree the lock file describes.
 *
 * This exists because of a specific, expensive mistake: the app's vendor held
 * a Composer path repository pointing at a working tree, so the version on
 * disk did not match the lock, and `composer update` reported success while
 * silently reusing the existing checkout ("Source already present"). Tests
 * then passed against code that production would never receive.
 *
 * The suite cannot detect that on its own, because whatever is in vendor is
 * what gets exercised. This reads the two files and compares them, so the
 * build fails while the app is on a base that differs from the released one.
 *
 * Deliberately not skipped for local development. A path install is a
 * legitimate thing to want, but it is a decision to make knowingly and undo,
 * and this is the check that makes the difference visible.
 */
class PackageLockParityTest extends TestCase
{
    /**
     * @return array<string, array{version: string, reference: string|null, distType: string|null}>
     */
    private function lockedPackages(): array
    {
        $lock = $this->readJsonFile(base_path('composer.lock'));

        $locked = [];

        foreach (['packages', 'packages-dev'] as $group) {
            foreach ($lock[$group] ?? [] as $package) {
                if (! str_starts_with($package['name'], 'nreljed/')) {
                    continue;
                }

                $locked[$package['name']] = [
                    'version' => $package['version'],
                    'reference' => $package['dist']['reference'] ?? null,
                    'distType' => $package['dist']['type'] ?? null,
                ];
            }
        }

        return $locked;
    }

    /**
     * @return array<string, array{version: string, distType: string|null, reference: string|null}>
     */
    private function installedPackages(): array
    {
        $installed = $this->readJsonFile(base_path('vendor/composer/installed.json'));

        $found = [];

        foreach ($installed['packages'] ?? [] as $package) {
            if (! str_starts_with($package['name'], 'nreljed/')) {
                continue;
            }

            $found[$package['name']] = [
                'version' => $package['version'],
                'distType' => $package['dist']['type'] ?? null,
                'reference' => $package['dist']['reference'] ?? null,
            ];
        }

        return $found;
    }

    /** @return array<string, mixed> */
    private function readJsonFile(string $path): array
    {
        $this->assertFileExists($path, "{$path} is missing.");

        $decoded = json_decode((string) file_get_contents($path), true);

        $this->assertIsArray($decoded, "{$path} is not valid JSON.");

        return $decoded;
    }

    public function test_the_installed_package_versions_match_the_lock_file(): void
    {
        $locked = $this->lockedPackages();

        $this->assertNotEmpty($locked, 'No nreljed packages found in composer.lock.');

        $installed = $this->installedPackages();

        foreach ($locked as $name => $expected) {
            $this->assertArrayHasKey(
                $name,
                $installed,
                "{$name} is in the lock but not installed. Run composer install."
            );

            $this->assertSame(
                $expected['version'],
                $installed[$name]['version'],
                "{$name} is installed at {$installed[$name]['version']} but the lock says "
                .$expected['version'].'. Run composer install so local matches the lock.'
            );
        }
    }

    public function test_the_installed_packages_were_built_from_the_locked_commits(): void
    {
        $installed = $this->installedPackages();

        foreach ($this->lockedPackages() as $name => $expected) {
            if ($expected['reference'] === null || ! isset($installed[$name])) {
                continue;
            }

            $this->assertSame(
                $expected['reference'],
                $installed[$name]['reference'],
                "{$name} was installed from a different commit than the lock records."
            );
        }
    }

    public function test_the_lock_file_does_not_pin_a_local_working_tree(): void
    {
        $offenders = [];

        foreach ($this->lockedPackages() as $name => $expected) {
            if ($expected['distType'] === 'path') {
                $offenders[] = $name;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'composer.lock pins these packages to a local path instead of a released commit: '
            .implode(', ', $offenders)
            .'. A path repository rewrites the lock, and that lock cannot be installed anywhere else. '
            .'Remove the path repository from composer.json and re-run composer update.'
        );
    }

    public function test_no_package_is_installed_from_a_local_working_tree(): void
    {
        $offenders = [];

        foreach ($this->installedPackages() as $name => $package) {
            if (in_array($package['distType'], ['path', 'symlink'], true)) {
                $offenders[] = "{$name} ({$package['distType']})";
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Installed from a local working tree rather than the released artifact: '
            .implode(', ', $offenders)
            .'. Tests would run against code production does not have. Remove the path repository '
            .'from composer.json and run composer install to get back to the released base.'
        );
    }
}
