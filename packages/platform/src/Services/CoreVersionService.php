<?php

namespace Nreljed\Platform\Services;

class CoreVersionService
{
    public const PACKAGE_NAME = 'platform';

    /**
     * Composer full package name. Consuming apps install it via Composer
     * as a vcs package from this repo.
     */
    public const PACKAGE = 'nreljed/platform';

    /**
     * GitHub repo hosting the platform distribution source. Consuming
     * apps install it via Composer as a vcs package from this repo.
     */
    public const PACKAGE_REPO = 'nreljed/platform';

    /**
     * Resolve the installed platform package identity from the actual
     * install state — never from a build-time record.
     *
     * Version comes from vendor/composer/installed.json (the authoritative
     * Composer install state; falls back to the package's own composer.json
     * "version" field). The commit SHA comes from installed.json's
     * source/dist reference (falls back to composer.lock). The repo is
     * derived from the installed source URL, falling back to the package
     * constant. Build-time snapshots (storage/app/core.json, core.sha) are
     * deliberately ignored — they can drift from what is actually installed.
     *
     * @return array{name: string, version: string|null, sha: string|null, repo: string}
     */
    public function info(): array
    {
        $installed = $this->installedMeta();

        return [
            'name' => self::PACKAGE_NAME,
            'version' => $this->installedVersion() ?? $this->composerVersion(),
            'sha' => $installed['reference'] ?? $this->lockReference(),
            'repo' => $this->repoFromInstalledUrl($installed['url'] ?? null) ?? $this->defaultRepo(),
        ];
    }

    public function shortSha(): ?string
    {
        $sha = $this->info()['sha'];

        return $sha !== null ? substr($sha, 0, 7) : null;
    }

    /**
     * Enumerate every installed nreljed/* package — the deploy SBOM.
     *
     * Sourced from vendor/composer/installed.json (the authoritative Composer
     * install state, same source info() uses). Exposed through
     * DeployService::result() so every /deploy/check response lists the bundled
     * engine/feature versions actually installed on the host.
     *
     * @return array<int, array{name: string, version: string|null, sha: string|null, repo: string}>
     */
    public function bundled(): array
    {
        $path = base_path('vendor/composer/installed.json');

        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);

        return self::fromInstalledJson($data);
    }

    /**
     * Framework-free interpretation of installed.json data.
     *
     * Kept static + pure so the package's contract tests can assert the SBOM
     * mapping without booting the application.
     *
     * @param  mixed  $data  decoded installed.json
     * @return array<int, array{name: string, version: string|null, sha: string|null, repo: string}>
     */
    public static function fromInstalledJson(mixed $data): array
    {
        $packages = is_array($data) && is_array($data['packages'] ?? null) ? $data['packages'] : [];
        $bundled = [];

        foreach ($packages as $package) {
            if (! is_array($package)) {
                continue;
            }

            $name = $package['name'] ?? null;
            if (! is_string($name) || ! str_starts_with($name, 'nreljed/')) {
                continue;
            }

            $bundled[] = [
                'name' => $name,
                'version' => self::scalarToNull($package['version'] ?? null),
                'sha' => self::scalarToNull($package['source']['reference'] ?? $package['dist']['reference'] ?? null),
                'repo' => self::repoFromUrl($package['source']['url'] ?? $package['dist']['url'] ?? null) ?? $name,
            ];
        }

        usort($bundled, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $bundled;
    }

    private static function scalarToNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private static function repoFromUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        if (preg_match('#github\.com[/:]([^/]+/[^/.]+?)(?:\.git)?$#', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    public function commitUrl(): ?string
    {
        $info = $this->info();
        $sha = $info['sha'];

        return $sha !== null ? 'https://github.com/'.$info['repo'].'/commit/'.$sha : null;
    }

    /**
     * Authoritative installed version for the platform package, sourced from
     * vendor/composer/installed.json (falls back to vendor package composer.json).
     */
    protected function installedVersion(): ?string
    {
        return (new PackageVersionService)->installedVersion(self::PACKAGE);
    }

    /**
     * @return array{version: string|null, reference: string|null, url: string|null}
     */
    protected function installedMeta(): array
    {
        $path = base_path('vendor/composer/installed.json');

        if (! is_file($path)) {
            return ['version' => null, 'reference' => null, 'url' => null];
        }

        $data = json_decode((string) file_get_contents($path), true);
        $packages = is_array($data) ? ($data['packages'] ?? []) : [];

        foreach ($packages as $package) {
            if (! is_array($package) || ($package['name'] ?? null) !== self::PACKAGE) {
                continue;
            }

            return [
                'version' => $this->trimToNull($package['version'] ?? null),
                'reference' => $this->trimToNull(
                    $package['source']['reference'] ?? $package['dist']['reference'] ?? null
                ),
                'url' => $this->trimToNull($package['source']['url'] ?? $package['dist']['url'] ?? null),
            ];
        }

        return ['version' => null, 'reference' => null, 'url' => null];
    }

    protected function lockReference(): ?string
    {
        $path = base_path('composer.lock');

        if (! is_file($path)) {
            return null;
        }

        $lock = json_decode((string) file_get_contents($path), true);
        $packages = array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []);

        foreach ($packages as $package) {
            if (! is_array($package) || ($package['name'] ?? null) !== self::PACKAGE) {
                continue;
            }

            return $this->trimToNull(
                $package['source']['reference'] ?? $package['dist']['reference'] ?? null
            );
        }

        return null;
    }

    protected function repoFromInstalledUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        if (preg_match('#github\.com[/:]([^/]+/[^/.]+?)(?:\.git)?$#', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    protected function composerVersion(): ?string
    {
        $path = base_path('vendor/nreljed/platform/composer.json');

        if (! is_file($path)) {
            return null;
        }

        $meta = json_decode((string) file_get_contents($path), true);

        return is_array($meta) ? $this->trimToNull($meta['version'] ?? null) : null;
    }

    protected function defaultRepo(): string
    {
        return config('core.github_repo', self::PACKAGE_REPO);
    }

    protected function trimToNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
