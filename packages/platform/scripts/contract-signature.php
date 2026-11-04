<?php

declare(strict_types=1);

/**
 * Contract signature gate for src/Contracts.
 *
 * The release pipeline must refuse a MINOR/PATCH release that changes what a
 * feature provider is *required* to implement — that is a breaking change by
 * definition. The old gate compared the raw bytes of FeatureProvider.php, which
 * meant a docblock fix demanded a MAJOR bump while a method addition to a new
 * interface slipped through.
 *
 * This script compares the *effective required method set* instead: every
 * interface's own methods plus the methods it inherits through `extends`,
 * normalised to `method(param types): return type`. Adding a method to an
 * existing interface is breaking; adding a whole new interface is additive and
 * allowed.
 *
 * Usage:
 *   php scripts/contract-signature.php <ref>              print the signature
 *   php scripts/contract-signature.php --diff <from> <to> exit 1 if breaking
 *
 * A <ref> is any git revision (tag, branch, HEAD) or WORKTREE to read the files
 * as they exist on disk.
 */
const CONTRACT_DIR = 'src/Contracts';

exit(main($argv));

/**
 * @param  list<string>  $argv
 */
function main(array $argv): int
{
    $args = array_slice($argv, 1);

    if ($args === [] || in_array($args[0], ['-h', '--help'], true)) {
        fwrite(STDERR, "Usage: php scripts/contract-signature.php <ref>\n");
        fwrite(STDERR, "       php scripts/contract-signature.php --diff <from> <to>\n");

        return $args === [] ? 1 : 0;
    }

    if ($args[0] === '--diff') {
        if (count($args) !== 3) {
            fwrite(STDERR, "--diff requires exactly two refs.\n");

            return 1;
        }

        return diff($args[1], $args[2]);
    }

    foreach (signature($args[0]) as $interface => $methods) {
        foreach ($methods as $method) {
            echo $interface.'::'.$method."\n";
        }
    }

    return 0;
}

/**
 * Compare the required-method set of every interface that exists in $from.
 *
 * @return int 0 when compatible, 1 when breaking
 */
function diff(string $from, string $to): int
{
    $old = signature($from);
    $new = signature($to);

    $breaking = [];
    $additive = array_values(array_diff(array_keys($new), array_keys($old)));

    foreach ($old as $interface => $methods) {
        if (! isset($new[$interface])) {
            $breaking[] = "{$interface} was removed";

            continue;
        }

        $added = array_values(array_diff($new[$interface], $methods));
        $removed = array_values(array_diff($methods, $new[$interface]));

        foreach ($added as $method) {
            $breaking[] = "{$interface}::{$method} is newly required";
        }

        foreach ($removed as $method) {
            $breaking[] = "{$interface}::{$method} is no longer declared";
        }
    }

    if ($additive !== []) {
        echo 'Additive interfaces (no bump required): '.implode(', ', $additive).PHP_EOL;
    }

    if ($breaking === []) {
        echo "Contract compatible: {$from} -> {$to}".PHP_EOL;

        return 0;
    }

    fwrite(STDERR, "BREAKING contract change ({$from} -> {$to}):".PHP_EOL);

    foreach ($breaking as $line) {
        fwrite(STDERR, '  - '.$line.PHP_EOL);
    }

    fwrite(STDERR, 'Bump MAJOR to ship this.'.PHP_EOL);

    return 1;
}

/**
 * The required-method set of every interface in the contract directory.
 *
 * @return array<string, list<string>> interface short name => sorted method signatures
 */
function signature(string $ref): array
{
    $files = contractFiles($ref);
    $sources = [];
    $declarations = [];

    foreach ($files as $file) {
        $source = read($ref, $file);

        if ($source === null) {
            continue;
        }

        $sources[$file] = $source;
        $declaration = parse($source);

        if ($declaration !== null) {
            $declarations[$declaration['name']] = $declaration + ['file' => $file];
        }
    }

    $resolved = [];

    foreach ($declarations as $name => $declaration) {
        $methods = resolve($name, $declarations, $sources, []);
        sort($methods);
        $resolved[$name] = $methods;
    }

    ksort($resolved);

    return $resolved;
}

/**
 * Flatten an interface's own method signatures plus everything it inherits.
 *
 * @param  array<string, array{name: string, extends: list<string>, methods: list<string>, file: string}>  $declarations
 * @param  array<string, string>  $sources
 * @param  list<string>  $seen
 * @return list<string>
 */
