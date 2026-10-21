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
use Nreljed\Platform\Contracts\FeatureProvider;
use Nreljed\Platform\Services\FeatureRegistry;
use Nreljed\Platform\Support\ResourceDefinition;
use PHPUnit\Framework\TestCase;

/**
 * The registry is what the dashboard sidebar renders from, so the shape of
 * getAllNavItems() is a shipped contract, not an implementation detail.
 *
 * A module owns its navigation: it declares the group heading (inline
 * `groupLabel` or getNavGroupLabel()) and the links it wants, and the platform
 * folds the resources that module declared into the same collapsible group. The
 * regression these tests lock down is that descriptors used to escape as flat
 * top-level items under a hardcoded `Resources` heading, which both buried them
 * outside the module's own dropdown and duplicated any link the module had also
 * written by hand.
 */
final class NavItemsFeatureRegistryTest extends TestCase
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

            public function getProviders($provider): array
            {
                return array_values(array_filter(
                    $this->providers,
                    fn (object $instance) => $instance instanceof $provider,
                ));
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

        // getAllNavItems() resolves route names, so the harness needs a router
        // and a URL generator. Registering real route definitions keeps
        // Route::has() and route() honest instead of stubbed.
        $router = new Router($events, $this->container);

        foreach (self::routeNames() as $name => $uri) {
            $router->addRoute(['GET'], $uri, fn () => null)->name($name);
        }

        // Route::name() runs after the route joins the collection, so the
        // name lookup table has to be rebuilt before Route::has() can see it.
        $router->getRoutes()->refreshNameLookups();

        $url = new UrlGenerator($router->getRoutes(), Request::create('http://localhost'));

        $this->container->instance('router', $router);
        $this->container->instance('url', $url);
        $this->container->instance(Router::class, $router);
        $this->container->instance(UrlGenerator::class, $url);
    }

    /**
     * Route names the fake modules below point their descriptors at, mapped to
     * the URI each one resolves to. Only these names exist, so a descriptor
     * naming anything else is genuinely unroutable.
     *
     * @return array<string, string>
     */
    private static function routeNames(): array
    {
        return [
            'dashboard.products.index' => '/dashboard/products',
            'dashboard.currencies.index' => '/dashboard/currencies',
            'dashboard.tax-rates.index' => '/dashboard/tax-rates',
            'dashboard.categories.index' => '/dashboard/categories',
        ];
    }

    public function test_descriptors_are_nested_into_the_module_group_not_emitted_flat(): void
    {
        $this->register(CollapsibleModule::class);

        $items = $this->registry()->getAllNavItems();

        $this->assertCount(1, $items, 'A module group plus its descriptors must produce exactly one sidebar group.');
        $this->assertSame('Shop', $items[0]['title']);
        $this->assertSame('Manage Shop', $items[0]['groupLabel'], 'Descriptor links must inherit the module heading, not a synthetic one.');

        $urls = $this->subItemUrls($items[0]);

        $this->assertSame([
            '/dashboard/dashboard',
            '/dashboard/products',
            '/dashboard/currencies',
            '/dashboard/tax-rates',
        ], $urls);
    }

    public function test_no_group_label_is_invented_for_nested_descriptors(): void
    {
        $this->register(CollapsibleModule::class);

        $items = $this->registry()->getAllNavItems();

        foreach ($items[0]['items'] as $subItem) {
            $this->assertArrayNotHasKey(
                'groupLabel',
                $subItem,
                'A nested link carrying its own groupLabel would override the module heading when rendered.',
            );
        }

        $this->assertNotContains(
            'Resources',
            array_column($items, 'groupLabel'),
            'The synthetic Resources heading must not reappear once descriptors are adopted.',
        );
    }

    public function test_a_hand_written_link_wins_over_the_generated_duplicate(): void
    {
        $this->register(CollapsibleModule::class);

        $items = $this->registry()->getAllNavItems();

        $urls = $this->subItemUrls($items[0]);

        $this->assertSame(
            array_values(array_unique($urls)),
            $urls,
            'The module declared Products by hand and declared it as a descriptor; it must appear once.',
        );
        $this->assertSame(1, count(array_keys($urls, '/dashboard/products', true)));
    }

    public function test_descriptor_links_carry_their_own_view_permission(): void
    {
        $this->register(CollapsibleModule::class);

        $items = $this->registry()->getAllNavItems();

        $byUrl = [];

        foreach ($items[0]['items'] as $subItem) {
            $byUrl[parse_url($subItem['url'], PHP_URL_PATH)] = $subItem;
        }

        // Entries the module curated keep its module-scoped permission, including
        // where it also declared a descriptor for the same screen.
        $this->assertSame('shop.manage', $byUrl['/dashboard/dashboard']['permission']);
        $this->assertSame('shop.manage', $byUrl['/dashboard/products']['permission']);

        // Generated entries carry the resource's own read permission, so someone
        // who can read a resource without full module access still sees it.
        $this->assertSame('shop.currencies.view', $byUrl['/dashboard/currencies']['permission']);
        $this->assertSame('shop.tax-rates.view', $byUrl['/dashboard/tax-rates']['permission']);
        // feature_slug comes from the owning provider's own slug(), so the frontend
        // can tell which module a link belongs to.
        $this->assertSame('collapsible-module', $byUrl['/dashboard/currencies']['feature_slug']);
    }

    public function test_a_descriptor_without_a_resolvable_index_route_stays_invisible(): void
    {
        $this->register(RoutelessModule::class);

        $items = $this->registry()->getAllNavItems();

        $this->assertCount(1, $items);
        $this->assertSame(['/dashboard/categories'], $this->subItemUrls($items[0]));
    }

    public function test_a_module_without_a_collapsible_group_falls_back_to_a_resources_heading(): void
    {
        $this->register(FlatModule::class);

        $items = $this->registry()->getAllNavItems();

        // FlatModule declares no nav items at all, so its descriptor surfaces as
        // the module's single top-level group.
        $this->assertCount(1, $items);
        $this->assertSame('Resources', $items[0]['groupLabel']);
        $this->assertSame('Currencies', $items[0]['title']);
        $this->assertSame(
            '/dashboard/currencies',
            parse_url($items[0]['url'], PHP_URL_PATH),
            'A provider with nothing to nest into still gets its descriptors, one group each.',
        );
    }

    public function test_every_top_level_nav_item_carries_an_icon(): void
    {
        $this->register(FlatModule::class);
        $this->register(DescriptorlessModule::class);

        $items = $this->registry()->getAllNavItems();

        $this->assertNotEmpty($items);

        foreach ($items as $item) {
            $this->assertArrayHasKey(
                'icon',
                $item,
                'A nav item without an icon renders as a blank row in the sidebar.',
            );
            $this->assertNotSame('', $item['icon']);
        }
    }

    public function test_a_provider_returning_several_collapsible_groups_is_left_alone(): void
    {
        $this->register(AmbiguousModule::class);

        $items = $this->registry()->getAllNavItems();

        $groupLabels = array_column($items, 'groupLabel');

        $this->assertContains(
            'Resources',
            $groupLabels,
            'With two candidate groups the registry must not guess which one owns the resources.',
        );
        $this->assertCount(3, $items, 'Two hand-written groups plus one flat descriptor item.');
    }

    public function test_a_module_with_no_descriptors_is_untouched(): void
    {
        $this->register(DescriptorlessModule::class);

        $items = $this->registry()->getAllNavItems();

        $this->assertCount(1, $items);
        $this->assertSame('Content', $items[0]['groupLabel']);
        $this->assertSame(['/dashboard/categories'], $this->subItemUrls($items[0]));
    }

    public function test_inline_group_label_beats_the_provider_default(): void
    {
        $this->register(InlineLabelModule::class);

        $items = $this->registry()->getAllNavItems();

        $this->assertSame('Content', $items[0]['groupLabel']);
        $this->assertSame(
            ['/dashboard/categories', '/dashboard/currencies'],
            $this->subItemUrls($items[0]),
            'An inline heading must still adopt the module descriptors.',
        );
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    private function subItemUrls(array $item): array
    {
        return array_values(array_map(
            fn (array $subItem) => parse_url($subItem['url'], PHP_URL_PATH),
            $item['items'] ?? [],
        ));
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
 * The Shop shape: one collapsible group, a hand-written Products link that a
 * descriptor also declares, and two descriptors the module never wrote by hand.
 */
final class CollapsibleModule implements FeatureProvider
{
    use InteractsWithFeatures;

    public function name(): string
    {
        return 'Collapsible module';
    }

    public function slug(): string
    {
        return 'collapsible-module';
    }

    public function getNavGroupLabel(): ?string
    {
        return 'Manage Shop';
    }

    public function getNavItems(): array
    {
        return [
            [
                'title' => 'Shop',
                'url' => '/dashboard/dashboard',
                'permission' => 'shop.manage',
                'items' => [
                    ['title' => 'Dashboard', 'url' => '/dashboard/dashboard', 'permission' => 'shop.manage'],
                    ['title' => 'All Products', 'url' => '/dashboard/products', 'permission' => 'shop.manage'],
                ],
            ],
        ];
    }

    public function getAdminResources(): array
    {
        return [
            new ResourceDefinition(
                key: 'products',
                label: 'Products',
                table: ['columns' => [['name' => 'name', 'header' => 'Name']]],
                routes: ['index' => 'dashboard.products.index'],
                permissions: ['view' => 'shop.products.view'],
            ),
            new ResourceDefinition(
                key: 'currencies',
                label: 'Currencies',
                table: ['columns' => [['name' => 'name', 'header' => 'Name']]],
                routes: ['index' => 'dashboard.currencies.index'],
                permissions: ['view' => 'shop.currencies.view'],
            ),
            new ResourceDefinition(
                key: 'tax-rates',
                label: 'Tax Rates',
                table: ['columns' => [['name' => 'name', 'header' => 'Name']]],
                routes: ['index' => 'dashboard.tax-rates.index'],
                permissions: ['view' => 'shop.tax-rates.view'],
            ),
        ];
    }
}

/**
 * A provider with no collapsible entry: descriptors still need to render, so the
 * flat `Resources` fallback stays as the contract for that case.
 */
final class FlatModule implements FeatureProvider
{
    use InteractsWithFeatures;

    public function name(): string
    {
        return 'Flat module';
    }

    public function slug(): string
    {
        return 'flat-module';
    }

    public function getNavItems(): array
    {
        return [];
    }

    public function getAdminResources(): array
    {
        return [
            new ResourceDefinition(
                key: 'currencies',
                label: 'Currencies',
                table: ['columns' => [['name' => 'name', 'header' => 'Name']]],
                routes: ['index' => 'dashboard.currencies.index'],
            ),
        ];
    }
}

/**
 * Two collapsible groups is genuinely ambiguous, so the registry must not pick
 * one and hide the descriptors inside it.
 */
final class AmbiguousModule implements FeatureProvider
{
    use InteractsWithFeatures;

    public function name(): string
    {
        return 'Ambiguous module';
    }

    public function slug(): string
    {
        return 'ambiguous-module';
    }

    public function getNavItems(): array
    {
        return [
            [
                'title' => 'Selling',
                'url' => '/dashboard/dashboard',
                'groupLabel' => 'Selling',
                'items' => [['title' => 'Dashboard', 'url' => '/dashboard/dashboard']],
            ],
            [
                'title' => 'Settings',
                'url' => '/dashboard/settings',
                'groupLabel' => 'Selling',
                'items' => [['title' => 'Settings', 'url' => '/dashboard/settings']],
            ],
        ];
    }

    public function getAdminResources(): array
    {
        return [
            new ResourceDefinition(
                key: 'currencies',
                label: 'Currencies',
                table: ['columns' => [['name' => 'name', 'header' => 'Name']]],
                routes: ['index' => 'dashboard.currencies.index'],
            ),
        ];
    }
}

/**
 * A descriptor awaiting its routes stays invisible until they exist.
 */
final class RoutelessModule implements FeatureProvider
{
    use InteractsWithFeatures;

    public function name(): string
    {
        return 'Routeless module';
    }

    public function slug(): string
    {
        return 'routeless-module';
    }

    public function getNavItems(): array
    {
        return [
            [
                'title' => 'Catalog',
                'url' => '/dashboard/categories',
                'items' => [['title' => 'Categories', 'url' => '/dashboard/categories']],
            ],
        ];
    }

    public function getAdminResources(): array
    {
        return [
            new ResourceDefinition(
                key: 'tax-rates',
                label: 'Tax Rates',
                table: ['columns' => [['name' => 'name', 'header' => 'Name']]],
                // Declared ahead of its routes: the descriptor must stay invisible
                // rather than render a link to nothing.
                routes: ['index' => 'dashboard.tax-rates.index.pending'],
            ),
        ];
    }
}

/**
 * Blog's shape: a curated group and no descriptors at all.
 */
final class DescriptorlessModule implements FeatureProvider
{
    use InteractsWithFeatures;

    public function name(): string
    {
        return 'Descriptorless module';
    }

    public function slug(): string
    {
        return 'descriptorless-module';
    }

    public function getNavItems(): array
    {
        return [
            [
                'title' => 'Posts',
                'url' => '/dashboard/categories',
                'groupLabel' => 'Content',
                'items' => [['title' => 'Categories', 'url' => '/dashboard/categories']],
            ],
        ];
    }
}

/**
 * An item-level groupLabel is the more specific declaration and must survive the
 * provider default, as Blog relies on.
 */
final class InlineLabelModule implements FeatureProvider
{
    use InteractsWithFeatures;

    public function name(): string
    {
        return 'Inline label module';
    }

    public function slug(): string
    {
        return 'inline-label-module';
    }

    public function getNavGroupLabel(): ?string
    {
        return 'Ignore me';
    }

    public function getNavItems(): array
    {
        return [
            [
                'title' => 'Posts',
                'url' => '/dashboard/categories',
                'groupLabel' => 'Content',
                'items' => [['title' => 'Categories', 'url' => '/dashboard/categories']],
            ],
        ];
    }

    public function getAdminResources(): array
    {
        return [
            new ResourceDefinition(
                key: 'currencies',
                label: 'Currencies',
                table: ['columns' => [['name' => 'name', 'header' => 'Name']]],
                routes: ['index' => 'dashboard.currencies.index'],
            ),
        ];
    }
}
