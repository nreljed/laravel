<?php

namespace App\Http\Middleware;

use App\Http\Controllers\EmailSettingsController;
use App\Models\Notification;
use App\Models\User;
use App\Services\EmailSettingsService;
use App\Services\FeatureRegistry;
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
                ? $request->user()->notifications()->latest()->take(5)->get()->map(fn (Notification $n) => [
                    'id' => $n->id,
                    'type' => $n->type,
                    'title' => $n->title ?? $n->data['title'] ?? '',
                    'message' => $n->message ?? $n->data['message'] ?? $n->data['body'] ?? '',
                    'link' => $n->link ?? $n->data['link'] ?? $n->data['url'] ?? '',
                    'severity' => $n->severity ?? 'info',
                    'module' => $n->module ?? 'core',
                    'is_read' => $n->read_at ? '1' : '0',
                    'data' => $n->data,
                    'read_at' => $n->read_at?->toISOString(),
                    'created_at' => $n->created_at->toISOString(),
                    'created_at_relative' => self::relativeTime($n->created_at),
                ])
                : [],
            'notificationUnreadCount' => fn () => $request->user()
                ? Notification::where('notifiable_id', $request->user()->id)
                    ->where('notifiable_type', get_class($request->user()))
                    ->whereNull('read_at')
                    ->count()
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
            'moduleNavItems' => fn () => $this->getModuleNavItems(),
            'moduleData' => fn () => $this->getModuleSharedData(),
            'emailSettings' => fn () => $this->getEmailSettings(),
            'emailTemplates' => fn () => $this->getEmailTemplates(),
            'brand' => [
                'logo' => fn () => self::safeSetting('app_logo', ''),
                'name' => fn () => self::safeSetting('app_name', 'Cost Breaker 360'),
            ],
            'app_url' => fn () => self::safeSetting('app_url', config('app.url')),
            'wishlistIds' => fn () => collect(),
            'wishlistResult' => fn () => null,
            'impersonating' => fn () => $request->session()->get('impersonating', false),
            'impersonator' => fn () => $request->session()->has('impersonator_id')
                ? User::find($request->session()->get('impersonator_id'))?->only(['id', 'name', 'email'])
                : null,
            'debug' => fn () => config('app.debug', false),
            'activeCurrencies' => fn () => $this->getActiveCurrencies(),
            'selectedCurrency' => fn () => session('selected_currency', 'EUR'),
        ];
    }

    protected function getModuleNavItems(): array
    {
        try {
            return app(FeatureRegistry::class)->getAllNavItems();
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function getModuleSharedData(): array
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

    protected static function relativeTime(\DateTimeInterface $datetime): string
    {
        $diff = time() - $datetime->getTimestamp();

        if ($diff < 60) {
            return 'just now';
        }
        if ($diff < 3600) {
            return floor($diff / 60).'m ago';
        }
        if ($diff < 86400) {
            return floor($diff / 3600).'h ago';
        }
        if ($diff < 604800) {
            return floor($diff / 86400).'d ago';
        }

        return $datetime->format('M j');
    }

    private function buildAuthUser($user): array
    {
        $primaryRole = $user->roles->first();
        $roleSlug = $primaryRole?->slug ?? '';

        $displayNameOptions = $user->getDisplayNameOptions();

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
}