function resolve(string $name, array $declarations, array $sources, array $seen): array
{
    if (in_array($name, $seen, true)) {
        return [];
    }

    $seen[] = $name;

    if (! isset($declarations[$name])) {
        // Extended from outside the contract directory (e.g. a framework
        // interface). Its methods are not ours to gate, so treat it as opaque.
        return [];
    }

    $methods = $declarations[$name]['methods'];

    foreach ($declarations[$name]['extends'] as $parent) {
        $parentName = shortName($parent);

        // Resolve the parent from the same ref's sources when the declaration
        // already covered it; otherwise look the file up by short name under a
        // guessed path so a newly added capability tree is still followed.
        if (! isset($declarations[$parentName])) {
            $candidate = CONTRACT_DIR.'/Capabilities/'.$parentName.'.php';

            if (isset($sources[$candidate])) {
                $parsed = parse($sources[$candidate]);

                if ($parsed !== null) {
                    $declarations[$parentName] = $parsed + ['file' => $candidate];
                }
            }
        }

        $methods = [...$methods, ...resolve($parentName, $declarations, $sources, $seen)];
    }

    return $methods;
}

/**
 * @return list<string>
 */
function contractFiles(string $ref): array
{
    $lines = [];

    if ($ref === 'WORKTREE') {
        $root = __DIR__.'/../'.CONTRACT_DIR;

        if (! is_dir($root)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $lines[] = CONTRACT_DIR.str_replace('\\', '/', substr($file->getPathname(), strlen($root)));
            }
        }

        sort($lines);

        return array_values($lines);
    }

    $output = [];
    $status = 0;

    exec('git ls-tree -r --name-only '.escapeshellarg($ref).' -- '.escapeshellarg(CONTRACT_DIR).' 2>&1', $output, $status);

    if ($status !== 0) {
        fwrite(STDERR, "Unable to read ref [{$ref}].".PHP_EOL);

        return [];
    }

    return array_values(array_filter(
        $output,
        fn (string $path) => str_ends_with($path, '.php'),
    ));
}

function read(string $ref, string $file): ?string
{
    if ($ref === 'WORKTREE') {
        $path = __DIR__.'/../'.$file;

        return is_file($path) ? (string) file_get_contents($path) : null;
    }

    $output = [];
    $status = 0;

    $nullDevice = PHP_OS_FAMILY === 'Windows' ? '2>NUL' : '2>/dev/null';

    exec('git show '.escapeshellarg($ref.':'.$file).' '.$nullDevice, $output, $status);

    return $status === 0 ? implode(PHP_EOL, $output) : null;
}

/**
 * @return array{name: string, extends: list<string>, methods: list<string>}|null
 */
function parse(string $source): ?array
{
    $source = stripComments($source);

    if (! preg_match('/interface\s+(\w+)(?:\s+extends\s+([^{]+))?/s', $source, $match)) {
        return null;
    }

    $extends = [];

    if (isset($match[2]) && trim($match[2]) !== '') {
        $extends = array_values(array_filter(array_map(
            'trim',
            explode(',', $match[2]),
        )));
    }

    preg_match_all(
        '/public\s+function\s+(\w+)\s*\(([^)]*)\)\s*(?::\s*([^\s;{]+))?\s*;/s',
        $source,
        $methods,
        PREG_SET_ORDER,
    );

    $signatures = [];

    foreach ($methods as $method) {
        $signatures[] = $method[1].'('.normalizeParameters($method[2]).')'.(
            isset($method[3]) && trim($method[3]) !== '' ? ': '.trim($method[3]) : ''
        );
    }

    return [
        'name' => $match[1],
        'extends' => $extends,
        'methods' => $signatures,
    ];
}

/**
 * Keep parameter names and types, drop default values and whitespace noise so a
 * docblock or formatting change never trips the gate.
 */
function normalizeParameters(string $parameters): string
{
    $parts = [];

    foreach (explode(',', $parameters) as $parameter) {
        $parameter = trim(preg_replace('/\s+/', ' ', $parameter) ?? '');

        if ($parameter === '') {
            continue;
        }

        $parameter = preg_replace('/\s*=\s*.*$/', '', $parameter) ?? $parameter;

        $parts[] = trim($parameter);
    }

    return implode(', ', $parts);
}

function shortName(string $name): string
{
    $position = strrpos($name, '\\');

    return $position === false ? $name : substr($name, $position + 1);
}

function stripComments(string $source): string
{
    $source = preg_replace('#/\*.*?\*/#s', '', $source) ?? $source;

    return preg_replace('#//[^\n]*#', '', $source) ?? $source;
}
