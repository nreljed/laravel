<?php

declare(strict_types=1);

namespace Nreljed\Platform\Support;

/**
 * Wires a scaffolded module into a consuming application.
 *
 * The five edits below are the irreducible activation steps for a module. They
 * are text-patches rather than decode/re-encode passes on purpose: rewriting a
 * composer.json or package.json through `json_encode` would reformat the whole
 * file and bury a two-line change in a hundred-line diff. Every method is
 * idempotent - running activation twice reports "already" and changes nothing.
 *
 * Nothing here throws on a missing anchor: a consumer that has not published a
 * config or uses a different page-config shape gets a "skipped" status plus the
 * manual instruction, never a half-activated app.
 */
final class ModuleActivator
{
    /**
     * @return array<string, string> step => human-readable status
     */
    public function activate(ModuleDefinition $module, string $appRoot): array
    {
        $appRoot = rtrim(str_replace('\\', '/', $appRoot), '/');

        return [
            'composer' => $this->activateComposer($module, $appRoot),
            'npm' => $this->activateNpm($module, $appRoot),
            'providers' => $this->activateProviders($module, $appRoot),
            'features' => $this->activateFeatureConfig($module, $appRoot),
            'pages' => $this->activatePageConfig($module, $appRoot),
        ];
    }

    /**
     * Register a Composer path repository and require the package.
     */
    public function activateComposer(ModuleDefinition $module, string $appRoot): string
    {
        $path = $appRoot.'/composer.json';

        if (! is_file($path)) {
            return 'skipped: composer.json not found';
        }

        $contents = (string) file_get_contents($path);
        $package = $module->package();

        if (str_contains($contents, '"'.$package.'"')) {
            return 'already: '.$package.' is required';
        }

        $relative = $module->relativeRoot($appRoot);

        $withRepository = $this->insertJsonMember(
            $contents,
            'repositories',
            static fn (string $indent, string $unit): string => "{\n"
                .$indent.$unit.'"name": "'.$module->slug.'-module",'."\n"
                .$indent.$unit.'"type": "path",'."\n"
                .$indent.$unit.'"url": "'.$relative.'",'."\n"
                .$indent.$unit.'"options": {'."\n"
                .$indent.$unit.$unit.'"symlink": true'."\n"
                .$indent.$unit.'}'."\n"
                .$indent.'}',
        );

        $withRequire = $this->insertJsonMember(
            $withRepository ?? $contents,
            'require',
            static fn (string $indent): string => '"'.$module->package().'": "@dev"',
        );

        if ($withRepository === null) {
            return 'skipped: composer.json has no "repositories" array - add a path repository for '
                .'"'.$relative.'" and require "'.$package.'": "@dev" manually';
        }

        if ($withRequire === null) {
            return 'skipped: composer.json has no "require" object - add '
                .'"'.$package.'": "@dev" manually';
        }

        return file_put_contents($path, $withRequire) === false
            ? 'skipped: composer.json is not writable'
            : 'patched: path repository + require "'.$package.'": "@dev"';
    }

    /**
     * Add the module's page kit as a linked local dependency.
     */
    public function activateNpm(ModuleDefinition $module, string $appRoot): string
    {
        $path = $appRoot.'/package.json';

        if (! is_file($path)) {
            return 'skipped: package.json not found';
        }

        $contents = (string) file_get_contents($path);
        $package = $module->npmPackage();

        if (str_contains($contents, '"'.$package.'"')) {
            return 'already: '.$package.' is a dependency';
        }

        $relative = $module->relativeRoot($appRoot);

        $contents = $this->insertJsonMember(
            $contents,
            'dependencies',
            static fn (string $indent): string => '"'.$module->npmPackage().'": "link:'.$relative.'"',
        );

        if ($contents === null) {
            return 'skipped: package.json has no "dependencies" object - add '
                .'"'.$package.'": "link:'.$relative.'" manually';
        }

        return file_put_contents($path, $contents) === false
            ? 'skipped: package.json is not writable'
            : 'patched: "'.$package.'": "link:'.$relative.'"';
    }

