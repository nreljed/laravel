<?php

namespace Nreljed\MarketingModule;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Nreljed\MarketingModule\Console\Commands\PublishDuePosts;
use Nreljed\MarketingModule\Console\Commands\SyncMarketingMetrics;
use Nreljed\MarketingModule\Enums\PostStatus;
use Nreljed\MarketingModule\Models\Campaign;
use Nreljed\MarketingModule\Models\Contact;
use Nreljed\MarketingModule\Models\Segment;
use Nreljed\MarketingModule\Models\SocialAccount;
use Nreljed\MarketingModule\Models\SocialPost;
use Nreljed\MarketingModule\Models\Tag;
use Nreljed\MarketingModule\Models\Workflow;
use Nreljed\MarketingModule\Policies\CampaignPolicy;
use Nreljed\MarketingModule\Policies\ContactPolicy;
use Nreljed\MarketingModule\Policies\SegmentPolicy;
use Nreljed\MarketingModule\Policies\SocialAccountPolicy;
use Nreljed\MarketingModule\Policies\SocialPostPolicy;
use Nreljed\MarketingModule\Policies\TagPolicy;
use Nreljed\MarketingModule\Policies\WorkflowPolicy;
use Nreljed\MarketingModule\Resources\CampaignResource;
use Nreljed\MarketingModule\Resources\ContactResource;
use Nreljed\MarketingModule\Resources\SegmentResource;
use Nreljed\MarketingModule\Resources\SocialAccountResource;
use Nreljed\MarketingModule\Resources\SocialPostResource;
use Nreljed\MarketingModule\Resources\TagResource;
use Nreljed\MarketingModule\Resources\WorkflowResource;
use Nreljed\MarketingModule\Services\AccountTokenRefresher;
use Nreljed\MarketingModule\Services\MarketingAnalytics;
use Nreljed\MarketingModule\Services\MetricNormalizer;
use Nreljed\MarketingModule\Services\PostPublisher;
use Nreljed\MarketingModule\Services\SocialAuthService;
use Nreljed\MarketingModule\Services\SocialChannelRegistry;
use Nreljed\MarketingModule\Services\SocialMetricSync;
use Nreljed\MarketingModule\Services\TokenVault;
use Nreljed\MarketingModule\Services\TrackedLinkService;
use Nreljed\MarketingModule\Services\WorkflowEngine;
use Nreljed\Platform\Concerns\InteractsWithFeatures;
use Nreljed\Platform\Contracts\FeatureProvider;
use Nreljed\Platform\Models\User;
use SocialiteProviders\Instagram\InstagramExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Pinterest\PinterestExtendSocialite;
use Throwable;

