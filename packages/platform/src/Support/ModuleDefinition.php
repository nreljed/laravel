<?php

declare(strict_types=1);

namespace Nreljed\Platform\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Everything `make:module` derives from a single module name.
 *
 * Kept as a value object (and free of container access) so the scaffolder can
 * be driven from a test without booting an application. Every derived string
 * the stubs need is computed once here: route prefixes, page keys, the Composer
 * and npm package names, the namespace and the table name.
 */
final class ModuleDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $slug,
        public readonly string $namespace,
        public readonly string $vendor,
        public readonly string $root,
        public readonly string $navGroup,
        public readonly string $version = '0.1.0',
    ) {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $this->name) !== 1) {
            throw new InvalidArgumentException(
                "Module name [{$this->name}] must be a valid PHP class prefix (letters, digits, underscore)."
            );
        }

        if (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $this->slug) !== 1) {
            throw new InvalidArgumentException(
                "Module slug [{$this->slug}] must be lowercase kebab-case — it is used as a route and page prefix."
            );
        }

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $this->namespace) !== 1) {
            throw new InvalidArgumentException(
                "Module namespace [{$this->namespace}] must be a valid PHP namespace."
            );
        }
    }

    /**
     * Build a definition from the command's arguments and options.
     *
     * @param  string  $root  Absolute directory the package is generated into.
     */
    public static function for(
        string $name,
        string $root,
        ?string $slug = null,
        ?string $namespace = null,
        ?string $navGroup = null,
        string $vendor = 'nreljed',
    ): self {
        $studly = Str::studly($name);

        return new self(
            name: $studly,
            slug: Str::slug($slug ?? $name),
            namespace: trim($namespace ?? Str::studly($vendor)."\\{$studly}Module", '\\'),
            vendor: $vendor,
            root: rtrim(str_replace('\\', '/', $root), '/'),
            navGroup: $navGroup ?? Str::headline($studly),
        );
    }

    /**
     * The Composer package name, e.g. `nreljed/events-module`.
     */
    public function package(): string
    {
        return "{$this->vendor}/{$this->slug}-module";
    }

    /**
     * The npm package name, e.g. `@nreljed/events-module`.
     */
    public function npmPackage(): string
    {
        return "@{$this->vendor}/{$this->slug}-module";
    }

    public function providerClass(): string
    {
        return "{$this->name}ServiceProvider";
    }

    public function controllerClass(): string
    {
        return "{$this->name}Controller";
    }

    /**
     * The frontend page-kit factory the consuming app spreads into its page
     * map, e.g. `registerEventsPages`.
     */
    public function pagesFunction(): string
    {
        return 'register'.$this->name.'Pages';
    }

    /**
     * The page-prefix constant the kit exports, as a template-literal
     * expression (e.g. `${eventsPagePrefix}`) so the generated TypeScript does
     * not need whitespace tricks inside the interpolation.
     */
    public function pageKeyPrefix(): string
    {
        return '${'.$this->slug.'PagePrefix}';
    }

    /**
     * The namespace with its backslashes escaped for a JSON string, so the
     * generated composer.json is valid rather than full of `\E`/`\M` escapes.
     */
    public function namespaceJson(): string
    {
        return str_replace('\\', '\\\\', $this->namespace);
    }

    public function providerFqcn(): string
    {
        return $this->namespace.'\\'.$this->providerClass();
    }

    public function routePrefix(): string
    {
        return 'dashboard/'.$this->slug;
    }

    public function routeNamePrefix(): string
    {
        return 'dashboard.'.$this->slug.'.';
    }

    /**
     * The Inertia component name the controller renders. The app's page map
     * resolves it from the module's own page kit.
     */
    public function pageName(): string
    {
        return $this->slug.'/index';
    }

    public function table(): string
    {
        return Str::snake(Str::pluralStudly($this->name));
    }

    /**
     * Directory the package lives in, relative to the app root when the
     * package is nested (e.g. `modules/Events`), used for the path repository
     * and the npm `link:` dependency.
     */
    public function relativeRoot(string $appRoot): string
    {
        $appRoot = rtrim(str_replace('\\', '/', $appRoot), '/');

        if ($this->root === $appRoot) {
            return '.';
        }

        if (str_starts_with($this->root, $appRoot.'/')) {
            return substr($this->root, strlen($appRoot) + 1);
        }

        return $this->root;
    }

    /**
     * @return array<string, string>
     */
    public function stubTokens(): array
    {
        return [
            '{{ name }}' => $this->name,
            '{{ slug }}' => $this->slug,
            '{{ namespace }}' => $this->namespace,
            '{{ namespaceJson }}' => $this->namespaceJson(),
            '{{ vendor }}' => $this->vendor,
            '{{ package }}' => $this->package(),
            '{{ npmPackage }}' => $this->npmPackage(),
            '{{ providerClass }}' => $this->providerClass(),
            '{{ controllerClass }}' => $this->controllerClass(),
            '{{ pagesFunction }}' => $this->pagesFunction(),
            '{{ pageKeyPrefix }}' => $this->pageKeyPrefix(),
            '{{ navGroup }}' => $this->navGroup,
            '{{ routePrefix }}' => $this->routePrefix(),
            '{{ routeNamePrefix }}' => $this->routeNamePrefix(),
            '{{ pageName }}' => $this->pageName(),
            '{{ table }}' => $this->table(),
            '{{ version }}' => $this->version,
        ];
    }
}
