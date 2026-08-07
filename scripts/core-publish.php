<?php

/**
 * Publish dashboard-core to the standalone GitHub package repo.
 *
 * The source of truth for development stays in packages/dashboard-core (this
 * repo). This tool mirrors that tree into a clone of
 * https://github.com/nreljed/dashboard-core, bumps the package version, and
 * pushes a new release tag. Consuming apps (Shop, Booking, ...) then pull it
 * with `composer update nreljed/dashboard-core`.
 *
 * Usage:
 *   php scripts/core-publish.php                bump patch, push vX.Y.Z
 *   php scripts/core-publish.php --version=1.2.3 push a specific version
 *   php scripts/core-publish.php --dry-run       show the version + diff only
 *
 * Requirements:
 *   - git on PATH, authenticated against github.com (e.g. `gh auth setup-git`)
 */
$dryRun = false;
$version = null;

for ($i = 1, $n = count($argv); $i < $n; $i++) {
    $arg = $argv[$i];

    if ($arg === '--dry-run') {
        $dryRun = true;
    } elseif ($arg === '--version') {
        $version = $argv[++$i] ?? null;
    } elseif (str_starts_with($arg, '--version=')) {
        $version = substr($arg, strlen('--version='));
    }
}

$projectRoot = dirname(__DIR__);
$source = $projectRoot.'/packages/dashboard-core';
$repoUrl = 'https://github.com/nreljed/dashboard-core.git';
$cloneDir = rtrim((string) (getenv('TEMP') ?: sys_get_temp_dir()), '/\\').'/core-publish-nreljed';

/** Files and directories never mirrored into the package repo. */
function ignoredPaths(): array
{
    return ['vendor', 'node_modules', '.git', '.core-sha', '.phpunit.result.cache'];
}

function runGit(array $args, ?string $cwd = null): string
{
    $cmd = array_merge(['git'], $args);
    $escaped = implode(' ', array_map('escapeshellarg', $cmd));
    $dir = $cwd !== null ? 'cd '.escapeshellarg($cwd).' && ' : '';

    $output = [];
    exec($dir.$escaped.' 2>&1', $output, $code);

    if ($code !== 0) {
        fwrite(STDERR, 'git failed: '.$escaped."\n".implode("\n", $output)."\n");
        exit(1);
    }

    return implode("\n", $output);
}

/**
 * @return array{added: array<int, string>, changed: array<int, string>, removed: array<int, string>}
 */
function diffTrees(string $source, string $target): array
{
    /** @return array<string, string> */
    $index = function (string $dir): array {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if (! $file->isFile()) {
                continue;
            }

            $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));
            $segments = explode('/', $rel);

            if (array_intersect($segments, ignoredPaths()) !== []) {
                continue;
            }

            $files[$rel] = hash_file('sha256', $file->getPathname());
        }

        return $files;
    };

    $sourceFiles = $index($source);
    $targetFiles = $index($target);

    $added = [];
    $changed = [];
    $removed = [];

    foreach ($sourceFiles as $rel => $hash) {
        if (! isset($targetFiles[$rel])) {
            $added[] = $rel;
        } elseif ($targetFiles[$rel] !== $hash) {
            $changed[] = $rel;
        }
    }

    foreach (array_keys($targetFiles) as $rel) {
        if (! isset($sourceFiles[$rel])) {
            $removed[] = $rel;
        }
    }

    return ['added' => $added, 'changed' => $changed, 'removed' => $removed];
}

function out(string $message = ''): void
{
    fwrite(STDOUT, $message.PHP_EOL);
}

if (! is_dir($source)) {
    fwrite(STDERR, "Core source not found: {$source}\n");
    exit(2);
}

if (! is_dir($cloneDir)) {
    out("Cloning {$repoUrl} into {$cloneDir} ...");
    runGit(['clone', '-c', 'core.autocrlf=false', $repoUrl, $cloneDir]);
} else {
    out("Updating existing clone at {$cloneDir} ...");
    runGit(['fetch', 'origin', '--tags', '--prune'], $cloneDir);
}

// Determine the next version.
if ($version === null) {
    $tagLines = array_filter(explode("\n", runGit(['ls-remote', '--tags', 'origin'], $cloneDir)));
    $semverTags = [];

    foreach ($tagLines as $line) {
        if (preg_match('/refs\/tags\/v(\d+)\.(\d+)\.(\d+)$/', trim($line), $m)) {
            $semverTags[] = [$m[1], $m[2], $m[3]];
        }
    }

    if ($semverTags !== []) {
        usort($semverTags, fn ($a, $b) => [$a[0], $a[1], $a[2]] <=> [$b[0], $b[1], $b[2]]);
        $last = end($semverTags);
        $version = $last[0].'.'.$last[1].'.'.((int) $last[2] + 1);
    } else {
        $version = '0.1.0';
    }
}

if (! preg_match('/^\d+\.\d+\.\d+$/', $version)) {
    fwrite(STDERR, "Invalid version: {$version} (expected X.Y.Z)\n");
    exit(2);
}

out("Publishing dashboard-core v{$version}");
out("  source: {$source}");

$diff = diffTrees($source, $cloneDir);

if (array_merge($diff['added'], $diff['changed'], $diff['removed']) === []) {
    out('  nothing to publish (trees are identical).');
}

foreach ($diff['removed'] as $rel) {
    out("  - {$rel}");
}

foreach (array_merge($diff['added'], $diff['changed']) as $rel) {
    out("  ~ {$rel}");
}

if ($dryRun) {
    out('DRY RUN: no changes made.');

    exit(0);
}

// Mirror files into the clone.
foreach ($diff['removed'] as $rel) {
    $path = $cloneDir.'/'.$rel;
    if (is_file($path) && unlink($path)) {
        out("removed  {$rel}");
    }
}

foreach (array_merge($diff['added'], $diff['changed']) as $rel) {
    $from = $source.'/'.$rel;
    $to = $cloneDir.'/'.$rel;

    if (! is_dir(dirname($to))) {
        mkdir(dirname($to), 0777, true);
    }

    if (! copy($from, $to)) {
        fwrite(STDERR, "failed to copy: {$rel}\n");
        exit(1);
    }

    out('synced   '.$rel);
}

// Keep the version field in both trees consistent with the tag.
$composerSource = $source.'/composer.json';
$composerClone = $cloneDir.'/composer.json';

foreach ([$composerSource, $composerClone] as $path) {
    if (is_file($path)) {
        $json = json_decode((string) file_get_contents($path), true);

        if (is_array($json) && ($json['version'] ?? null) !== $version) {
            $json['version'] = $version;
            file_put_contents($path, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
            out("version  {$path} -> {$version}");
        }
    }
}

runGit(['add', '-A'], $cloneDir);

if (trim(runGit(['status', '--porcelain'], $cloneDir)) === '') {
    out('Nothing to commit — trees already in sync.');

    exit(0);
}

runGit(['commit', '-q', '-m', "chore: core v{$version}"], $cloneDir);
runGit(['tag', "v{$version}"], $cloneDir);
runGit(['push', 'origin', 'main', '--tags'], $cloneDir);

out('');
out('Published nreljed/dashboard-core v'.$version);
out('  repo:  https://github.com/nreljed/dashboard-core');
out('');
out('Next steps:');
out('  1. Commit the composer.json version bump in this repo (master).');
out('  2. In each consuming app:');
out('       composer update nreljed/dashboard-core');
out('       npm run build');
out('       commit + push  (CI + deploy)');
out('');

exit(0);
