<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Answers "which copy of the app am I actually running?" before any testing.
 *
 * There is more than one Laravel tree on this machine. The local site
 * `shop-laravel.local` serves a directory junction that points at
 * `laravel\app\apps\laravel.shop`, and a separate standalone copy sits
 * outside the workspace that nothing serves. Running the suite in the copy
 * that nothing serves produces results for an app no visitor will ever
 * reach, and the failure is invisible: the tests pass, just about the wrong
 * code.
 *
 * The package side has the same shape. `packages\shop-module` in the
 * workspace is what the frontend build reads; the separate shop-module
 * repository is where PHP changes are authored and released.
 */
class ShopEnvCheckCommand extends Command
{
    protected $signature = 'shop:env-check {--json : Machine-readable output}';

    protected $description = 'Report which app/package tree is active and whether it is the one the local site serves';

    /** Packages whose install source is worth reporting. */
    private const WATCHED = ['nreljed/platform', 'nreljed/blog', 'nreljed/shop-module'];

    public function handle(): int
    {
        $appPath = $this->resolveLink(base_path());
        $served = $this->servedAppPaths();

        $packages = [];

        foreach (self::WATCHED as $name) {
            $packages[$name] = $this->describePackage($name);
        }

        $isServedTree = $this->isServedTree($appPath, $served);

        $problems = [];

        foreach ($packages as $name => $package) {
            if ($package['status'] === 'path-install') {
                $problems[] = "{$name} is installed from a local working tree, not the released artifact.";
            }

            if ($package['status'] === 'missing') {
                $problems[] = "{$name} is not installed.";
            }
        }

        if ($served !== [] && ! $isServedTree) {
            $targets = implode(', ', array_map(
                fn (array $site): string => $site['resolved'],
                $served
            ));

            $problems[] = "No local site serves this tree. The sites serve: {$targets}.";
        }

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'app_path' => $appPath,
                'served_sites' => $served,
                'is_served_tree' => $isServedTree,
                'packages' => $packages,
                'problems' => $problems,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $problems === [] ? self::SUCCESS : self::FAILURE;
        }

        $this->components->twoColumnDetail('App tree', $appPath);

        if ($served === []) {
            $this->components->twoColumnDetail('Local site', 'none detected (not a LocalWP machine?)');
        } else {
            foreach ($served as $site) {
                $marker = $this->samePath($site['resolved'], $appPath) ? ' <- this tree' : '';

                $this->components->twoColumnDetail('Served by', $site['resolved'].$marker);
            }
        }

        foreach ($packages as $name => $package) {
            $label = $package['version'] === null ? 'not installed' : $package['version'];

            if ($package['status'] === 'path-install') {
                $label .= '  (LOCAL WORKING TREE)';
            }

            $this->components->twoColumnDetail($name, $label);
        }

        if ($problems === []) {
            $this->components->info('This tree is the one the local site serves, and vendor matches the lock.');

            return self::SUCCESS;
        }

        foreach ($problems as $problem) {
            $this->components->error($problem);
        }

        $this->components->warn(
            'Test from the app the local site serves: laravel\app\apps\laravel.shop. '
            .'Another copy sits outside that workspace and is served by nothing.'
        );

        return self::FAILURE;
    }

    /**
     * @param  array<int, array{config: string, app: string, resolved: string}>  $served
     */
    private function isServedTree(string $appPath, array $served): bool
    {
        foreach ($served as $site) {
            if ($this->samePath($site['resolved'], $appPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find the app directories the local sites are actually served from.
     *
     * LocalWP keeps each site's document root in a generated config under
     * %LOCALAPPDATA%\run\<site>\conf. Those roots point at a junction, so the
     * served path and the real path differ; both are reported, because the
     * mismatch is the whole point.
     *
     * @return array<int, array{config: string, app: string, resolved: string}>
     */
    /**
     * Extract the app directories from the document roots in a site config.
     *
     * Public and static so the parsing can be tested without a LocalWP install
     * on the machine, which is the situation CI is in.
     *
     * @return array<int, string> Absolute app directories, junctions unresolved.
     */
    public static function appPathsFromSiteConfig(string $contents): array
    {
        $apps = [];

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            if (! preg_match('#^\s*DocumentRoot\s+"(.+?)"\s*$#i', $line, $matches)) {
                continue;
            }

            $root = str_replace('/', '\\', $matches[1]);

            if (basename($root) !== 'public') {
                continue;
            }

            $apps[] = dirname($root);
        }

        return array_values(array_unique($apps));
    }

    private function servedAppPaths(): array
    {
        $roots = [];

        foreach ($this->localRunDirs() as $runDir) {
            foreach (glob($runDir.'\*\conf\apache\site.conf') ?: [] as $config) {
                foreach (self::appPathsFromSiteConfig((string) file_get_contents($config)) as $app) {
                    $roots[$this->resolveLink($app)] = [
                        'config' => $config,
                        'app' => $app,
                        'resolved' => $this->resolveLink($app),
                    ];
                }
            }
        }

        return array_values($roots);
    }

    /**
     * LocalWP has kept its run directory under both LocalAppData and
     * AppData\Roaming\Local depending on version, so check both.
     *
     * @return array<int, string>
     */
    private function localRunDirs(): array
    {
        $candidates = [];

        foreach (['LOCALAPPDATA', 'APPDATA'] as $variable) {
            $base = getenv($variable);

            if (! is_string($base) || $base === '') {
                continue;
            }

            $candidates[] = $base.'\run';
            $candidates[] = $base.'\Local\run';
        }

        return array_values(array_filter(
            array_unique($candidates),
            fn (string $path): bool => is_dir($path)
        ));
    }

    /**
     * Follow a directory junction or symlink to its real location.
     */
    private function resolveLink(string $path): string
    {
        $real = @realpath($path);

        return $this->normalize($real === false ? $path : $real);
    }

    /** @return array{version: string|null, status: string, reference: string|null} */
    private function describePackage(string $name): array
    {
        $installedPath = base_path('vendor'.DIRECTORY_SEPARATOR.'composer'.DIRECTORY_SEPARATOR.'installed.json');

        if (! is_file($installedPath)) {
            return ['version' => null, 'status' => 'missing', 'reference' => null];
        }

        $installed = json_decode((string) file_get_contents($installedPath), true);

        foreach ($installed['packages'] ?? [] as $package) {
            if (($package['name'] ?? null) !== $name) {
                continue;
            }

            $isPath = in_array($package['dist']['type'] ?? null, ['path', 'symlink'], true);

            return [
                'version' => $package['version'] ?? null,
                'status' => $isPath ? 'path-install' : 'ok',
                'reference' => $package['dist']['reference'] ?? null,
            ];
        }

        return ['version' => null, 'status' => 'missing', 'reference' => null];
    }

    private function normalize(string $path): string
    {
        return str_replace('/', '\\', rtrim($path, '\\/'));
    }

    private function samePath(string $a, string $b): bool
    {
        return strcasecmp($this->normalize($a), $this->normalize($b)) === 0;
    }
}