    /**
     * Register the provider in bootstrap/providers.php.
     */
    public function activateProviders(ModuleDefinition $module, string $appRoot): string
    {
        $path = $appRoot.'/bootstrap/providers.php';

        if (! is_file($path)) {
            return 'skipped: bootstrap/providers.php not found';
        }

        $contents = (string) file_get_contents($path);
        $fqcn = $module->providerFqcn();

        if (str_contains($contents, $fqcn.'::class') || str_contains($contents, $module->providerClass().'::class')) {
            return 'already: '.$fqcn.' is registered';
        }

        $eol = str_contains($contents, "\r\n") ? "\r\n" : "\n";
        $lines = explode($eol, $contents);

        // 1. The import: after the last existing `use` statement.
        $lastUse = null;

        foreach ($lines as $index => $line) {
            if (preg_match('/^use\s+[^;]+;\s*$/', $line) === 1) {
                $lastUse = $index;
            }
        }

        if ($lastUse === null) {
            return 'skipped: could not locate the use statements in bootstrap/providers.php';
        }

        array_splice($lines, $lastUse + 1, 0, ['use '.$fqcn.';']);

        // 2. The entry: before the closing `]);` of the returned array.
        $close = null;

        for ($index = count($lines) - 1; $index >= 0; $index--) {
            if (preg_match('/^\s*\]\)\s*;\s*$/', $lines[$index]) === 1) {
                $close = $index;

                break;
            }
        }

        if ($close === null) {
            return 'skipped: could not locate the returned provider array in bootstrap/providers.php';
        }

        $indent = $this->indentOf($this->previousCodeLine($lines, $close) ?? '    ');

        array_splice($lines, $close, 0, [$indent.$module->providerClass().'::class,']);

        return file_put_contents($path, implode($eol, $lines)) === false
            ? 'skipped: bootstrap/providers.php is not writable'
            : 'patched: '.$module->providerClass().'::class';
    }

    /**
     * Register the provider and its enabled default in config/features.php.
     */
    public function activateFeatureConfig(ModuleDefinition $module, string $appRoot): string
    {
        $path = $appRoot.'/config/features.php';

        if (! is_file($path)) {
            return 'skipped: config/features.php is not published - run '
                .'"php artisan vendor:publish --tag=core-config", then add '
                .$module->providerFqcn().' and \''.$module->slug.'\' => true';
        }

        $contents = (string) file_get_contents($path);
        $fqcn = $module->providerFqcn();
        $short = $module->providerClass();
        $patched = [];

        // The providers array references the class by short name, matching the
        // app's own style, so the import has to land first or PHP fatals on an
        // unresolvable constant.
        if (! str_contains($contents, $short)) {
            $withUse = $this->insertPhpUseStatement($contents, $fqcn);
            $reference = $short.'::class,';

            if ($withUse === null) {
                // A config file with no imports: reference the class by its
                // fully-qualified name instead of skipping activation.
                $reference = '\\'.$fqcn.'::class,';
            } else {
                $contents = $withUse;
            }

            $next = $this->insertPhpArrayEntry($contents, 'providers', $reference);

            if ($next === null) {
                return 'skipped: could not find the "providers" array in config/features.php';
            }

            $contents = $next;
            $patched[] = 'providers';
        }

        if (! str_contains($contents, "'".$module->slug."'")) {
            $next = $this->insertPhpArrayEntry($contents, 'enabled', "'".$module->slug."' => true,");

            if ($next !== null) {
                $contents = $next;
                $patched[] = 'enabled';
            }
        }

        if ($patched === []) {
            return 'already: provider and enabled default are registered';
        }

        return file_put_contents($path, $contents) === false
            ? 'skipped: config/features.php is not writable'
            : 'patched: '.implode(' + ', $patched);
    }

