<?php

namespace Nreljed\Platform\Services;

/**
 * Core-owned resolver for the *installed* version of a Composer package.
 *
 * Feature providers expose a declared version() that can drift or be
 * hardcoded (e.g. a provider shipping "1.0.0" in code while Composer
 * installed "0.1.4"). The system-health page therefore needs the ground
 * truth of what Composer actually installed.
 *
 * This reads vendor/composer/installed.json (the authoritative install
 * state) first and falls back to the package's own composer.json "version"
 * field, then returns null when the package is not installed.
 */
class PackageVersionService
{
    /**
     * Resolve the installed version for a fully-qualified Composer package
     * name (e.g. "nreljed/shop-module").
     */
    public function installedVersion(string $package): ?string
    {
        return $this->fromInstalledJson($package) ?? $this->fromComposerJson($package);
    }

    protected function fromInstalledJson(string $package): ?string
    {
        $path = base_path('vendor/composer/installed.json');

        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);
        $packages = is_array($data) ? ($data['packages'] ?? []) : [];

        foreach ($packages as $installed) {
            if (! is_array($installed) || ($installed['name'] ?? null) !== $package) {
                continue;
            }

            $version = trim((string) ($installed['version'] ?? ''));

            return $version === '' ? null : $version;
        }

        return null;
    }

    protected function fromComposerJson(string $package): ?string
    {
        $path = base_path('vendor/'.$package.'/composer.json');

        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);
        $version = is_array($data) ? trim((string) ($data['version'] ?? '')) : '';

        return $version === '' ? null : $version;
    }
}
