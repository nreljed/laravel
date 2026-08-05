<?php

namespace Nreljed\DashboardCore\Services;

class CoreVersionService
{
    public const PACKAGE_NAME = 'dashboard-core';

    /**
     * Resolve the installed dashboard-core package identity.
     *
     * Prefers the build-time recorded storage/app/core.json (name, version,
     * sha), then falls back to the legacy storage/app/core.sha marker plus the
     * version field of the installed package composer.json.
     *
     * @return array{name: string, version: string|null, sha: string|null}
     */
    public function info(): array
    {
        $file = $this->fromJsonFile();

        if ($file !== null) {
            return $file;
        }

        return [
            'name' => self::PACKAGE_NAME,
            'version' => $this->composerVersion(),
            'sha' => $this->shaFromMarker(),
        ];
    }

    public function shortSha(): ?string
    {
        $sha = $this->info()['sha'];

        return $sha !== null ? substr($sha, 0, 7) : null;
    }

    public function commitUrl(): ?string
    {
        $sha = $this->info()['sha'];

        return $sha !== null ? 'https://github.com/nreljed/laravel/commit/'.$sha : null;
    }

    /**
     * @return array{name: string, version: string|null, sha: string|null}|null
     */
    protected function fromJsonFile(): ?array
    {
        $path = storage_path('app/core.json');

        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data) || (! isset($data['sha']) && ! isset($data['version']))) {
            return null;
        }

        return [
            'name' => $this->clean($data['name'] ?? null) ?? self::PACKAGE_NAME,
            'version' => $this->clean($data['version'] ?? null),
            'sha' => $this->clean($data['sha'] ?? null),
        ];
    }

    protected function shaFromMarker(): ?string
    {
        $path = storage_path('app/core.sha');

        return is_file($path) ? $this->clean(file_get_contents($path)) : null;
    }

    protected function composerVersion(): ?string
    {
        $path = base_path('vendor/nreljed/dashboard-core/composer.json');

        if (! is_file($path)) {
            return null;
        }

        $meta = json_decode((string) file_get_contents($path), true);

        return is_array($meta) ? $this->clean($meta['version'] ?? null) : null;
    }

    protected function clean(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
