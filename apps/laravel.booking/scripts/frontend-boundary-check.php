<?php

/**
 * Frontend boundary check for the consuming application.
 *
 * Structural guard for the app's own TS/TSX sources (mirrors the pattern the
 * nreljed packages ship as scripts/frontend-boundary-check.php): every relative
 * ./ import must resolve to a real file inside resources/js and must not escape
 * that root, no import may reach package code through filesystem paths
 * (vendor/, node_modules/nreljed, ../../packages), and package code may only be
 * reached through the @nreljed/* aliases declared in vite.config.js and
 * tsconfig.json.
 *
 * Usage: php scripts/frontend-boundary-check.php [app-root]
 *   app-root: optional; defaults to this repo's root.
 * Exit code 0 = clean, 1 = violation found.
 */
$root = rtrim($argv[1] ?? dirname(__DIR__), '/\\');

$jsRoot = $root.'/resources/js';

if (! is_dir($jsRoot)) {
    fwrite(STDERR, "resources/js not found: $jsRoot\n");

    exit(1);
}

$issues = [];
$files = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($jsRoot)
);

foreach ($iterator as $file) {
    if ($file->isFile() && in_array($file->getExtension(), ['ts', 'tsx'], true)) {
        $files[] = $file->getPathname();
    }
}

sort($files);

$jsRootReal = realpath($jsRoot);

foreach ($files as $file) {
    $content = (string) file_get_contents($file);
    $relative = str_replace('\\', '/', substr($file, strlen($root.'/')));

    if (! preg_match_all('/from\s+[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
        continue;
    }

    foreach ($matches[1] as $spec) {
        if (preg_match('#^@nreljed/(platform|blog|shop-module|booking-module|dashboard-core)(/|$)#', $spec)) {
            continue;
        }

        if (preg_match('#(^|/)vendor/|node_modules/nreljed|\.\./packages#', $spec)) {
            $issues[] = "$relative imports package code through a filesystem path: $spec";

            continue;
        }

        if (preg_match('#^(app|storage|public|resources/|database|tests|vendor|config)(/|$)#', $spec)) {
            $issues[] = "$relative imports a host-app root path: $spec";

            continue;
        }

        if (! str_starts_with($spec, '.')) {
            continue;
        }

        $base = dirname($file).'/'.$spec;

        $candidates = [
            $base,
            $base.'.ts',
            $base.'.tsx',
            $base.'/index.ts',
            $base.'/index.tsx',
        ];

        $resolved = null;

        foreach ($candidates as $candidate) {
            $real = realpath($candidate);

            if ($real !== false) {
                $resolved = $real;

                break;
            }
        }

        if ($resolved === null) {
            $issues[] = "$relative imports an unresolvable file: $spec";
        } elseif (! str_starts_with($resolved.DIRECTORY_SEPARATOR, $jsRootReal.DIRECTORY_SEPARATOR)) {
            $issues[] = "$relative imports a file outside resources/js: $spec";
        }
    }
}

if ($issues === []) {
    fwrite(STDOUT, 'Frontend boundary check: OK ('.count($files)." files)\n");

    exit(0);
}

fwrite(STDERR, "Frontend boundary check FAILED:\n");
foreach ($issues as $issue) {
    fwrite(STDERR, "  - $issue\n");
}

exit(1);
