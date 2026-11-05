<?php

namespace Nreljed\Platform\Tests;

use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Facade;
use Nreljed\Platform\Concerns\InteractsWithFeatures;
use Nreljed\Platform\Contracts\Capabilities\HasDashboardWidgets;
use Nreljed\Platform\Contracts\Capabilities\HasNavigation;
use Nreljed\Platform\Contracts\Capabilities\HasPermissions;
use Nreljed\Platform\Contracts\Capabilities\HasSettings;
use Nreljed\Platform\Contracts\Capabilities\HasSitemap;
use Nreljed\Platform\Contracts\Feature;
use Nreljed\Platform\Services\FeatureRegistry;
use PHPUnit\Framework\TestCase;

/**
 * The capability split is only worth anything if the registry actually adapts:
 * a feature that declares navigation and nothing else must contribute
 * navigation and nothing else.
 *
 * These tests register features that deliberately do NOT implement the
 * deprecated FeatureProvider composite, so the capability dispatch is exercised
 * rather than bypassed by the fat interface's no-op defaults.
 */
final class CapabilityDispatchTest extends TestCase
{
    private Container $container;

    private ?FeatureRegistry $registry = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new class extends Container
        {
            /** @var array<class-string, object> */
            private array $providers = [];

            public function registerProvider(object $provider, string $class): void
            {
                $this->providers[$class] = $provider;
            }

            public function getProvider($provider): ?object
            {
                $class = is_object($provider) ? get_class($provider) : $provider;

                return $this->providers[$class] ?? null;
            }

            public function basePath($path = ''): string
            {
                return __DIR__.($path !== '' ? DIRECTORY_SEPARATOR.$path : '');
            }
        };

        Container::setInstance($this->container);
        Facade::setFacadeApplication($this->container);
        Facade::clearResolvedInstances();

        $this->container->instance('config', new ConfigRepository([
            'features' => ['providers' => [], 'enabled' => []],
            'app' => ['url' => 'http://localhost'],
        ]));

        $events = new Dispatcher($this->container);
        $this->container->instance('events', $events);

        $router = new Router($events, $this->container);
        $router->addRoute(['GET'], '/dashboard/notes', fn () => null)->name('dashboard.notes.index');
        $router->getRoutes()->refreshNameLookups();

        $url = new UrlGenerator($router->getRoutes(), Request::create('http://localhost'));

        $this->container->instance('router', $router);
        $this->container->instance('url', $url);
        $this->container->instance(Router::class, $router);
        $this->container->instance(UrlGenerator::class, $url);
    }

    public function test_a_feature_that_is_not_a_feature_provider_is_accepted(): void
    {
        $this->register(NavigationOnlyFeature::class);

        $this->assertSame(
            ['navigation-only'],
            array_column($this->registry()->getModuleList(), 'slug'),
            'The registry must accept the minimal Feature contract, not demand the deprecated composite.'
        );
    }

    public function test_a_navigation_only_feature_contributes_navigation(): void
    {
        $this->register(NavigationOnlyFeature::class);

        $items = $this->registry()->getAllNavItems();

        $this->assertCount(1, $items);
        $this->assertSame('Notes', $items[0]['groupLabel']);
        $this->assertSame('Notes', $items[0]['title']);
        $this->assertSame('FileText', $items[0]['icon'], 'A missing icon still degrades to the registry default.');
        $this->assertSame(
            '/dashboard/notes',
            parse_url($items[0]['url'], PHP_URL_PATH),
            'Hand-written nav URLs must pass through untouched.'
        );
    }

    public function test_a_navigation_only_feature_contributes_nothing_else(): void
    {
        $this->register(NavigationOnlyFeature::class);

        $registry = $this->registry();

        $this->assertSame([], $registry->getAllSharedData());
        $this->assertSame([], $registry->getAllDashboardData());
        $this->assertSame([], $registry->getAllAnalyticsSeries());
        $this->assertSame([], $registry->getAllNotificationTypes());
        $this->assertSame([], $registry->getAllEmailTemplates());
        $this->assertSame([], $registry->getAllSitemapUrls());
        $this->assertSame([], $registry->getAllOrphanMediaTargets());
        $this->assertSame([], $registry->getAllSettingsGroups());
        $this->assertSame([], $registry->getAllAdminResources());
        $this->assertSame([], $registry->getAllContentDescriptions());
        $this->assertSame([], $registry->getAllMediaSubdirectories());
        $this->assertSame([], $registry->getAllHealthChecks());
        $this->assertSame([], $registry->getAllScheduledTasks());
        $this->assertSame([], $registry->getAllSettingsSections());
        $this->assertSame([], $registry->getAllSettingsNav());
        $this->assertSame([], $registry->getAllDeactivationNotices());
        $this->assertSame([], $registry->getDataBackupModules());
        $this->assertSame([], $registry->permissions());
        $this->assertSame([], $registry->getAllGalleryConfigs());
        $this->assertSame([], $registry->getAllProfileSections());
        $this->assertSame([], $registry->getAllMetaFormattingConfigs());
        $this->assertSame([], $registry->getAllRepeaterConfigs());
        $this->assertSame([], $registry->getAllProfileFieldConfigs());
    }

    public function test_a_navigation_only_feature_has_no_settings_in_the_module_list(): void
    {
        $this->register(NavigationOnlyFeature::class);

        $module = $this->registry()->getModuleList()[0];

        $this->assertFalse($module['has_settings']);
        $this->assertNull($module['settings_route']);
        $this->assertSame([], $module['dependencies']);
        $this->assertNull($module['package'], 'A feature bundled in core declares no composer package.');
    }

    public function test_a_fat_feature_provider_still_contributes_every_capability(): void
    {
        $this->register(FatFeatureProvider::class);

        $registry = $this->registry();

        $this->assertSame(['title' => 'Fat', 'url' => '/dashboard/notes', 'icon' => 'FileText'], $registry->getAllNavItems()[0]);
        $this->assertCount(1, $registry->permissions());
        $this->assertSame('fat.feature.view', $registry->permissions()[0]['slug']);
        $this->assertSame('Fat Feature', $registry->permissions()[0]['group'], 'A permission without a group inherits the feature name.');
        $this->assertSame(['total' => 3, 'label' => 'Fat-feature', 'icon' => 'FileText'], $registry->getAllDashboardData()['fat-feature']);
        $this->assertSame([['label' => 'Fat Feature', 'route' => 'dashboard.notes.index', 'url' => '/dashboard/notes']], $registry->getAllSettingsNav());
        $this->assertSame(['/dashboard/notes'], array_column($registry->getAllSitemapUrls(), 'loc'));
    }

    public function test_capability_dispatch_does_not_change_validation(): void
    {
        $this->register(NavigationOnlyFeature::class);
        $this->register(FatFeatureProvider::class);

        $this->assertSame(
            [],
            $this->registry()->validate(),
            'Neither a minimal nor a complete feature may produce validation issues.'
        );
    }

    public function test_user_deletion_reaches_every_registered_feature(): void
    {
        $this->register(NavigationOnlyFeature::class);

        $feature = $this->container->getProvider(NavigationOnlyFeature::class);

        $this->registry()->cleanupUserData(42);

        $this->assertSame(42, $feature->deletedUserId, 'onUserDelete() lives on the minimal Feature contract, so it is always called.');
    }

    public function test_missing_dependencies_are_still_reported(): void
    {
        $this->register(DependentFeature::class);

        $missing = $this->registry()->missingDependencies();

        $this->assertCount(1, $missing);
        $this->assertSame('dependent-feature', $missing[0]['provider']);
        $this->assertSame('navigation-only', $missing[0]['dependency']);
    }

    private function register(string $providerClass): void
    {
        $instance = new $providerClass;

        $this->container->registerProvider($instance, $providerClass);
        $this->container['config']->set('features.providers', array_merge(
            (array) $this->container['config']->get('features.providers', []),
            [$providerClass],
        ));
        $this->container['config']->set("features.enabled.{$instance->slug()}", true);
    }

    private function registry(): FeatureRegistry
    {
        return $this->registry ??= new FeatureRegistry;
    }
}

