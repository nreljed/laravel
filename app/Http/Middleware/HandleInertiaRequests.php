<?php

namespace App\Http\Middleware;

use App\Http\Controllers\EmailSettingsController;
use App\Models\Notification;
use App\Models\User;
use App\Services\EmailSettingsService;
use App\Services\FeatureRegistry;
use App\Services\NotificationService;
use App\Services\SettingsService;
use App\Traits\RelativeTime;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    use RelativeTime;

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
                    get_class($request->user())
                )
                : 0,
            'userCapabilities' => fn () => $request->user()
                ? $this->getUserCapabilities($request->user())
                : [],
            'userMode' => fn () => $request->user()
                ? ($request->session()->get('user_mode', 'guest'))
                : 'guest',
            'canSwitchMode' => fn () => false,
            'formattingSettings' => function () {
                try {
                    return SettingsService::getFormattingDefaults();
                } catch (\Throwable) {
                    return [];
                }
            },
            'featureNavItems' => fn () => $this->getFeatureNavItems(),
            'featureData' => fn () => $this->getFeatureSharedData(),
            'emailSettings' => fn () => $this->getEmailSettings(),
            'emailTemplates' => fn () => $this->getEmailTemplates(),
            'brand' => [
                'logo' => fn () => self::safeSetting('app_logo', ''),
                'favicon' => fn () => self::safeSetting('app_favicon', ''),
                'name' => fn () => self::safeSetting('app_name', 'Cost Breaker 360'),
            ],
            'seo' => [
                'title' => fn () => self::safeSetting('app_name', 'Cost Breaker 360'),
                'description' => fn () => self::safeSetting('app_description', ''),
                'og_image' => fn () => self::safeSetting('app_logo', ''),
            ],
            'app_url' => fn () => self::safeSetting('app_url', config('app.url')),
            'registration' => [
                'enabled' => fn () => self::safeSetting('enable_registration', '1') === '1',
                'require_email_verification' => fn () => self::safeSetting('require_email_verification', '0') === '1',
            ],
            'wishlistIds' => fn () => collect(),
            'wishlistResult' => fn () => null,
            'impersonating' => fn () => $request->session()->get('impersonating', false),
            'impersonator' => fn () => $request->session()->has('impersonator_id')
                ? User::find($request->session()->get('impersonator_id'))?->only(['id', 'name', 'email'])
                : null,
            'activeCurrencies' => fn () => $this->getActiveCurrencies(),
            'selectedCurrency' => fn () => session('selected_currency', 'EUR'),
        ];
    }

    protected function getFeatureNavItems(): array
    {
        try {
            return app(FeatureRegistry::class)->getAllNavItems();
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function getFeatureSharedData(): array
    {
        try {
            return app(FeatureRegistry::class)->getAllSharedData();
        } catch (\Throwable $e) {
            return [];
        }
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
                'manageUsers' => $user->can('manage-users'),
                'manageSettings' => $user->can('manage-settings'),
                'manageRoles' => $user->can('manage-roles'),
                'viewSystemHealth' => $user->can('view-system-health'),
                'viewDashboard' => $user->can('view-dashboard'),
                'viewProfile' => $user->can('view-profile'),
                'editProfile' => $user->can('edit-profile'),
                'deleteAccount' => $user->can('delete-account'),
                'viewNotifications' => $user->can('view-notifications'),
                'manageNotifications' => $user->can('manage-notifications'),
                'postsView' => $user->can('posts.view'),
                'postsCreate' => $user->can('posts.create'),
                'postsEdit' => $user->can('posts.edit'),
                'postsDelete' => $user->can('posts.delete'),
            ],
        ];

        return $caps;
    }

    protected function getActiveCurrencies(): array
    {
        return [['code' => 'EUR', 'name' => 'Euro', 'symbol' => "\u{20ac}", 'is_default' => true]];
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

        $displayNameOptions = $user->getDisplayNameOptions();

        $phoneData = $user->phone ?? [];
        $socialData = $user->social_links ?? [];

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
            'contact_email' => $user->contact_email ?? '',
            'user_registered' => $user->created_at?->toISOString(),
            'roles' => $user->roles->pluck('slug')->toArray(),
            'role' => $roleSlug,
            'profile_data' => [
                'properties' => [
                    'phone_numbers' => $phoneData,
                    'social_media' => $socialData,
                ],
            ],
        ];
    }
}
