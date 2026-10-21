<?php

namespace Nreljed\MarketingModule;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Nreljed\MarketingModule\Models\Contact;
use Nreljed\MarketingModule\Models\Segment;
use Nreljed\MarketingModule\Models\SocialAccount;
use Nreljed\MarketingModule\Models\Tag;
use Nreljed\MarketingModule\Policies\ContactPolicy;
use Nreljed\MarketingModule\Policies\SegmentPolicy;
use Nreljed\MarketingModule\Policies\SocialAccountPolicy;
use Nreljed\MarketingModule\Policies\TagPolicy;
use Nreljed\MarketingModule\Resources\ContactResource;
use Nreljed\MarketingModule\Resources\SegmentResource;
use Nreljed\MarketingModule\Resources\SocialAccountResource;
use Nreljed\MarketingModule\Resources\TagResource;
use Nreljed\MarketingModule\Services\SocialAuthService;
use Nreljed\MarketingModule\Services\SocialChannelRegistry;
use Nreljed\MarketingModule\Services\TokenVault;
use Nreljed\Platform\Concerns\InteractsWithFeatures;
use Nreljed\Platform\Contracts\FeatureProvider;
use Nreljed\Platform\Models\User;
use SocialiteProviders\Instagram\InstagramExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Pinterest\PinterestExtendSocialite;
use Throwable;

/**
 * The Marketing feature: social accounts, the audience, and — from the next
 * phase — scheduled publishing, campaigns and workflow automations.
 *
 * The provider owns four things a consumer never has to wire by hand: the
 * routes, the policies, the OAuth driver extensions, and the declarative
 * resources the kernel's admin pages render.
 */
class MarketingServiceProvider extends ServiceProvider implements FeatureProvider
{
    use InteractsWithFeatures;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/marketing.php', 'marketing');

        $this->app->singleton(TokenVault::class);
        $this->app->singleton(
            SocialChannelRegistry::class,
            fn () => new SocialChannelRegistry((array) config('marketing.channels', [])),
        );
        $this->app->singleton(SocialAuthService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->registerPolicies();
        $this->registerSocialiteExtensions();

        if ($this->enabled()) {
            $this->loadRoutes();
        }
    }

    /**
     * The marketing routes.
     *
     * There is no blanket `permission:` middleware on the group. Marketing
     * grants are per area — accounts, contacts, tags, segments — and a single
     * group gate would either lock out a legitimate user or hand a user a
     * sidebar link that 403s. Every action authorises itself through its
     * policy, and the sidebar item for each resource carries the same
     * permission the policy checks.
     */
    protected function loadRoutes(): void
    {
        Route::middleware(['web', 'auth', 'verified'])
            ->prefix('/dashboard/marketing')
            ->name('dashboard.marketing.')
            ->group(__DIR__.'/Routes/web.php');
    }

    protected function registerPolicies(): void
    {
        Gate::policy(SocialAccount::class, SocialAccountPolicy::class);
        Gate::policy(Contact::class, ContactPolicy::class);
        Gate::policy(Tag::class, TagPolicy::class);
        Gate::policy(Segment::class, SegmentPolicy::class);
    }

    /**
     * Extend Socialite with the drivers Socialite does not ship.
     *
     * Instagram and Pinterest come from the SocialiteProviders family, which
     * registers a driver by listening for the manager's `SocialiteWasCalled`
     * event. The manager fires it once the application has booted, so the
     * listeners registered here are in place before it does.
     */
    protected function registerSocialiteExtensions(): void
    {
        if (! class_exists(SocialiteWasCalled::class)) {
            return;
        }

        $events = $this->app['events'];

        if (class_exists(InstagramExtendSocialite::class)) {
            $events->listen(SocialiteWasCalled::class, [InstagramExtendSocialite::class, 'handle']);
        }

        if (class_exists(PinterestExtendSocialite::class)) {
            $events->listen(SocialiteWasCalled::class, [PinterestExtendSocialite::class, 'handle']);
        }
    }

    public function name(): string
    {
        return 'Marketing';
    }

    public function slug(): string
    {
        return 'marketing';
    }

    public function description(): ?string
    {
        return 'Connect social accounts, manage your audience, and publish to every platform from one place.';
    }

