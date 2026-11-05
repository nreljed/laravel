<?php

namespace Nreljed\Platform\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Shape guard for the package manifests.
 *
 * Two classes of defect this catches, both of which have actually shipped:
 *
 *  1. Duplicate `repositories` entries. A named and an unnamed entry pointing
 *     at the same URL makes Composer resolve the same package twice (and made
 *     a consumer's manifest impossible to read). Nothing in Composer rejects
 *     it, so it has to be asserted here.
 *  2. `composer.json` / `package.json` version drift. The release workflow only
 *     compares them against the tag at publish time; catching it earlier keeps
 *     a bad tag from ever being cut.
 *
 * No framework boot: the package test suite runs without an application.
 */
final class ComposerManifestShapeTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $composer;

    /** @var array<string, mixed> */
    private array $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->composer = $this->readJson('composer.json');
        $this->package = $this->readJson('package.json');
    }

    public function test_composer_json_declares_the_package_identity(): void
    {
        $this->assertSame('nreljed/platform', $this->composer['name'] ?? null);
        $this->assertNotEmpty($this->composer['description'] ?? null);
        $this->assertNotEmpty($this->composer['version'] ?? null);
        $this->assertArrayHasKey('php', $this->composer['require'] ?? []);
        $this->assertArrayHasKey('laravel/framework', $this->composer['require'] ?? []);
    }

    public function test_repositories_are_unique_by_url_and_by_name(): void
    {
        $repositories = $this->composer['repositories'] ?? [];

        $this->assertIsArray($repositories);

        $byUrl = [];
        $byName = [];
        $violations = [];

        foreach ($repositories as $index => $repository) {
            if (! is_array($repository)) {
                continue;
            }

            $label = $repository['name'] ?? "#{$index}";

            if (isset($repository['url'])) {
                $url = $this->normalizeRepositoryUrl((string) $repository['url']);

                if (isset($byUrl[$url])) {
                    $violations[] = sprintf(
                        'repositories %s and %s both point at %s',
                        $byUrl[$url],
                        $label,
                        $url,
                    );
                }

                $byUrl[$url] = $label;
            }

            if (isset($repository['name'])) {
                $name = strtolower((string) $repository['name']);

                if (isset($byName[$name])) {
                    $violations[] = sprintf('repository name [%s] is declared twice', $name);
                }

                $byName[$name] = true;
            }
        }

        $this->assertSame(
            [],
            $violations,
            'composer.json declares duplicate repositories — Composer accepts this silently, so it has to be asserted:'
                .PHP_EOL.implode(PHP_EOL, $violations),
        );
    }

    public function test_repository_entries_declare_a_type_and_a_url(): void
    {
        foreach ($this->composer['repositories'] ?? [] as $index => $repository) {
            $this->assertIsArray($repository, "repositories[{$index}] must be an object.");
            $this->assertArrayHasKey('type', $repository, "repositories[{$index}] is missing \"type\".");
            $this->assertArrayHasKey('url', $repository, "repositories[{$index}] is missing \"url\".");
        }
    }

    public function test_composer_and_package_versions_agree(): void
    {
        $this->assertSame(
            $this->composer['version'] ?? null,
            $this->package['version'] ?? null,
            'composer.json and package.json versions must match — the release workflow compares both against the tag.',
        );
    }

    public function test_package_name_maps_to_the_composer_name(): void
    {
        $this->assertSame(
            '@'.str_replace('/', '/', (string) ($this->composer['name'] ?? '')),
            $this->package['name'] ?? null,
            'The npm package must be the scoped form of the Composer package name.',
        );
    }

    public function test_psr4_autoload_maps_the_namespace_to_src(): void
    {
        $psr4 = $this->composer['autoload']['psr-4'] ?? [];

        $this->assertArrayHasKey('Nreljed\\Platform\\', $psr4);
        $this->assertSame('src/', $psr4['Nreljed\\Platform\\']);

        $this->assertDirectoryExists(dirname(__DIR__).'/src');
    }

    public function test_package_exports_point_at_existing_files(): void
    {
        $exports = $this->package['exports'] ?? [];

        $this->assertIsArray($exports);
        $this->assertNotEmpty($exports, 'package.json must expose the frontend kit via "exports".');

        foreach ($exports as $subpath => $target) {
            // Wildcard exports are resolved at import time, not on disk.
            if (str_contains((string) $subpath, '*') || str_contains((string) $target, '*')) {
                continue;
            }

            $this->assertFileExists(
                dirname(__DIR__).'/'.ltrim((string) $target, './'),
                "package.json exports [{$subpath}] points at a missing file ({$target}).",
            );
        }
    }

    /**
     * Composer treats a URL as an identity; `.../repo` and `.../repo.git` and
     * `.../repo/` are the same repository.
     */
    private function normalizeRepositoryUrl(string $url): string
    {
        $url = strtolower(trim($url));
        $url = rtrim($url, '/');

        return str_ends_with($url, '.git') ? substr($url, 0, -4) : $url;
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $relative): array
    {
        $path = dirname(__DIR__).'/'.$relative;

        $this->assertFileExists($path);

        $decoded = json_decode((string) file_get_contents($path), true);

        $this->assertIsArray($decoded, "{$relative} is not a JSON object.");

        return $decoded;
    }
}