/**
 * The Marketing feature: social accounts, the audience, the publishing
 * pipeline (scheduled posts with per-account deliveries, retries and
 * approvals), campaigns, workflow automations and delivery analytics.
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
        $this->app->singleton(AccountTokenRefresher::class);
        $this->app->singleton(MetricNormalizer::class);
        $this->app->singleton(PostPublisher::class);
        $this->app->singleton(SocialMetricSync::class);
        $this->app->singleton(TrackedLinkService::class);
        $this->app->singleton(WorkflowEngine::class);
        $this->app->singleton(MarketingAnalytics::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->registerPolicies();
        $this->registerSocialiteExtensions();

        if ($this->enabled()) {
            $this->registerRateLimiters();
            $this->loadRoutes();
            $this->loadCommands();
            $this->configureSchedules();
            $this->registerWorkflowHooks();
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
        Route::middleware('throttle:marketing-links')
            ->prefix(trim((string) config('marketing.links.prefix', 'm'), '/'))
            ->name('marketing.links.')
            ->group(__DIR__.'/Routes/public.php');

        Route::middleware(['web', 'auth', 'verified'])
            ->prefix('/dashboard/marketing')
            ->name('dashboard.marketing.')
            ->group(__DIR__.'/Routes/web.php');
    }

    protected function registerRateLimiters(): void
    {
        RateLimiter::for('marketing-links', static fn (Request $request) => Limit::perMinute(
            max(1, (int) config('marketing.links.rate_limit_per_minute', 60)),
        )->by((string) $request->ip()));
    }

    protected function registerPolicies(): void
    {
        Gate::policy(SocialAccount::class, SocialAccountPolicy::class);
        Gate::policy(Contact::class, ContactPolicy::class);
        Gate::policy(Tag::class, TagPolicy::class);
        Gate::policy(Segment::class, SegmentPolicy::class);
        Gate::policy(SocialPost::class, SocialPostPolicy::class);
        Gate::policy(Campaign::class, CampaignPolicy::class);
        Gate::policy(Workflow::class, WorkflowPolicy::class);
    }

    /**
     * Listen for the two events workflows can react to.
     *
     * The listeners are registered only while the feature is enabled and only
     * act once the workflow tables exist, so a half-migrated install or a
     * disabled module never turns a contact create into a boot failure.
     */
    protected function registerWorkflowHooks(): void
    {
        Contact::created(function (Contact $contact): void {
            if (! $this->workflowTablesExist()) {
                return;
            }

            try {
                $this->app->make(WorkflowEngine::class)->contactCreated($contact);
            } catch (Throwable) {
                // An automation must never fail the request that created the
                // contact; the engine records its own failures per workflow.
            }
        });

        SocialPost::updated(function (SocialPost $post): void {
            if (! $post->wasChanged('status') || $post->status !== PostStatus::Published) {
                return;
            }

            if (! $this->workflowTablesExist()) {
                return;
            }

            try {
                $this->app->make(WorkflowEngine::class)->postPublished($post);
            } catch (Throwable) {
                // The post is already published; a workflow failure is logged
                // in workflow_runs rather than unwound into the publisher.
            }
        });
    }

    /**
     * The command the scheduler runs. Registering it only while enabled keeps
     * a disabled module from appearing as an available artisan command.
     */
    protected function loadCommands(): void
    {
        $this->commands([
            PublishDuePosts::class,
            SyncMarketingMetrics::class,
        ]);
    }

    /**
     * Publish due posts once a minute. `withoutOverlapping` is what keeps a
     * slow platform from stacking two runs on the same delivery.
     */
    protected function configureSchedules(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('marketing:publish-due')
                ->everyMinute()
                ->withoutOverlapping();

            if (! (bool) config('marketing.metrics.enabled', false)) {
                return;
            }

            $hours = max(1, min(24, (int) config('marketing.metrics.sync_interval_hours', 6)));
            $expression = $hours === 24 ? '0 0 * * *' : "0 */{$hours} * * *";

            $schedule->command('marketing:sync-metrics')
                ->cron($expression)
                ->withoutOverlapping();
        });
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
            [
                'slug' => 'marketing.posts.manage',
                'name' => 'Manage Posts',
                'description' => 'Compose, schedule, approve and publish posts to connected social accounts.',
            ],
            [
                'slug' => 'marketing.campaigns.manage',
                'name' => 'Manage Campaigns',
                'description' => 'Create and edit the campaigns that group posts for reporting.',
            ],
            [
                'slug' => 'marketing.workflows.manage',
                'name' => 'Manage Workflows',
                'description' => 'Create and edit the automations that tag, update or notify on audience and publishing events.',
            ],
            [
                'slug' => 'marketing.analytics.view',
                'name' => 'View Marketing Analytics',
                'description' => 'See delivery, campaign and workflow reporting for the marketing module.',
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
            // Only links a descriptor cannot express belong here. The
            // resources (accounts, posts, contacts, tags, segments) are
            // appended by the platform registry into this same group, each
            // carrying its own read permission.
            return [[
                'title' => 'Marketing',
                'url' => route('dashboard.marketing.dashboard', [], false),
                'icon' => 'Megaphone',
                'permission' => null,
                'module_slug' => 'Marketing',
                'showModeBadge' => false,
                'items' => [
                    ['title' => 'Dashboard', 'url' => route('dashboard.marketing.dashboard', [], false)],
                    [
                        'title' => 'Analytics',
                        'url' => route('dashboard.marketing.analytics', [], false),
                        'permission' => 'marketing.analytics.view',
                    ],
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
            'posts' => SocialPost::query()->count(),
            'scheduled' => SocialPost::query()->where('status', PostStatus::Scheduled->value)->count(),
            'campaigns' => Campaign::query()->count(),
            'workflows' => Workflow::query()->where('is_active', true)->count(),
        ];
    }

    public function getAdminResources(): array
    {
        return [
            SocialAccountResource::definition(),
            SocialPostResource::definition(),
            ContactResource::definition(),
            TagResource::definition(),
            SegmentResource::definition(),
            CampaignResource::definition(),
            WorkflowResource::definition(),
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
                'social_posts_table' => Schema::hasTable('social_posts'),
                'social_post_targets_table' => Schema::hasTable('social_post_targets'),
                'social_post_metrics_table' => Schema::hasTable('social_post_metrics'),
                'marketing_links_table' => Schema::hasTable('marketing_links'),
                'marketing_link_clicks_table' => Schema::hasTable('marketing_link_clicks'),
                'campaigns_table' => Schema::hasTable('campaigns'),
                'workflows_table' => Schema::hasTable('workflows'),
                'workflow_runs_table' => Schema::hasTable('workflow_runs'),
            ];
        } catch (Throwable) {
            return [];
        }
    }

    public function getScheduledTasks(): array
    {
        $tasks = [[
            'command' => 'marketing:publish-due',
            'frequency' => 'every:60',
            'description' => 'Publishes scheduled posts and retries failed deliveries.',
        ]];

        if ((bool) config('marketing.metrics.enabled', false)) {
            $hours = max(1, min(24, (int) config('marketing.metrics.sync_interval_hours', 6)));

            $tasks[] = [
                'command' => 'marketing:sync-metrics',
                'frequency' => 'every:'.($hours * 3600),
                'description' => 'Collects engagement metrics for published deliveries.',
            ];
        }

        return $tasks;
    }

    public function getMediaSubdirectory(): ?string
    {
        return (string) config('marketing.media.subdirectory', 'social');
    }

    public function onUserDelete(int $userId): void
    {
        // A post addressed to the user's accounts has no audience once the
        // accounts are gone; deleting the post takes its targets with it.
        SocialPost::query()->where('user_id', $userId)->delete();

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
        foreach (['accounts', 'posts', 'contacts', 'tags', 'segments', 'campaigns', 'workflows'] as $area) {
            if ($user->hasPermission("marketing.{$area}.manage")) {
                return true;
            }
        }

        return $user->hasPermission('marketing.analytics.view');
    }

    /**
     * Whether the automation tables exist. The contact observer runs on every
     * contact create, so this check must stay cheap and exception-safe.
     */
    protected function workflowTablesExist(): bool
    {
        try {
            return Schema::hasTable('workflows') && Schema::hasTable('workflow_runs');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Whether the module's tables are present. A fresh install that has not
     * migrated yet must not break the host dashboard.
     */
    protected function tablesExist(): bool
    {
        try {
            return Schema::hasTable('social_accounts')
                && Schema::hasTable('social_posts')
                && Schema::hasTable('social_post_targets')
                && Schema::hasTable('contacts')
                && Schema::hasTable('tags')
                && Schema::hasTable('segments')
                && Schema::hasTable('campaigns')
                && Schema::hasTable('workflows')
                && Schema::hasTable('workflow_runs');
        } catch (Throwable) {
            return false;
        }
    }
}
