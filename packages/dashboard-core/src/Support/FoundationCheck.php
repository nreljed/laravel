<?php

namespace Nreljed\DashboardCore\Support;

/**
 * Verifies that an application tree still respects the dashboard-core
 * foundation boundary after a project clone sync.
 */
class FoundationCheck
{
    public function __construct(private readonly string $root) {}

    /**
     * @return array{clean: bool, issues: array<int, string>}
     */
    public function run(): array
    {
        $issues = [];

        $manifestPath = $this->root.'/vendor/nreljed/dashboard-core/core-manifest.json';

        if (! is_file($manifestPath)) {
            return ['clean' => false, 'issues' => ['manifest missing: '.$manifestPath.' (run composer install first)']];
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        if (! is_array($manifest)) {
            return ['clean' => false, 'issues' => ['manifest unreadable: '.$manifestPath]];
        }

        foreach ($manifest['app_owned_files'] ?? [] as $rel => $spec) {
            $path = $this->root.'/'.$rel;

            if (! is_file($path)) {
                $issues[] = "missing app-owned file: $rel";

                continue;
            }

            $marker = is_array($spec) ? ($spec['marker'] ?? null) : null;

            if (is_string($marker) && $marker !== '' && ! str_contains((string) file_get_contents($path), $marker)) {
                $issues[] = "app-owned file drifted from core: $rel";
            }
        }

        foreach ($manifest['forbidden_project_paths'] ?? [] as $rel) {
            if (file_exists($this->root.'/'.$rel)) {
                $issues[] = "forbidden project path exists: $rel (move into dashboard-core)";
            }
        }

        return ['clean' => $issues === [], 'issues' => $issues];
    }
}
