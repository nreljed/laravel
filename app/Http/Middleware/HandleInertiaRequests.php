<?php

namespace App\Http\Middleware;

use App\Http\Controllers\EmailSettingsController;
use App\Modules\ModuleRegistry;
use App\Services\EmailSettingsService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'flash' => function () use ($request) {
                if (! $request->hasSession()) {
                    return [];
                }

                $flash = [];

                if ($request->session()->has('flash')) {
                    $flash = array_merge($flash, $request->session()->get('flash', []));
                }

                if ($request->session()->has('status')) {
                    $flash['status'] = $request->session()->get('status');
                }

                if ($request->session()->has('error')) {
                    $flash['error'] = $request->session()->get('error');
                }

                if ($request->session()->has('mail_debug')) {
                    $flash['mail_debug'] = $request->session()->get('mail_debug');
                }

                return $flash;
            },
            'csp_nonce' => fn () => $request->attributes->get('csp_nonce'),
            'auth' => [
                'user' => fn () => $request->user()
                    ? $this->buildAuthUser($request->user())
                    : null,
            ],
            'latestNotifications' => fn () => $request->user()
                ? $request->user()->notifications()->latest()->take(5)->get()
                : [],
            'userCapabilities' => fn () => $request->user()
                ? $this->getUserCapabilities($request->user())
                : [],
            'userMode' => fn () => $request->user()
                ? ($request->session()->get('user_mode', 'host'))
                : 'host',
            'canSwitchMode' => fn () => $request->user()
                ? $request->user()->hasPermission('bookings.view')
                : false,
            'formattingSettings' => function () {
                try {
                    return SettingsService::getFormattingDefaults();
                } catch (\Throwable) {
                    return [];
                }
            },
            'moduleNavItems' => fn () => $this->getModuleNavItems(),
            'moduleData' => fn () => $this->getModuleSharedData(),
            'modules' => fn () => $this->getModulesData(),
            'repeaterConfigs' => fn () => $this->getRepeaterConfigs(),
            'profileFieldConfigs' => fn () => $this->getProfileFieldConfigs(),
            'emailSettings' => fn () => $this->getEmailSettings(),
            'emailTemplates' => fn () => $this->getEmailTemplates(),
            'brand' => [
                'logo' => fn () => self::safeSetting('app_logo', ''),
                'name' => fn () => self::safeSetting('app_name', 'Cost Breaker 360'),
            ],
            'app_url' => fn () => self::safeSetting('app_url', config('app.url')),
            'notificationUnreadCount' => fn () => $request->user()
                ? $request->user()->unreadNotifications()->count()
                : 0,
            'debug' => fn () => config('app.debug', false),
            'moduleDebugData' => fn () => $this->getModuleDebugData(),
        ];
    }

    protected function getModuleNavItems(): array
    {
        if (! app()->resolved(ModuleRegistry::class)) {
            return [];
        }

        return app(ModuleRegistry::class)->getAllNavItems();
    }

    protected function getModuleSharedData(): array
    {
        if (! app()->resolved(ModuleRegistry::class)) {
            return [];
        }

        return app(ModuleRegistry::class)->getAllSharedData();
    }

    protected function getModulesData(): array
    {
        if (! app()->resolved(ModuleRegistry::class)) {
            return ['enabled' => [], 'list' => []];
        }

        $registry = app(ModuleRegistry::class);

        return [
            'enabled' => $registry->getEnabledModuleSlugs(),
            'list' => $registry->getModuleList(),
        ];
    }

    protected function getRepeaterConfigs(): array
    {
        if (! app()->resolved(ModuleRegistry::class)) {
            return [];
        }

        return app(ModuleRegistry::class)->getAllRepeaterConfigs();
    }

    protected function getProfileFieldConfigs(): array
    {
        if (! app()->resolved(ModuleRegistry::class)) {
            return [];
        }

        return app(ModuleRegistry::class)->getAllProfileFieldConfigs();
    }

    protected function getEmailSettings(): array
    {
        try {
            return app(EmailSettingsService::class)->forFrontend();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getUserCapabilities($user): array
    {
        $caps = [
            'isAdmin' => $user->isAdmin(),
            'can' => [
                'manageUsers' => $user->hasPermission('manage-users'),
                'manageSettings' => $user->hasPermission('manage-settings'),
                'manageRoles' => $user->hasPermission('manage-roles'),
                'viewSystemHealth' => $user->hasPermission('view-system-health'),
                'viewDashboard' => $user->hasPermission('view-dashboard'),
                'viewProfile' => $user->hasPermission('view-profile'),
                'editProfile' => $user->hasPermission('edit-profile'),
                'deleteAccount' => $user->hasPermission('delete-account'),
                'viewNotifications' => $user->hasPermission('view-notifications'),
                'manageNotifications' => $user->hasPermission('manage-notifications'),
            ],
        ];

        if (app()->resolved(ModuleRegistry::class)) {
            $registry = app(ModuleRegistry::class);
            foreach ($registry->all() as $slug => $module) {
                foreach ($module->permissions() as $perm) {
                    $caps[$perm['slug']] = $user->hasPermission($perm['slug']);
                }
                $caps['hasModule_'.$slug] = $this->isModuleEnabled($slug);
            }
        }

        return $caps;
    }

    private function isModuleEnabled(string $slug): bool
    {
        if (! app()->resolved(ModuleRegistry::class)) {
            return false;
        }

        return app(ModuleRegistry::class)->isEnabled($slug);
    }

    protected function getModuleDebugData(): array
    {
        if (! app()->resolved(ModuleRegistry::class)) {
            return [];
        }

        $registry = app(ModuleRegistry::class);
        $data = [];

        foreach ($registry->all() as $slug => $module) {
            try {
                $nav = $module->getNavItems();
            } catch (\Throwable $e) {
                $nav = [];
            }

            $navUrls = [];

            if (! empty($nav)) {
                $navUrls['parent'] = [
                    'title' => $nav['title'] ?? '',
                    'url' => $nav['url'] ?? null,
                ];
                foreach ($nav['items'] ?? [] as $sub) {
                    $navUrls['children'][] = [
                        'title' => $sub['title'] ?? '',
                        'url' => $sub['url'] ?? null,
                    ];
                }
            }

            $data[] = [
                'name' => $module->name(),
                'slug' => $slug,
                'enabled' => $registry->isEnabled($slug),
                'booted' => $registry->getBooted()->has($slug),
                'nav' => $navUrls,
                'dependencies' => $module->dependencies(),
            ];
        }

        return $data;
    }

    protected function getEmailTemplates(): array
    {
        try {
            return app(EmailSettingsController::class)->getTemplates();
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected static function safeSetting(string $key, mixed $default = null): mixed
    {
        try {
            return SettingsService::get($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }

    private function buildAuthUser($user): array
    {
        $primaryRole = $user->roles->first();
        $roleSlug = $primaryRole?->slug ?? '';

        $displayNameOptions = $this->buildDisplayNameOptions($user);

        $phoneData = $user->phone
            ? (is_string($user->phone) ? json_decode($user->phone, true) : $user->phone)
            : [];

        $socialData = $user->social_links
            ? (is_string($user->social_links) ? json_decode($user->social_links, true) : $user->social_links)
            : [];

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'user_email' => $user->email,
            'avatar' => $user->avatar_url,
            'custom_avatar_url' => $user->avatar_url,
            'display_name' => $user->display_name ?: $user->name,
            'display_name_options' => $displayNameOptions,
            'user_login' => $user->name,
            'first_name' => $user->first_name ?? '',
            'last_name' => $user->last_name ?? '',
            'nickname' => $user->nickname ?? '',
            'url' => $user->url ?? '',
            'website' => $user->url ?? '',
            'description' => $user->bio ?? '',
            'user_registered' => $user->created_at?->toISOString(),
            'roles' => $user->roles->pluck('slug')->toArray(),
            'role' => $roleSlug,
            'module_profile' => [
                'properties' => [
                    'phone_numbers' => $phoneData,
                    'social_media' => $socialData,
                ],
            ],
        ];
    }

    private function buildDisplayNameOptions($user): array
    {
        $options = [];
        $username = $user->name;
        $firstName = $user->first_name ?? '';
        $lastName = $user->last_name ?? '';
        $nickname = $user->nickname ?? '';

        $options['username'] = $username;

        if (! empty($firstName)) {
            $options['firstname'] = $firstName;
        }
        if (! empty($lastName)) {
            $options['lastname'] = $lastName;
        }
        if (! empty($firstName) && ! empty($lastName)) {
            $options['firstlast'] = $firstName.' '.$lastName;
            $options['lastfirst'] = $lastName.' '.$firstName;
        }
        if (! empty($nickname)) {
            $options['nickname'] = $nickname;
        }

        $displayName = $user->display_name ?: $user->name;
        if (! in_array($displayName, $options)) {
            $options['display'] = $displayName;
        }

        return $options;
    }
}
