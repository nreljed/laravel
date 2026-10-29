<?php

namespace Nreljed\Blog;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Nreljed\Blog\Console\Commands\PostsClean;
use Nreljed\Blog\Console\Commands\PostsSeed;
use Nreljed\Blog\Console\Commands\PublishScheduledContent;
use Nreljed\Blog\Models\Post;
use Nreljed\Blog\Models\PostCategory;
use Nreljed\Blog\Models\PostTag;
use Nreljed\Blog\Observers\PostObserver;
use Nreljed\Blog\Policies\PostPolicy;
use Nreljed\Platform\Concerns\InteractsWithFeatures;
use Nreljed\Platform\Contracts\FeatureProvider;
use Nreljed\Platform\Services\SettingsService;
use Nreljed\Platform\Support\ContentDescription;
use Nreljed\Platform\Support\SettingsSection;

class BlogServiceProvider extends ServiceProvider implements FeatureProvider
{
    use InteractsWithFeatures;

    public function name(): string
    {
        return 'Posts';
    }

    public function slug(): string
    {
        return 'posts';
    }

    public function boot(): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->loadRoutes();
        $this->loadPublicRoutes();
        $this->loadAdminSettingsRoutes();
        $this->loadMigrations();

        $this->loadViewsFrom(__DIR__.'/Resources/views', 'posts');

        Post::observe(PostObserver::class);

        Gate::policy(Post::class, PostPolicy::class);

        $this->commands([
            PublishScheduledContent::class,
            PostsClean::class,
            PostsSeed::class,
        ]);

