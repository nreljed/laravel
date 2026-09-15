<?php

use Nreljed\Platform\Support\FoundationCheck;

/**
 * Foundation boundary check for the platform package.
 *
 * Verifies the app tree still respects the core boundary after a project
 * clone sync: core-owned files match their markers, forbidden project paths
 * are absent, and overridable configs are present.
 *
 * Usage: php scripts/foundation-check.php [project-root]
 *   project-root: optional; defaults to this repo's root. Consuming apps
 *   (Shop, Booking) pass their own app directory to validate their tree.
 * Exit code 0 = clean, 1 = drift detected.
 */
$root = $argv[1] ?? dirname(__DIR__);

$classFile = $root.'/vendor/nreljed/platform/src/Support/FoundationCheck.php';

if (! is_file($classFile)) {
    fwrite(STDERR, "nreljed/platform is not installed; run composer install first.\n");

    exit(1);
}

require $classFile;

$check = new FoundationCheck($root);
$result = $check->run();

if ($result['clean']) {
    fwrite(STDOUT, "Foundation check: OK\n");

    exit(0);
}

fwrite(STDERR, "Foundation check FAILED:\n");
foreach ($result['issues'] as $issue) {
    fwrite(STDERR, "  - $issue\n");
}

exit(1);