    public function getComposerPackage(): ?string
    {
        return 'nreljed/marketing-module';
    }

    public function permissions(): array
    {
        return [
            [
                'slug' => 'marketing.accounts.manage',
                'name' => 'Manage Social Accounts',
                'description' => 'Connect, edit and disconnect the social accounts you publish to.',
            ],
            [
                'slug' => 'marketing.contacts.manage',
                'name' => 'Manage Contacts',
                'description' => 'Create, edit and delete the contacts in your audience.',
            ],
            [
                'slug' => 'marketing.tags.manage',
                'name' => 'Manage Tags',
                'description' => 'Create, edit and delete the tags that group your audience.',
            ],
            [
                'slug' => 'marketing.segments.manage',
                'name' => 'Manage Segments',
                'description' => 'Create, edit and delete the segments that target your audience.',
            ],
        ];
    }

    public function getNavGroupLabel(): ?string
    {
        return 'Marketing';
    }

    public function getNavItems(): array
    {
        $user = auth()->user();

        // A user who cannot act in the module gets no group at all, so the
        // sidebar never shows an empty "Marketing" heading. A signed-out
        // context still returns the group: the registry needs the shape to
        // nest the descriptors into, and the sidebar is never rendered without
        // a session.
        if ($user !== null && ! $this->canView($user)) {
            return [];
        }

        try {
            // Only links a descriptor cannot express belong here. The four
            // resources (accounts, contacts, tags, segments) are appended by
            // the platform registry into this same group, each carrying its
            // own read permission.
            return [[
                'title' => 'Marketing',
                'url' => route('dashboard.marketing.dashboard', [], false),
                'icon' => 'Megaphone',
                'permission' => null,
                'module_slug' => 'Marketing',
                'showModeBadge' => false,
                'items' => [
                    ['title' => 'Dashboard', 'url' => route('dashboard.marketing.dashboard', [], false)],
                ],
            ]];
        } catch (Throwable) {
            return [];
        }
    }

    public function getDashboardData(): array
    {
        if (! $this->tablesExist()) {
            return [];
        }

        return [
            'label' => 'Marketing',
            'icon' => 'Megaphone',
            'total' => SocialAccount::query()->count(),
            'contacts' => Contact::query()->count(),
            'segments' => Segment::query()->count(),
        ];
    }

    public function getAdminResources(): array
    {
        return [
            SocialAccountResource::definition(),
            ContactResource::definition(),
            TagResource::definition(),
            SegmentResource::definition(),
        ];
    }

    public function getHealthChecks(): array
    {
        try {
            return [
                'social_accounts_table' => Schema::hasTable('social_accounts'),
                'contacts_table' => Schema::hasTable('contacts'),
                'tags_table' => Schema::hasTable('tags'),
                'segments_table' => Schema::hasTable('segments'),
            ];
        } catch (Throwable) {
            return [];
        }
    }

    public function getScheduledTasks(): array
    {
        // Publishing runs on the scheduler from the next phase; Phase 1 has
        // no recurring work of its own.
        return [];
    }

    public function getMediaSubdirectory(): ?string
    {
        return (string) config('marketing.media.subdirectory', 'social');
    }

    public function onUserDelete(int $userId): void
    {
        // A social account holds credentials the connected user granted.
        // Leaving them behind after the account is deleted would keep a live
        // grant nobody can revoke.
        SocialAccount::query()->where('user_id', $userId)->delete();
    }

    public function getDeactivationNotice(): ?string
    {
        return 'Marketing is off. Connected social accounts keep their credentials but nothing can be published until it is switched back on.';
    }

    /**
     * Any marketing permission is enough to open the section. The sidebar
     * item and the page must agree, or the user gets a link that 403s.
     */
    protected function canView(User $user): bool
    {
        foreach (['accounts', 'contacts', 'tags', 'segments'] as $area) {
            if ($user->hasPermission("marketing.{$area}.manage")) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the module's tables are present. A fresh install that has not
     * migrated yet must not break the host dashboard.
     */
    protected function tablesExist(): bool
    {
        try {
            return Schema::hasTable('social_accounts')
                && Schema::hasTable('contacts')
                && Schema::hasTable('tags')
                && Schema::hasTable('segments');
        } catch (Throwable) {
            return false;
        }
    }
}