        $this->configureSchedules();
    }

    protected function loadRoutes(): void
    {
        Route::middleware(['web', 'auth', 'verified', 'throttle:60,1'])
            ->prefix('dashboard/posts')
            ->name('dashboard.posts.')
            ->group(__DIR__.'/Routes/web.php');
    }

    protected function loadPublicRoutes(): void
    {
        // `maintenance` gates the reader-facing blog. The platform's marketing
        // pages have carried it since the package was split; the blog did not,
        // so a visitor reached posts (and the DB behind them) during a
        // maintenance window. Admins bypass the middleware, so previews and
        // scheduled-publish checks keep working for whoever is running it.
        Route::middleware(['web', 'maintenance'])
            ->prefix('blog')
            ->group(__DIR__.'/Routes/public.php');
    }

    protected function loadAdminSettingsRoutes(): void
    {
        Route::middleware(['web', 'auth', 'verified', 'admin', 'throttle:60,1'])
            ->prefix('dashboard/admin/settings/posts')
            ->name('dashboard.posts.settings.')
            ->group(__DIR__.'/Routes/settings.php');
    }

    protected function loadMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * Fire the blog's scheduled publish command once per minute. This moved
     * out of the platform kernel, which no longer owns blog commands.
     */
    protected function configureSchedules(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            if (! Schema::hasTable('posts')) {
                return;
            }

            $schedule->command('schedule:publish')
                ->everyMinute()
                ->withoutOverlapping();
        });
    }

    public function permissions(): array
    {
        return [
            ['slug' => 'posts.view', 'name' => 'View Posts', 'description' => 'View blog posts.', 'group' => 'Posts'],
            ['slug' => 'posts.create', 'name' => 'Create Posts', 'description' => 'Create new blog posts.', 'group' => 'Posts'],
            ['slug' => 'posts.edit', 'name' => 'Edit Posts', 'description' => 'Edit existing blog posts.', 'group' => 'Posts'],
            ['slug' => 'posts.delete', 'name' => 'Delete Posts', 'description' => 'Delete blog posts.', 'group' => 'Posts'],
            ['slug' => 'posts.manage', 'name' => 'Manage All Posts', 'description' => 'View, edit, and delete posts authored by other users.', 'group' => 'Posts'],
            ['slug' => 'posts.categories.manage', 'name' => 'Manage Post Categories', 'description' => 'Create, edit, and delete post categories.', 'group' => 'Posts'],
            ['slug' => 'posts.tags.manage', 'name' => 'Manage Post Tags', 'description' => 'Create, edit, and delete post tags.', 'group' => 'Posts'],
        ];
    }

    public function getNavItems(): array
    {
        return [
            [
                'title' => 'Posts',
                'url' => route('dashboard.posts.dashboard', [], false),
                'icon' => 'FileText',
                'permission' => 'posts.view',
                'groupLabel' => 'Content',
                'items' => [
                    ['title' => 'Dashboard', 'url' => route('dashboard.posts.dashboard', [], false), 'permission' => 'posts.view'],
                    ['title' => 'All Posts', 'url' => route('dashboard.posts.index', [], false), 'permission' => 'posts.view'],
                    ['title' => 'Add Post', 'url' => route('dashboard.posts.create', [], false), 'permission' => 'posts.create'],
                    ['title' => 'Media Library', 'url' => route('dashboard.posts.media', [], false), 'permission' => 'posts.view'],
                    ['title' => 'Categories', 'url' => route('dashboard.posts.categories.index', [], false), 'permission' => 'posts.categories.manage'],
                    ['title' => 'Tags', 'url' => route('dashboard.posts.tags.index', [], false), 'permission' => 'posts.tags.manage'],
                ],
            ],
        ];
    }

    public function getDashboardData(): array
    {
        if (! Schema::hasTable('posts')) {
            return [];
        }

        return [
            'label' => 'Posts',
            'icon' => 'FileText',
            'total' => Post::count(),
            'published' => Post::where('status', 'published')->count(),
            'draft' => Post::where('status', 'draft')->count(),
        ];
    }

    public function getAnalyticsSeries(): array
    {
        if (! Schema::hasTable('posts')) {
            return [];
        }

        $start = now()->subDays(29)->startOfDay();

        $counts = Post::query()
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        $data = collect(range(0, 29))
            ->map(fn (int $offset) => [
                'date' => now()->subDays(29 - $offset)->toDateString(),
                'count' => (int) ($counts[now()->subDays(29 - $offset)->toDateString()] ?? 0),
            ])
            ->values()
            ->all();

        return [
            'posts' => [
                'label' => 'New Posts',
                'data' => $data,
            ],
        ];
    }

    public function getSharedData(): array
    {
        return [];
    }

    public function getSettingsRoute(): ?string
    {
        return 'dashboard.posts.settings.index';
    }

    public function hasSettings(): bool
    {
        return true;
    }

    public function getSettingsGroups(): array
    {
        return ['posts'];
    }

    public function getSettingsData(): array
    {
        $settings = Schema::hasTable('settings') ? SettingsService::getGroup('posts') : [];

        return [
            'show_word_count' => ($settings['show_word_count'] ?? '1') === '1',
        ];
    }

    public function getSettingsSection(): ?SettingsSection
    {
        return new SettingsSection(
            tab: 'posts',
            label: 'Posts',
            icon: 'FileText',
            component: 'posts/settings',
            submitUrl: 'dashboard.posts.settings.update',
            nonceKey: 'posts_settings',
            defaults: ['show_word_count' => true],
        );
    }

    public function getNotificationTypes(): array
    {
        return [
            'post.published' => [
                'label' => 'Post Published',
                'description' => 'When a post is published.',
                'default_channels' => ['in_app', 'email'],
                'default_enabled' => true,
                'group' => 'Content',
                'email_template' => 'post-published',
                'entity_id_keys' => ['post_id'],
            ],
            'post.scheduled' => [
                'label' => 'Post Scheduled',
                'description' => 'When a post is scheduled.',
                'default_channels' => ['in_app', 'email'],
                'default_enabled' => true,
                'group' => 'Content',
                'email_template' => 'post-scheduled',
                'entity_id_keys' => ['post_id'],
            ],
        ];
    }

    public function getEmailTemplates(): array
    {
        return [
            'post-published' => [
                'label' => 'Post Published',
                'group' => 'Content',
                'description' => 'Notifies the author when a post is published.',
                'sample_data' => [
                    'postTitle' => 'Welcome to the blog',
                    'postUrl' => 'https://example.com/blog/welcome',
                    'userName' => 'John Doe',
                ],
            ],
            'post-scheduled' => [
                'label' => 'Post Scheduled',
                'group' => 'Content',
                'description' => 'Notifies the author when a post is scheduled for publication.',
                'sample_data' => [
                    'postTitle' => 'Update: product roadmap',
                    'postUrl' => 'https://example.com/blog/update',
                    'userName' => 'John Doe',
                    'scheduledAt' => 'Mar 15, 2026 09:00',
                ],
            ],
        ];
    }

    public function getGalleryConfig(): array
    {
        return [
            'gallery' => [
                'label' => 'Post Gallery',
                'json_key' => 'post_gallery',
                'thumbnail_key' => 'post_thumbnail_url',
                'max_images' => 20,
                'upload_action' => 'dashboard.posts.upload-image',
                'delete_action' => 'dashboard.posts.delete-image',
                'upload_folder' => 'posts',
                'hide_in_media_library' => true,
                'create_attachment' => true,
            ],
        ];
    }

    public function onUserDelete(int $userId): void
    {
        Post::where('user_id', $userId)->each(function (Post $post) {
            $post->delete();
        });
    }

    public function getMediaSubdirectory(): ?string
    {
        return 'posts';
    }

    public function getComposerPackage(): ?string
    {
        return 'nreljed/blog';
    }

    public function getOrphanMediaTargets(): array
    {
        return [
            'posts' => Post::class,
        ];
    }

    public function getHealthChecks(): array
    {
        return [
            'posts_table' => Schema::hasTable('posts'),
        ];
    }

    public function getContentDescription(): ?ContentDescription
    {
        return new ContentDescription(
            slug: 'post',
            modelClass: Post::class,
            label: 'Post',
            features: ['seo', 'excerpt', 'scheduled', 'gallery'],
            taxonomies: [
                ['key' => 'category', 'label' => 'Categories', 'hierarchical' => true],
                ['key' => 'tag', 'label' => 'Tags', 'hierarchical' => false],
            ],
            mediaConfig: [
                'subdirectory' => 'posts',
                'organize_by_month' => true,
                'max_images' => 20,
                'upload_action' => 'dashboard.posts.upload-image',
                'delete_action' => 'dashboard.posts.delete-image',
            ],
            capabilityBase: 'posts',
        );
    }

    public function getSitemapUrls(): array
    {
        if (! Schema::hasTable('posts')) {
            return [];
        }

        $urls = [
            [
                'loc' => '/blog',
                'changefreq' => 'daily',
                'priority' => '0.8',
            ],
        ];

        Post::query()
            ->where('status', 'published')
            ->each(function (Post $post) use (&$urls) {
                $urls[] = [
                    'loc' => sprintf('/blog/%s', $post->slug),
                    'changefreq' => 'weekly',
                    'priority' => '0.7',
                    'lastmod' => ($post->updated_at ?? $post->published_at)?->toDateString(),
                ];
            });

        PostCategory::query()
            ->whereHas('posts', fn ($query) => $query->where('status', 'published'))
            ->each(function (PostCategory $category) use (&$urls) {
                $urls[] = [
                    'loc' => sprintf('/blog?category=%s', $category->slug),
                    'changefreq' => 'weekly',
                    'priority' => '0.6',
                ];
            });

        PostTag::query()
            ->whereHas('posts', fn ($query) => $query->where('status', 'published'))
            ->each(function (PostTag $tag) use (&$urls) {
                $urls[] = [
                    'loc' => sprintf('/blog?tag=%s', $tag->slug),
                    'changefreq' => 'weekly',
                    'priority' => '0.5',
                ];
            });

        return $urls;
    }

    public function getScheduledTasks(): array
    {
        return [
            [
                'command' => PublishScheduledContent::class,
                'frequency' => 'every:1',
                'description' => 'Publish due scheduled posts and fire post.published notifications.',
            ],
        ];
    }
}
