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
 *   php scripts/core-publish.php                bump patch, push vX.Y.Z + release
 *   php scripts/core-publish.php --version=1.2.3 push a specific version
 *   php scripts/core-publish.php --dry-run       show the version + diff only
 *   php scripts/core-publish.php --no-release    skip creating the GitHub release
 *
 * Requirements:
 *   - git on PATH, authenticated against github.com (e.g. `gh auth setup-git`)
 *   - gh CLI on PATH to auto-create the GitHub release (optional)
 */
$dryRun = false;
$version = null;
$skipRelease = false;

for ($i = 1, $n = count($argv); $i < $n; $i++) {
    $arg = $argv[$i];

    if ($arg === '--dry-run') {
        $dryRun = true;
    } elseif ($arg === '--no-release') {
        $skipRelease = true;
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

function deleteDirectory(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        $path = $item->getPathname();

        if ($item->isDir()) {
            rmdir($path);

            continue;
        }

        // Git marks loose/packed objects read-only — clear the flag so
        // unlink() works on Windows.
        chmod($path, 0666);
        unlink($path);
    }

    rmdir($dir);
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
 * Build markdown release notes from the file diff between two tags.
 */
function buildReleaseNotes(string $cloneDir, ?string $previousTag, string $version): string
{
    $notes = "## dashboard-core v{$version}\n";

    if ($previousTag === null) {
        return $notes."\nInitial release of dashboard-core.\n";
    }

    $changed = [];

    foreach (explode("\n", runGit(['diff', '--stat', $previousTag, 'v'.$version], $cloneDir)) as $line) {
        if (str_contains($line, '|')) {
            $name = trim(explode('|', $line, 2)[0]);

            if ($name !== '') {
                $changed[] = $name;
            }
        }
    }

    if ($changed !== []) {
        $notes .= "\n### Changed files\n\n";

        foreach ($changed as $name) {
            $notes .= "- `{$name}`\n";
        }
    }

    $notes .= "\n**Full diff:** https://github.com/nreljed/dashboard-core/compare/{$previousTag}...v{$version}\n";

    return $notes;
}

/**
 * Create a GitHub release for the given tag using the gh CLI. Returns true on
 * success (or when the release already exists), false when gh is unavailable
 * or the release could not be created.
 */
function createRelease(string $cloneDir, string $tag, ?string $previousTag): bool
{
    $notes = buildReleaseNotes($cloneDir, $previousTag, ltrim($tag, 'v'));
    $notesFile = tempnam(sys_get_temp_dir(), 'core-release-');

    if ($notesFile === false || file_put_contents($notesFile, $notes) === false) {
        return false;
    }

    $cmd = 'gh release create '.escapeshellarg($tag)
        .' --repo nreljed/dashboard-core'
        .' --title '.escapeshellarg('dashboard-core '.$tag)
        .' --notes-file '.escapeshellarg($notesFile);

    $output = [];
    exec($cmd.' 2>&1', $output, $code);

    unlink($notesFile);

    if ($code !== 0) {
        $message = implode("\n", $output);

        if (str_contains($message, 'already exists')) {
            return true;
        }

        fwrite(STDERR, 'gh release create failed: '.$cmd."\n".$message."\n");

        return false;
    }

    return true;
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

// A leftover directory from an interrupted run may exist without being a
// usable clone (e.g. .git present but no HEAD) — re-clone in that case
// instead of failing on `git fetch`.
if (is_dir($cloneDir) && ! is_file($cloneDir.'/.git/HEAD')) {
    out("Removing invalid clone at {$cloneDir} ...");
    deleteDirectory($cloneDir);
}

if (! is_dir($cloneDir)) {
    out("Cloning {$repoUrl} into {$cloneDir} ...");
    runGit(['clone', '-c', 'core.autocrlf=false', $repoUrl, $cloneDir]);
} else {
    out("Updating existing clone at {$cloneDir} ...");
    runGit(['fetch', 'origin', '--tags', '--prune'], $cloneDir);
}

// CI runners have no global git identity, which made `git commit` fail with
// "Author identity unknown" and killed every automated publish since the
// workflow went live. Set a bot identity on the clone itself so publishing
// works from any machine without touching the host's global git config.
runGit(['config', 'user.name', 'github-actions[bot]'], $cloneDir);
runGit(['config', 'user.email', '41898282+github-actions[bot]@users.noreply.github.com'], $cloneDir);

// Decide up front whether anything functionally changed, so running
// core:publish with no new code does not mint a pointless version tag.
// composer.json is excluded from the "functional" diff because the local
// copy is reformatted by the prettier pre-commit hook (2-space) while the
// distribution copy is written by json_encode (4-space) — a cosmetic-only
// difference that must not trigger a release.
$diff = diffTrees($source, $cloneDir);

$functionalDiff = [
    'added' => array_values(array_filter($diff['added'], fn ($rel) => $rel !== 'composer.json')),
    'changed' => array_values(array_filter($diff['changed'], fn ($rel) => $rel !== 'composer.json')),
    'removed' => array_values(array_filter($diff['removed'], fn ($rel) => $rel !== 'composer.json')),
];

if (array_merge($functionalDiff['added'], $functionalDiff['changed'], $functionalDiff['removed']) === []) {
    $onlyComposerFile = array_merge($diff['added'], $diff['changed'], $diff['removed']) !== [];

    out($onlyComposerFile
        ? 'Nothing to publish - only composer.json differs (formatting/version). Skipping version bump.'
        : 'Nothing to publish - source and distribution trees are identical. Skipping version bump.');

    exit(0);
}

// Resolve the latest existing tag (used for the next version and release notes).
$previousTag = null;
$semverTags = [];

foreach (array_filter(explode("\n", runGit(['ls-remote', '--tags', 'origin'], $cloneDir))) as $line) {
    if (preg_match('/refs\/tags\/v(\d+)\.(\d+)\.(\d+)$/', trim($line), $m)) {
        $semverTags[] = [$m[1], $m[2], $m[3]];
    }
}

if ($semverTags !== []) {
    usort($semverTags, fn ($a, $b) => [$a[0], $a[1], $a[2]] <=> [$b[0], $b[1], $b[2]]);
    $previousTag = 'v'.implode('.', end($semverTags));
}

// Determine the next version (patch bump unless explicitly overridden).
if ($version === null) {
    if ($previousTag === null) {
        $version = '0.1.0';
    } else {
        [$major, $minor, $patch] = explode('.', substr($previousTag, 1));
        $version = $major.'.'.$minor.'.'.((int) $patch + 1);
    }
}

if (! preg_match('/^\d+\.\d+\.\d+$/', $version)) {
    fwrite(STDERR, "Invalid version: {$version} (expected X.Y.Z)\n");
    exit(2);
}

out("Publishing dashboard-core v{$version}");
out("  source: {$source}");

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

$releaseTag = 'v'.$version;
$releaseCreated = false;

if (! $skipRelease) {
    $releaseCreated = createRelease($cloneDir, $releaseTag, $previousTag);
}

out('');
out('Published nreljed/dashboard-core v'.$version);
out('  repo:  https://github.com/nreljed/dashboard-core');

if ($releaseCreated) {
    out('  release: https://github.com/nreljed/dashboard-core/releases/tag/'.$releaseTag);
} elseif (! $skipRelease) {
    out('');
    out('Release not created automatically (gh unavailable or unauthenticated). Create it manually:');
    out('  gh release create '.$releaseTag.' --repo nreljed/dashboard-core --title "dashboard-core v'.$version.'"');
}

out('');
out('Next steps:');
out('  1. Commit the composer.json version bump in this repo (main).');
out('  2. In each consuming app:');
out('       composer update nreljed/dashboard-core');
out('       npm run build');
out('       commit + push  (CI + deploy)');
out('');

exit(0);
