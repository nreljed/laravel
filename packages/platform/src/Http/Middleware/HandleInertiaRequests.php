<?php

namespace Nreljed\Platform\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\AlwaysProp;
use Inertia\Inertia;
use Inertia\Middleware;
use Nreljed\Platform\Models\Notification;
use Nreljed\Platform\Models\User;
use Nreljed\Platform\Services\DatabaseMaintenanceService;
use Nreljed\Platform\Services\FeatureRegistry;
use Nreljed\Platform\Services\NotificationService;
use Nreljed\Platform\Services\PermissionCacheService;
use Nreljed\Platform\Services\SettingsService;
use Nreljed\Platform\Support\RoleCatalog;
use Nreljed\Platform\Traits\RelativeTime;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    use RelativeTime;

    protected $rootView = 'app';

    #[\Override]
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    #[\Override]
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
                ? $request->user()->notifications()->latest()->take(5)->get()->map(fn (Notification $n) => [
                    'id' => $n->id,
                    'type' => $n->type,
                    'title' => $n->title ?? $n->data['title'] ?? '',
                    'message' => $n->message ?? $n->data['message'] ?? $n->data['body'] ?? '',
                    'link' => $n->link ?? $n->data['link'] ?? $n->data['url'] ?? '',
                    'severity' => $n->severity ?? 'info',
                    'module' => $n->module ?? 'core',
                    'is_read' => (bool) $n->read_at,
                    'data' => $n->data,
                    'read_at' => $n->read_at?->toISOString(),
                    'created_at' => $n->created_at->toISOString(),
                    'created_at_relative' => self::relativeTime($n->created_at),
                ])
                : [],
            'notificationUnreadCount' => fn () => $request->user()
                ? app(NotificationService::class)->getCachedUnreadCount(
                    $request->user()->id,
                    $request->user()::class
                )
                : 0,
            'userCapabilities' => fn () => $request->user()
                ? $this->getUserCapabilities($request->user())
                : [],
            'formattingSettings' => function () {
                try {
                    return SettingsService::getFormattingDefaults();
                } catch (\Throwable) {
                    return [];
                }
            },
            'featureNavItems' => $this->getFeatureNavItems(...),
            'sharedData' => new AlwaysProp($this->getFeatureSharedData(...)),
            'repeaterConfigs' => $this->getFeatureRepeaterConfigs(...),
            'profileSections' => $this->getFeatureProfileSections(...),
            'profileFieldConfigs' => $this->getFeatureProfileFieldConfigs(...),
            'settingsRoutes' => $this->getFeatureSettingsRoutes(...),
            'featureSettingsNav' => $this->getFeatureSettingsNav(...),
            'features' => [
                'databaseMaintenanceEnabled' => function () {
                    try {
                        return app(DatabaseMaintenanceService::class)->isEnabled();
                    } catch (\Throwable) {
                        return (bool) config('database_maintenance.enabled', true);
                    }
                },
            ],
            'modules' => Inertia::optional($this->getModules(...)),
            'brand' => [
                'logo' => fn () => self::safeSetting('app_logo', ''),
                'favicon' => fn () => self::safeSetting('app_favicon', ''),
                'name' => fn () => self::safeSetting('app_name', config('app.name', '')),
            ],
            'seo' => [
                'title' => fn () => self::safeSetting('app_name', config('app.name', '')),
                'description' => fn () => self::safeSetting('app_description', ''),
                'og_image' => fn () => self::safeSetting('app_logo', ''),
            ],
            'app_url' => fn () => self::safeSetting('app_url', config('app.url')),
            'ziggy' => function () {
                try {
                    return (new Ziggy)->toArray();
                } catch (\Throwable) {
                    return [];
                }
            },
            'registration' => [
                'enabled' => fn () => self::safeSetting('enable_registration', '1') === '1',
                'require_email_verification' => fn () => self::safeSetting('require_email_verification', '0') === '1',
            ],
            'impersonating' => fn () => $request->session()->get('impersonating', false),
            'impersonator' => fn () => $request->session()->has('impersonator_id')
                ? User::find($request->session()->get('impersonator_id'))?->only(['id', 'name', 'email'])
                : null,
        ];
    }

    protected function getFeatureNavItems(): array
    {
        try {
            return app(FeatureRegistry::class)->getAllNavItems();
        } catch (\Throwable) {
            return [];
        }
    }

    protected function getFeatureSharedData(): array
    {
        try {
            return app(FeatureRegistry::class)->getAllSharedData();
        } catch (\Throwable) {
            return [];
        }
    }

    protected function getFeatureRepeaterConfigs(): array
    {
        try {
            return app(FeatureRegistry::class)->getAllRepeaterConfigs();
        } catch (\Throwable) {
            return [];
        }
    }

    protected function getFeatureProfileSections(): array
    {
        try {
            return app(FeatureRegistry::class)->getAllProfileSections();
        } catch (\Throwable) {
            return [];
        }
    }

    protected function getFeatureProfileFieldConfigs(): array
    {
        try {
            return app(FeatureRegistry::class)->getAllProfileFieldConfigs();
        } catch (\Throwable) {
            return [];
        }
    }

    protected function getFeatureSettingsRoutes(): array
    {
        try {
            return app(FeatureRegistry::class)->getAllSettingsRoutes();
        } catch (\Throwable) {
            return [];
        }
    }

    protected function getFeatureSettingsNav(): array
    {
        try {
            return app(FeatureRegistry::class)->getAllSettingsNav();
        } catch (\Throwable) {
            return [];
        }
    }

    protected function getModules(): array
    {
        try {
            return app(FeatureRegistry::class)->getAll();
        } catch (\Throwable) {
            return [];
        }
    }

    private function getUserCapabilities($user): array
    {
        $slugs = [];

        try {
            $slugs = collect(RoleCatalog::permissions())->pluck('slug')->all();
        } catch (\Throwable) {
        }

        $allSlugs = array_values(array_unique($slugs));
        $userSlugs = app(PermissionCacheService::class)->getUserPermissionSlugs($user->id);

        $permissions = [];

        foreach ($allSlugs as $slug) {
            $permissions[$slug] = in_array($slug, $userSlugs, true);
        }

        return [
            'isAdmin' => $user->isAdmin(),
            'can' => $permissions,
        ];
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
        $roleSlug = $primaryRole->slug ?? '';

        $displayNameOptions = $user->getDisplayNameOptions();

        return [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'email' => $user->email,
            'user_email' => $user->email,
            'avatar' => $user->avatar_url,
            'custom_avatar_url' => $user->avatar_url,
            'display_name' => $user->display_name ?: $user->name,
            'display_name_options' => $displayNameOptions,
            'user_login' => $user->username,
            'first_name' => $user->first_name ?? '',
            'last_name' => $user->last_name ?? '',
            'url' => $user->url ?? '',
            'bio' => $user->bio ?? '',
            'user_registered' => $user->created_at?->toISOString(),
            'roles' => $user->roles->pluck('slug')->toArray(),
            'role' => $roleSlug,
        ];
    }
}