    /**
     * Add a `use` statement after the last existing one, preserving the file's
     * line endings. Returns null when the file has no use statements at all.
     */
    private function insertPhpUseStatement(string $contents, string $fqcn): ?string
    {
        $eol = str_contains($contents, "\r\n") ? "\r\n" : "\n";
        $lines = explode($eol, $contents);
        $lastUse = null;

        foreach ($lines as $index => $line) {
            if (preg_match('/^use\s+[^;]+;\s*$/', $line) === 1) {
                $lastUse = $index;
            }
        }

        if ($lastUse === null) {
            return null;
        }

        array_splice($lines, $lastUse + 1, 0, ['use '.$fqcn.';']);

        return implode($eol, $lines);
    }

    /**
     * Spread the module's page kit into resources/js/page-config.ts.
     */
    public function activatePageConfig(ModuleDefinition $module, string $appRoot): string
    {
        $path = $appRoot.'/resources/js/page-config.ts';

        if (! is_file($path)) {
            return 'skipped: resources/js/page-config.ts not found';
        }

        $contents = (string) file_get_contents($path);
        $function = $module->pagesFunction();

        if (str_contains($contents, $function)) {
            return 'already: '.$function.'() is in the page config';
        }

        $eol = str_contains($contents, "\r\n") ? "\r\n" : "\n";
        $lines = explode($eol, $contents);

        $import = 'import { '.$function.' } from "'.$module->npmPackage().'/pages";';
        $lastImport = null;

        foreach ($lines as $index => $line) {
            if (preg_match('/^import\s+.*\s+from\s+["\']@nreljed\/.*["\'];\s*$/', $line) === 1) {
                $lastImport = $index;
            }
        }

        if ($lastImport === null) {
            return 'skipped: could not locate the @nreljed imports in page-config.ts';
        }

        array_splice($lines, $lastImport + 1, 0, [$import]);

        $opened = null;

        foreach ($lines as $index => $line) {
            if (preg_match('/basePages\s*:\s*\{\s*$/', $line) === 1) {
                $opened = $index;

                break;
            }
        }

        if ($opened === null) {
            return 'skipped: page-config.ts has no multi-line "basePages: {" object - '
                .'add ...'.$function.'() manually';
        }

        $indent = $this->indentOf($this->nextCodeLine($lines, $opened) ?? '    ');
        $indent = $indent === '' ? $this->indentOf($lines[$opened]).'  ' : $indent;

        array_splice($lines, $opened + 1, 0, [$indent.'...'.$function.'(),']);

        return file_put_contents($path, implode($eol, $lines)) === false
            ? 'skipped: page-config.ts is not writable'
            : 'patched: import + ...'.$function.'()';
    }