/**
 * The whole point of the split: identity, lifecycle and navigation. No trait,
 * no 37 no-op methods, nothing else contributed.
 */
class NavigationOnlyFeature implements Feature, HasNavigation
{
    public ?int $deletedUserId = null;

    public function name(): string
    {
        return 'Navigation only';
    }

    public function slug(): string
    {
        return 'navigation-only';
    }

    public function description(): ?string
    {
        return 'A feature that only contributes navigation.';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function getComposerPackage(): ?string
    {
        return null;
    }

    public function dependencies(): array
    {
        return [];
    }

    public function enabled(): bool
    {
        return (bool) config('features.enabled.'.$this->slug(), false);
    }

    public function onDisable(): void {}

    public function onUserDelete(int $userId): void
    {
        $this->deletedUserId = $userId;
    }

    public function getNavGroupLabel(): ?string
    {
        return 'Notes';
    }

    public function getNavItems(): array
    {
        return [
            ['title' => 'Notes', 'url' => '/dashboard/notes'],
        ];
    }
}

/**
 * A feature that declares a dependency it cannot satisfy, to prove the
 * dependency check reads the minimal contract too.
 */
final class DependentFeature extends NavigationOnlyFeature
{
    public function name(): string
    {
        return 'Dependent';
    }

    public function slug(): string
    {
        return 'dependent-feature';
    }

    public function dependencies(): array
    {
        return ['navigation-only'];
    }
}

/**
 * The pre-0.3 shape: every capability, all at once. This must keep behaving
 * exactly as it did before the split.
 */
final class FatFeatureProvider implements Feature, HasDashboardWidgets, HasNavigation, HasPermissions, HasSettings, HasSitemap
{
    use InteractsWithFeatures;

    public function name(): string
    {
        return 'Fat Feature';
    }

    public function slug(): string
    {
        return 'fat-feature';
    }

    public function getNavItems(): array
    {
        return [['title' => 'Fat', 'url' => '/dashboard/notes']];
    }

    public function permissions(): array
    {
        return [['slug' => 'fat.feature.view']];
    }

    public function hasSettings(): bool
    {
        return true;
    }

    public function getSettingsRoute(): ?string
    {
        return 'dashboard.notes.index';
    }

    public function getDashboardData(): array
    {
        return ['total' => 3];
    }

    public function getSitemapUrls(): array
    {
        return [['loc' => '/dashboard/notes', 'changefreq' => 'weekly', 'priority' => '0.5']];
    }
}
