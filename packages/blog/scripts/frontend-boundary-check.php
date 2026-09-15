<?php

/**
 * Frontend boundary check for the nreljed/blog package.
 *
 * Structural guard for the shipped TS/TSX kit (mirrors the PHP-level
 * NamespaceIntegrityTest): every relative ./ import must resolve to a real
 * file inside the package, module imports must stay within the package's own
 * Resources roots (or the '@dashboard'/'@' platform aliases provided by the
 * consuming app), and no host-app or environment leakage is allowed.
 *
 * Usage: php scripts/frontend-boundary-check.php
 * Exit code 0 = clean, 1 = violation found.
 */
$root = dirname(__DIR__);

$issues = [];
$files = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root.'/src')
);

foreach ($iterator as $file) {
    if ($file->isFile() && in_array($file->getExtension(), ['ts', 'tsx'], true)) {
        $files[] = $file->getPathname();
    }
}

sort($files);

foreach ($files as $file) {
    $content = (string) file_get_contents($file);
    $relative = str_replace('\\', '/', substr($file, strlen($root.'/')));

    if (! preg_match_all('/from\s+[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
        continue;
    }

    foreach ($matches[1] as $spec) {
        if (preg_match('#^@(dashboard|blog)(/|$)#', $spec)) {
            continue;
        }

        if (preg_match('#^(app/|storage/|public/|resources/js/|database/|tests/)#', $spec)) {
            $issues[] = "$relative imports a host-app path: $spec";
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

        if (! array_filter($candidates, fn (string $c) => realpath($c) !== false)) {
            $issues[] = "$relative imports a missing file: $spec";
        }
    }
}

foreach ($files as $file) {
    $content = (string) file_get_contents($file);
    $relative = str_replace('\\', '/', substr($file, strlen($root.'/')));

    if (preg_match('/from\s+[\'"](@dashboard-posts|@test-posts|@[a-zA-Z_-]+-module)[\'"]/', $content, $m)) {
        $issues[] = "$relative imports another module: {$m[1]}";
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
