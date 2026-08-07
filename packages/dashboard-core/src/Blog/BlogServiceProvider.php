<?php

namespace Nreljed\DashboardCore\Blog;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Nreljed\DashboardCore\Blog\Models\Post;
use Nreljed\DashboardCore\Concerns\InteractsWithFeatures;
use Nreljed\DashboardCore\Contracts\FeatureProvider;
use Nreljed\DashboardCore\Observers\PostObserver;

class BlogServiceProvider extends ServiceProvider implements FeatureProvider
{
    use InteractsWithFeatures;

    public function name(): string
    {
        return 'Blog';
    }

    public function slug(): string
    {
        return 'posts';
    }

    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadPublicRoutes();
        $this->loadMigrations();

        Post::observe(PostObserver::class);
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
        Route::middleware(['web'])
            ->prefix('blog')
            ->group(__DIR__.'/Routes/public.php');
    }

    protected function loadMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    public function permissions(): array
    {
        return [
            ['slug' => 'posts.view', 'name' => 'View Posts', 'description' => 'View blog posts.', 'group' => 'Blog'],
            ['slug' => 'posts.create', 'name' => 'Create Posts', 'description' => 'Create new blog posts.', 'group' => 'Blog'],
            ['slug' => 'posts.edit', 'name' => 'Edit Posts', 'description' => 'Edit existing blog posts.', 'group' => 'Blog'],
            ['slug' => 'posts.delete', 'name' => 'Delete Posts', 'description' => 'Delete blog posts.', 'group' => 'Blog'],
            ['slug' => 'posts.manage', 'name' => 'Manage All Posts', 'description' => 'View, edit, and delete posts authored by other users.', 'group' => 'Blog'],
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
                    ['title' => 'Categories', 'url' => route('dashboard.posts.categories.index', [], false), 'permission' => 'manage-categories'],
                    ['title' => 'Tags', 'url' => route('dashboard.posts.tags.index', [], false), 'permission' => 'manage-tags'],
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

    public function getNotificationTypes(): array
    {
        return [
            'post.published' => [
                'label' => 'Post Published',
                'description' => 'When a post is published.',
                'default_channels' => ['in_app'],
                'default_enabled' => true,
                'group' => 'Content',
            ],
            'post.scheduled' => [
                'label' => 'Post Scheduled',
                'description' => 'When a post is scheduled.',
                'default_channels' => ['in_app'],
                'default_enabled' => true,
                'group' => 'Content',
            ],
        ];
    }

    public function getEmailTemplates(): array
    {
        return [];
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
}
