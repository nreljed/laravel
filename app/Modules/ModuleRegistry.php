<?php

namespace App\Modules;

use App\Models\Module;
use App\Modules\Contracts\ModuleInterface;
use Illuminate\Support\Collection;

class ModuleRegistry
{
    protected Collection $modules;

    protected Collection $booted;

    public function __construct()
    {
        $this->modules = new Collection;
        $this->booted = new Collection;
    }

    public static function cleanupUserData(int $userId): void
    {
        $instance = app(static::class);

        foreach ($instance->all() as $module) {
            $module->onUserDelete($userId);
        }
    }

    public function register(ModuleInterface $module): void
    {
        $this->modules->put($module->slug(), $module);
    }

    public function get(string $slug): ?ModuleInterface
    {
        return $this->modules->get($slug);
    }

    public function all(): Collection
    {
        return $this->modules;
    }

    public function allEnabled(): Collection
    {
        $enabledSlugs = Module::enabled()->pluck('slug')->toArray();

        return $this->modules->filter(fn (ModuleInterface $module) => in_array($module->slug(), $enabledSlugs));
    }

    public function isEnabled(string $slug): bool
    {
        $module = Module::where('slug', $slug)->first();

        return $module && $module->enabled;
    }

    public function bootEnabled(): void
    {
        $enabled = $this->allEnabled();

        $sorted = $this->resolveDependencyOrder($enabled);

        foreach ($sorted as $module) {
            $module->boot();
            $this->booted->put($module->slug(), $module);
        }
    }

    public function registerRoutes(): void
    {
        foreach ($this->booted as $module) {
            $module->routes();
        }
    }

    public function getAllNavItems(): array
    {
        $items = [];

        foreach ($this->booted as $module) {
            $nav = $module->getNavItems();
            if (! empty($nav)) {
                $nav['groupLabel'] = $module->getNavGroupLabel();
                $items[] = $nav;
            }
        }

        return $items;
    }

    public function getAllDashboardData(): array
    {
        $data = [];

        foreach ($this->booted as $module) {
            $moduleData = $module->getDashboardData();
            if (! empty($moduleData)) {
                $data = array_merge($data, [$module->slug() => $moduleData]);
            }
        }

        return $data;
    }

    public function getAllMetaFormattingConfigs(): array
    {
        $configs = [];

        foreach ($this->booted as $module) {
            $config = $module->getMetaFormattingConfig();
            if (! empty($config)) {
                $configs[$module->slug()] = $config;
            }
        }

        return $configs;
    }

    public function getAllSharedData(): array
    {
        $data = [];

        foreach ($this->booted as $module) {
            $shared = $module->getSharedData();
            if (! empty($shared)) {
                $data = array_merge($data, $shared);
            }
        }

        return $data;
    }

    public function getSettingsRoutes(): array
    {
        $routes = [];

        foreach ($this->booted as $module) {
            $route = $module->getSettingsRoute();
            if ($route) {
                $routes[$module->slug()] = $route;
            }
        }

        return $routes;
    }

    public function getBooted(): Collection
    {
        return $this->booted;
    }

    public function getEnabledModuleSlugs(): array
    {
        return $this->allEnabled()->keys()->toArray();
    }

    public function getAllRepeaterConfigs(): array
    {
        $configs = [];

        foreach ($this->booted as $module) {
            $config = $module->getRepeaterConfig();
            if (! empty($config)) {
                $configs[$module->slug()] = $config;
            }
        }

        return $configs;
    }

    public function getAllGalleryConfigs(): array
    {
        $configs = [];

        foreach ($this->booted as $module) {
            $config = $module->getGalleryConfig();
            if (! empty($config)) {
                $configs[$module->slug()] = $config;
            }
        }

        return $configs;
    }

    public function getAllProfileFieldConfigs(): array
    {
        $configs = [];

        foreach ($this->booted as $module) {
            $config = $module->getProfileFieldsConfig();
            if (! empty($config)) {
                $configs[$module->slug()] = $config;
            }
        }

        return $configs;
    }

    public function getAllNotificationTypes(): array
    {
        $types = [];

        foreach ($this->booted as $module) {
            $moduleTypes = $module->getNotificationTypes();
            foreach ($moduleTypes as $key => $config) {
                $types[$key] = array_merge([
                    'group' => $module->name(),
                    'default_channels' => ['in_app'],
                    'default_enabled' => true,
                ], $config);
            }
        }

        return $types;
    }

    public function getAllEmailTemplates(): array
    {
        $templates = [];

        foreach ($this->booted as $module) {
            $moduleTemplates = $module->getEmailTemplates();
            if (! empty($moduleTemplates)) {
                $templates = array_merge($templates, $moduleTemplates);
            }
        }

        return $templates;
    }

    public function loadAllModuleData(): array
    {
        $navItems = [];
        $settingsRoutes = [];
        $enabledSlugs = [];

        foreach ($this->booted as $slug => $module) {
            $enabledSlugs[] = $slug;

            $nav = $module->getNavItems();
            if (! empty($nav)) {
                $navItems[] = $nav;
            }

            $route = $module->getSettingsRoute();
            if ($route) {
                $settingsRoutes[$slug] = $route;
            }
        }

        $moduleList = [];
        foreach ($this->modules as $slug => $module) {
            $moduleList[] = [
                'name' => $module->slug(),
                'displayName' => $module->name(),
                'description' => $module->description(),
                'version' => $module->version(),
                'enabled' => $this->isEnabled($slug),
                'dependencies' => $module->dependencies(),
            ];
        }

        return [
            'navItems' => $navItems,
            'settings' => $settingsRoutes,
            'list' => $moduleList,
        ];
    }

    public function getModuleList(): array
    {
        $list = [];

        foreach ($this->modules as $slug => $module) {
            $list[] = [
                'name' => $module->slug(),
                'displayName' => $module->name(),
                'description' => $module->description(),
                'version' => $module->version(),
                'enabled' => $this->isEnabled($slug),
                'dependencies' => $module->dependencies(),
            ];
        }

        return $list;
    }

    protected function resolveDependencyOrder(Collection $modules): Collection
    {
        $sorted = new Collection;
        $resolved = [];

        foreach ($modules as $module) {
            $this->resolveDependencies($module, $modules, $sorted, $resolved);
        }

        return $sorted;
    }

    protected function resolveDependencies(
        ModuleInterface $module,
        Collection $available,
        Collection $sorted,
        array &$resolved
    ): void {
        $slug = $module->slug();

        if (in_array($slug, $resolved)) {
            return;
        }

        foreach ($module->dependencies() as $dependency) {
            $depModule = $this->modules->get($dependency);

            if ($depModule && ! in_array($dependency, $resolved)) {
                $this->resolveDependencies($depModule, $available, $sorted, $resolved);
            }
        }

        $sorted->put($slug, $module);
        $resolved[] = $slug;
    }
}
