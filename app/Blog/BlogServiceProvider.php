<?php

namespace App\Blog;

use App\Blog\Models\Post;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class BlogServiceProvider extends ServiceProvider
{
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
        $this->loadMigrations();
    }

    protected function loadRoutes(): void
    {
        Route::middleware(['web', 'auth', 'verified', 'throttle:60,1'])
            ->prefix('dashboard/posts')
            ->name('dashboard.posts.')
            ->group(__DIR__.'/Routes/web.php');
    }

    protected function loadMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    public function permissions(): array
    {
        return [
            ['slug' => 'posts.view', 'name' => 'View Posts', 'description' => 'View blog posts.'],
            ['slug' => 'posts.create', 'name' => 'Create Posts', 'description' => 'Create new blog posts.'],
            ['slug' => 'posts.edit', 'name' => 'Edit Posts', 'description' => 'Edit existing blog posts.'],
            ['slug' => 'posts.delete', 'name' => 'Delete Posts', 'description' => 'Delete blog posts.'],
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
                    ['title' => 'Dashboard', 'url' => route('dashboard.posts.dashboard', [], false)],
                    ['title' => 'All Posts', 'url' => route('dashboard.posts.index', [], false)],
                    ['title' => 'Add Post', 'url' => route('dashboard.posts.create', [], false)],
                    ['title' => 'Categories', 'url' => route('dashboard.posts.categories.index', [], false)],
                    ['title' => 'Tags', 'url' => route('dashboard.posts.tags.index', [], false)],
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

    public function getGalleryConfigs(): array
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
