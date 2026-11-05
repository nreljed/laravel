<?php

declare(strict_types=1);

namespace Nreljed\Platform\Tests\Console;

use Illuminate\Container\Container;
use Nreljed\Platform\Console\Commands\MakeModule;
use Nreljed\Platform\Contracts\Capabilities\HasNavigation;
use Nreljed\Platform\Contracts\Capabilities\HasPermissions;
use Nreljed\Platform\Contracts\Feature;
use Nreljed\Platform\Contracts\FeatureProvider;
use Nreljed\Platform\PlatformServiceProvider;
use Nreljed\Platform\Support\ModuleActivator;
use Nreljed\Platform\Support\ModuleDefinition;
use Nreljed\Platform\Support\ModuleScaffolder;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Contract test for `make:module`.
 *
 * The platform suite deliberately does not boot the framework, so the generator
 * is exercised the way it is actually used: the value object, the scaffolder and
 * the activator are container-free by design, which lets the whole generation
 * path run against a temporary directory here.
 */
final class MakeModuleCommandTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryDirectories = [];

    private string $lintOutput = '';

    protected function tearDown(): void
    {
        foreach ($this->temporaryDirectories as $directory) {
            $this->deleteDirectory($directory);
        }

        $this->temporaryDirectories = [];

        parent::tearDown();
    }

    public function test_command_is_registered_by_the_platform_service_provider(): void
    {
        $source = (string) file_get_contents((new ReflectionClass(PlatformServiceProvider::class))->getFileName());

        $this->assertStringContainsString(
            'Console\\Commands\\MakeModule::class',
            $source,
            'make:module must be registered in PlatformServiceProvider::loadConsoleRoutes().',
        );
    }

    public function test_command_declares_the_scaffolding_options(): void
    {
        $signature = (string) ((new ReflectionClass(MakeModule::class))->getDefaultProperties()['signature'] ?? '');

        $this->assertStringContainsString('make:module', $signature);
        $this->assertStringContainsString('{name : The module name', $signature);

        foreach (['--slug=', '--namespace=', '--vendor=', '--path=', '--nav-group=', '--with-migrations', '--with-tests', '--activate', '--force'] as $option) {
            $this->assertStringContainsString($option, $signature, "make:module must declare {$option}.");
        }
    }

    public function test_definition_derives_the_package_identifiers(): void
    {
        $module = ModuleDefinition::for('Events', $this->temporaryDirectory());

        $this->assertSame('Events', $module->name);
        $this->assertSame('events', $module->slug);
        $this->assertSame('Nreljed\\EventsModule', $module->namespace);
        $this->assertSame('nreljed/events-module', $module->package());
        $this->assertSame('@nreljed/events-module', $module->npmPackage());
        $this->assertSame('EventsServiceProvider', $module->providerClass());
        $this->assertSame('Nreljed\\EventsModule\\EventsServiceProvider', $module->providerFqcn());
        $this->assertSame('registerEventsPages', $module->pagesFunction());
        $this->assertSame('dashboard/events', $module->routePrefix());
        $this->assertSame('dashboard.events.', $module->routeNamePrefix());
        $this->assertSame('events/index', $module->pageName());
        $this->assertSame('events', $module->table());
    }

    public function test_definition_rejects_an_invalid_slug(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ModuleDefinition::for('Events', $this->temporaryDirectory(), slug: '!!!');
    }

    public function test_definition_rejects_an_invalid_namespace(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ModuleDefinition::for('Events', $this->temporaryDirectory(), namespace: '1Invalid\\Namespace');
    }

    public function test_it_scaffolds_a_complete_module(): void
    {
        $module = ModuleDefinition::for('Events', $this->temporaryDirectory().'/Events');

        $written = $this->scaffolder()->scaffold($module, withMigrations: true, withTests: true);

        foreach ([
            'composer.json',
            'package.json',
            'README.md',
            '.gitignore',
            '.gitattributes',
            'src/EventsServiceProvider.php',
            'src/Http/Controllers/EventsController.php',
            'src/Routes/web.php',
            'src/Resources/js/pages.ts',
            'src/Resources/js/Pages/Index.tsx',
            'database/migrations/.gitkeep',
            'tests/bootstrap.php',
            'tests/Feature/EventsModuleTest.php',
            'phpunit.xml',
        ] as $file) {
            $this->assertContains($file, $written, "make:module did not write {$file}.");
            $this->assertFileExists($module->root.'/'.$file);
        }
    }

    public function test_generated_manifests_are_valid_json_and_point_at_the_module(): void
    {
        $module = ModuleDefinition::for('Events', $this->temporaryDirectory().'/Events');
        $this->scaffolder()->scaffold($module);

        $composer = $this->decodeJson($module->root.'/composer.json');
        $package = $this->decodeJson($module->root.'/package.json');

        $this->assertSame('nreljed/events-module', $composer['name']);
        $this->assertSame('0.1.0', $composer['version']);
        $this->assertSame('src/', $composer['autoload']['psr-4']['Nreljed\\EventsModule\\']);
        $this->assertSame('>=0.3 <1.0', $composer['require']['nreljed/platform']);
        $this->assertSame(
            ['Nreljed\\EventsModule\\EventsServiceProvider'],
            $composer['extra']['laravel']['providers'],
        );

        $this->assertSame('@nreljed/events-module', $package['name']);
        $this->assertSame('./src/Resources/js/pages.ts', $package['exports']['./pages']);
        $this->assertArrayHasKey('@inertiajs/react', $package['peerDependencies']);
        $this->assertArrayHasKey('react', $package['peerDependencies']);
    }

    public function test_generated_php_files_are_syntactically_valid(): void
    {
        $module = ModuleDefinition::for('Events', $this->temporaryDirectory().'/Events');
        $this->scaffolder()->scaffold($module, withMigrations: true, withTests: true);

        $files = [
            $module->root.'/src/EventsServiceProvider.php',
            $module->root.'/src/Http/Controllers/EventsController.php',
            $module->root.'/src/Routes/web.php',
            $module->root.'/tests/bootstrap.php',
            $module->root.'/tests/Feature/EventsModuleTest.php',
        ];

        foreach (glob($module->root.'/database/migrations/*.php') ?: [] as $migration) {
            $files[] = $migration;
        }

        foreach ($files as $file) {
            $this->assertSame(0, $this->lint($file), "Generated PHP is invalid: {$file}\n".$this->lintOutput);
        }
    }

    public function test_generated_files_contain_no_unrendered_stub_tokens(): void
    {
        $module = ModuleDefinition::for('Events', $this->temporaryDirectory().'/Events');
        $this->scaffolder()->scaffold($module, withMigrations: true, withTests: true);

        foreach ($this->allFiles($module->root) as $file) {
            $this->assertStringNotContainsString(
                '{{',
                (string) file_get_contents($file),
                "Unrendered stub token left in {$file}.",
            );
        }
    }

    public function test_the_generated_provider_satisfies_the_feature_contract(): void
    {
        $module = ModuleDefinition::for('Scaffolded', $this->temporaryDirectory().'/Scaffolded');
        $this->scaffolder()->scaffold($module);

        require_once $module->root.'/src/ScaffoldedServiceProvider.php';

        $class = $module->providerFqcn();
        $reflection = new ReflectionClass($class);

        $this->assertTrue($reflection->implementsInterface(Feature::class));
        $this->assertTrue($reflection->implementsInterface(HasNavigation::class));
        $this->assertTrue($reflection->implementsInterface(HasPermissions::class));
        $this->assertFalse(
            $reflection->implementsInterface(FeatureProvider::class),
            'New modules must implement the capability interfaces, not the deprecated composite.',
        );

        $provider = new $class(new Container);

        $this->assertSame('Scaffolded', $provider->name());
        $this->assertSame('scaffolded', $provider->slug());
        $this->assertSame('nreljed/scaffolded-module', $provider->getComposerPackage());
        $this->assertSame('Scaffolded', $provider->getNavGroupLabel());

        $slugs = array_map(
            static fn (array|string $permission): string => is_array($permission) ? (string) $permission['slug'] : $permission,
            $provider->permissions(),
        );

        $this->assertSame(['scaffolded.view', 'scaffolded.manage'], $slugs);
    }

    public function test_the_nav_item_is_built_from_the_module_route(): void
    {
        $module = ModuleDefinition::for('Events', $this->temporaryDirectory().'/Events');
        $this->scaffolder()->scaffold($module);

        $source = (string) file_get_contents($module->root.'/src/EventsServiceProvider.php');

        $this->assertStringContainsString("route('dashboard.events.index'", $source);
        $this->assertStringContainsString("'permission' => 'events.view'", $source);
    }

    public function test_it_refuses_to_write_into_a_non_empty_directory(): void
    {
        $root = $this->temporaryDirectory().'/Events';
        $this->scaffolder()->scaffold(ModuleDefinition::for('Events', $root));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already exists and is not empty');

        $this->scaffolder()->scaffold(ModuleDefinition::for('Events', $root));
    }

    public function test_force_rewrites_the_generated_files(): void
    {
        $root = $this->temporaryDirectory().'/Events';
        $this->scaffolder()->scaffold(ModuleDefinition::for('Events', $root));

        $provider = $root.'/src/EventsServiceProvider.php';
        file_put_contents($provider, '<?php // edited');

        $this->scaffolder()->scaffold(ModuleDefinition::for('Events', $root), force: true);

        $this->assertStringContainsString('class EventsServiceProvider', (string) file_get_contents($provider));
    }

    public function test_it_activates_the_module_in_a_host_application(): void
    {
        $appRoot = $this->fakeApplication();
        $module = ModuleDefinition::for('Events', $appRoot.'/modules/Events');

        $this->scaffolder()->scaffold($module);

        $status = (new ModuleActivator)->activate($module, $appRoot);

        $this->assertStringStartsWith('patched:', $status['composer']);
        $this->assertStringStartsWith('patched:', $status['npm']);
        $this->assertStringStartsWith('patched:', $status['providers']);
        $this->assertStringStartsWith('patched:', $status['features']);
        $this->assertStringStartsWith('patched:', $status['pages']);

        $composer = $this->decodeJson($appRoot.'/composer.json');
        $pathRepository = null;

        foreach ($composer['repositories'] as $repository) {
            if (($repository['name'] ?? null) === 'events-module') {
                $pathRepository = $repository;
            }
        }

        $this->assertIsArray($pathRepository, 'Activation must append a named path repository.');
        $this->assertSame('path', $pathRepository['type']);
        $this->assertSame('modules/Events', $pathRepository['url']);
        $this->assertSame('@dev', $composer['require']['nreljed/events-module']);

        $package = $this->decodeJson($appRoot.'/package.json');
        $this->assertSame('link:modules/Events', $package['dependencies']['@nreljed/events-module']);

        $providers = (string) file_get_contents($appRoot.'/bootstrap/providers.php');
        $this->assertStringContainsString('use Nreljed\\EventsModule\\EventsServiceProvider;', $providers);
        $this->assertStringContainsString('EventsServiceProvider::class,', $providers);
        $this->assertSame(1, substr_count($providers, 'EventsServiceProvider::class'));

        $features = (string) file_get_contents($appRoot.'/config/features.php');
        $this->assertStringContainsString('EventsServiceProvider::class,', $features);
        $this->assertStringContainsString("'events' => true,", $features);

        $pageConfig = (string) file_get_contents($appRoot.'/resources/js/page-config.ts');
        $this->assertStringContainsString('import { registerEventsPages } from "@nreljed/events-module/pages";', $pageConfig);
        $this->assertStringContainsString('...registerEventsPages(),', $pageConfig);
    }

    public function test_activation_is_idempotent(): void
    {
        $appRoot = $this->fakeApplication();
        $module = ModuleDefinition::for('Events', $appRoot.'/modules/Events');
        $this->scaffolder()->scaffold($module);

        $activator = new ModuleActivator;
        $activator->activate($module, $appRoot);
        $second = $activator->activate($module, $appRoot);

        foreach ($second as $step => $status) {
            $this->assertStringStartsWith('already:', $status, "Second activation of {$step} must be a no-op.");
        }

        $providers = (string) file_get_contents($appRoot.'/bootstrap/providers.php');
        $this->assertSame(1, substr_count($providers, 'EventsServiceProvider::class'));

        $features = (string) file_get_contents($appRoot.'/config/features.php');
        $this->assertSame(1, substr_count($features, "'events' => true,"));
    }

    public function test_activation_reports_skipped_steps_instead_of_failing(): void
    {
        $appRoot = $this->temporaryDirectory();
        $module = ModuleDefinition::for('Events', $appRoot.'/modules/Events');
        $this->scaffolder()->scaffold($module);

        $status = (new ModuleActivator)->activate($module, $appRoot);

        foreach ($status as $step => $message) {
            $this->assertStringStartsWith('skipped:', $message, "Missing host file must skip {$step}, not throw.");
        }
    }

    public function test_activation_expands_an_empty_providers_array(): void
    {
        $appRoot = $this->fakeApplication(features: "<?php\n\nuse Nreljed\\Platform\\PublicSite\\PublicSiteServiceProvider;\n\nreturn [\n    'providers' => [],\n    'enabled' => [],\n];\n");
        $module = ModuleDefinition::for('Events', $appRoot.'/modules/Events');
        $this->scaffolder()->scaffold($module);

        $status = (new ModuleActivator)->activateFeatureConfig($module, $appRoot);

        $this->assertStringContainsString('patched:', $status);

        $features = (string) file_get_contents($appRoot.'/config/features.php');

        $this->assertStringContainsString("'providers' => [\n        EventsServiceProvider::class,\n    ],", $features);
        $this->assertStringContainsString("'enabled' => [\n        'events' => true,\n    ],", $features);
        $this->assertStringContainsString('use Nreljed\\EventsModule\\EventsServiceProvider;', $features);
    }

    public function test_activation_falls_back_to_the_fqcn_when_the_config_has_no_imports(): void
    {
        $appRoot = $this->fakeApplication(features: "<?php\n\nreturn [\n    'providers' => [],\n    'enabled' => [],\n];\n");
        $module = ModuleDefinition::for('Events', $appRoot.'/modules/Events');
        $this->scaffolder()->scaffold($module);

        $status = (new ModuleActivator)->activateFeatureConfig($module, $appRoot);

        $this->assertStringContainsString('patched:', $status);

        $features = (string) file_get_contents($appRoot.'/config/features.php');

        $this->assertStringContainsString('\\Nreljed\\EventsModule\\EventsServiceProvider::class,', $features);
        $this->assertStringNotContainsString('use Nreljed\\EventsModule', $features);
    }

    private function scaffolder(): ModuleScaffolder
    {
        return new ModuleScaffolder(__DIR__.'/../../src/Console/stubs/module');
    }

    /**
     * A minimal host application: exactly the five files activation touches.
     */
    private function fakeApplication(?string $features = null): string
    {
        $root = $this->temporaryDirectory();

        mkdir($root.'/bootstrap', 0777, true);
        mkdir($root.'/config', 0777, true);
        mkdir($root.'/resources/js', 0777, true);

        file_put_contents($root.'/composer.json', <<<'JSON'
        {
            "name": "acme/app",
            "repositories": [
                {
                    "type": "vcs",
                    "url": "https://github.com/nreljed/platform.git"
                }
            ],
            "require": {
                "php": "^8.4",
                "nreljed/platform": "^0.3"
            }
        }
        JSON);

        file_put_contents($root.'/package.json', <<<'JSON'
        {
          "name": "acme-app",
          "private": true,
          "dependencies": {
            "@nreljed/platform": "link:packages/platform"
          }
        }
        JSON);

        file_put_contents($root.'/bootstrap/providers.php', <<<'PHP'
        <?php

        use Nreljed\Platform\PlatformServiceProvider;

        return array_filter([
            PlatformServiceProvider::class,
        ]);
        PHP);

        file_put_contents($root.'/config/features.php', $features ?? <<<'PHP'
        <?php

        use Nreljed\Platform\PublicSite\PublicSiteServiceProvider;

        return [
            'providers' => [
                PublicSiteServiceProvider::class,
            ],
            'enabled' => [
                'public-site' => true,
            ],
        ];
        PHP);

        file_put_contents($root.'/resources/js/page-config.ts', <<<'TS'
        import { registerCoreTemplates } from "@nreljed/platform/dashboard";

        const pageConfig = {
            basePages: {
                ...registerCoreTemplates(),
            },
        };
        TS);

        return $root;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $path): array
    {
        $this->assertFileExists($path);

        $decoded = json_decode((string) file_get_contents($path), true);

        $this->assertIsArray($decoded, "{$path} is not valid JSON.");

        return $decoded;
    }

    /**
     * `php -l` the file; returns the exit code (0 when clean).
     */
    private function lint(string $file): int
    {
        if (! function_exists('exec')) {
            $this->markTestSkipped('exec() is disabled, so generated PHP cannot be linted.');
        }

        $this->lintOutput = '';
        $output = [];
        $code = 1;

        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file).' 2>&1', $output, $code);

        $this->lintOutput = implode("\n", $output);

        return $code;
    }

    /**
     * @return list<string>
     */
    private function allFiles(string $root): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir().'/nreljed-make-module-'.bin2hex(random_bytes(6));

        mkdir($directory, 0777, true);

        $this->temporaryDirectories[] = $directory;

        return str_replace('\\', '/', $directory);
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $entry) {
            $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
        }

        @rmdir($directory);
    }
}