    /**
     * Insert a member at the end of a JSON object or array, preserving the
     * file's existing indentation and line endings.
     *
     * @param  callable(string, string): string  $render  receives the entry indent and the indent unit
     */
    private function insertJsonMember(string $contents, string $key, callable $render): ?string
    {
        if (preg_match('/"'.preg_quote($key, '/').'"\s*:\s*([\[{])/', $contents, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $open = $match[1][0];
        $openPos = $match[1][1];
        $closePos = $this->matchingBracket($contents, $openPos);

        if ($closePos === null) {
            return null;
        }

        $unit = $this->indentUnit($contents);
        $openIndent = $this->indentOf($this->lineAt($contents, $openPos));
        $body = substr($contents, $openPos + 1, $closePos - $openPos - 1);
        $trimmed = rtrim($body);

        if ($trimmed === '') {
            $entryIndent = $openIndent.$unit;
            $newBody = "\n".$entryIndent.$render($entryIndent, $unit)."\n".$openIndent;
        } else {
            $entryIndent = $this->indentOf($this->lastCodeLine($trimmed) ?? $openIndent.$unit);
            $entryIndent = $entryIndent === '' ? $openIndent.$unit : $entryIndent;
            $newBody = $trimmed.','."\n".$entryIndent.$render($entryIndent, $unit).substr($body, strlen($trimmed));
        }

        return substr($contents, 0, $openPos + 1).$newBody.substr($contents, $closePos);
    }

    /**
     * Append an entry to a `'key' => [ ... ]` array in a PHP config file.
     */
    private function insertPhpArrayEntry(string $contents, string $key, string $entry): ?string
    {
        $eol = str_contains($contents, "\r\n") ? "\r\n" : "\n";
        $lines = explode($eol, $contents);

        $start = null;

        foreach ($lines as $index => $line) {
            if (preg_match("/'".preg_quote($key, '/')."'\s*=>\s*\[/", $line) === 1) {
                $start = $index;

                break;
            }
        }

        if ($start === null) {
            return null;
        }

        $indent = $this->indentOf($lines[$start]);

        // An empty array lives on one line: expand it before inserting.
        if (preg_match("/'".preg_quote($key, '/')."'\s*=>\s*\[\s*\]\s*,?/", $lines[$start]) === 1) {
            $lines[$start] = $indent."'".$key."' => [".$eol.$indent.'    '.$entry.$eol.$indent.'],';

            return implode($eol, $lines);
        }

        $depth = 0;
        $end = null;

        for ($index = $start; $index < count($lines); $index++) {
            $depth += substr_count($lines[$index], '[') - substr_count($lines[$index], ']');

            if ($depth <= 0) {
                $end = $index;

                break;
            }
        }

        if ($end === null) {
            return null;
        }

        $entryIndent = $this->indentOf($this->previousCodeLine($lines, $end) ?? $indent.'    ');
        $entryIndent = $entryIndent === '' ? $indent.'    ' : $entryIndent;

        array_splice($lines, $end, 0, [$entryIndent.$entry]);

        return implode($eol, $lines);
    }

    /**
     * Find the closing bracket that matches the opening bracket at $openPos,
     * ignoring brackets inside JSON strings.
     */
    private function matchingBracket(string $contents, int $openPos): ?int
    {
        $open = $contents[$openPos];
        $close = $open === '{' ? '}' : ']';
        $depth = 0;
        $inString = false;
        $escaped = false;
        $length = strlen($contents);

        for ($index = $openPos; $index < $length; $index++) {
            $char = $contents[$index];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;

                    continue;
                }

                if ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;
            } elseif ($char === $open) {
                $depth++;
            } elseif ($char === $close) {
                $depth--;

                if ($depth === 0) {
                    return $index;
                }
            }
        }

        return null;
    }

    /**
     * The indentation unit used by the file: the leading whitespace of the
     * first indented line, falling back to four spaces.
     */
    private function indentUnit(string $contents): string
    {
        if (preg_match('/^([ \t]+)"/m', $contents, $match) === 1) {
            return $match[1];
        }

        return '    ';
    }

    private function lineAt(string $contents, int $offset): string
    {
        $start = strrpos(substr($contents, 0, $offset), "\n");
        $start = $start === false ? 0 : $start + 1;
        $end = strpos($contents, "\n", $offset);
        $end = $end === false ? strlen($contents) : $end;

        return substr($contents, $start, $end - $start);
    }

    private function lastCodeLine(string $contents): ?string
    {
        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];

        for ($index = count($lines) - 1; $index >= 0; $index--) {
            if (trim($lines[$index]) !== '') {
                return $lines[$index];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $lines
     */
    private function previousCodeLine(array $lines, int $before): ?string
    {
        for ($index = $before - 1; $index >= 0; $index--) {
            if (trim($lines[$index]) !== '') {
                return $lines[$index];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $lines
     */
    private function nextCodeLine(array $lines, int $after): ?string
    {
        for ($index = $after + 1; $index < count($lines); $index++) {
            if (trim($lines[$index]) !== '') {
                return $lines[$index];
            }
        }

        return null;
    }

    private function indentOf(string $line): string
    {
        preg_match('/^[ \t]*/', $line, $match);

        return $match[0] ?? '';
    }
}
