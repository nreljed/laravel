<?php

namespace App\Modules\Posts;

use App\Modules\Contracts\ModuleInterface;
use App\Modules\Posts\Models\Post;
use App\Modules\Traits\HasRoutes;
use Illuminate\Support\Facades\Schema;

class PostsModule implements ModuleInterface
{
    use HasRoutes;

    public function name(): string
    {
        return 'Posts';
    }

    public function slug(): string
    {
        return 'posts';
    }

    public function description(): ?string
    {
        return 'Create and manage blog posts and articles.';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function dependencies(): array
    {
        return [];
    }

    public function routes(): void
    {
        $this->loadRoutes(__DIR__.'/Routes/web.php', '/dashboard/posts', 'dashboard.posts.', 'posts.view');
    }

    public function boot(): void
    {
        //
    }

    public function register(): void
    {
        //
    }

    public function getNavGroupLabel(): ?string
    {
        return 'Manage Posts';
    }

    public function getNavItems(): array
    {
        return [
            'title' => 'Posts',
            'url' => route('dashboard.posts.dashboard', [], false),
            'icon' => 'FileText',
            'permission' => 'posts.view',
            'module_slug' => 'posts',
            'showModeBadge' => false,
            'items' => [
                ['title' => 'Dashboard', 'url' => route('dashboard.posts.dashboard', [], false)],
                ['title' => 'All Posts', 'url' => route('dashboard.posts.index', [], false)],
                ['title' => 'Add Post', 'url' => route('dashboard.posts.create', [], false)],
                ['title' => 'Categories', 'url' => route('dashboard.posts.categories.index', [], false)],
                ['title' => 'Tags', 'url' => route('dashboard.posts.tags.index', [], false)],
            ],
        ];
    }

    public function getMetaFormattingConfig(): array
    {
        return [
            'price_fields' => [],
            'surface_fields' => [],
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

    public function getSettingsRoute(): ?string
    {
        return null;
    }

    public function getFrontendPages(): array
    {
        return [
            ['path' => '/dashboard/posts/dashboard', 'component' => 'Posts/Dashboard', 'name' => 'dashboard.posts.dashboard'],
            ['path' => '/dashboard/posts', 'component' => 'Posts/Index', 'name' => 'dashboard.posts.index'],
            ['path' => '/dashboard/posts/create', 'component' => 'Posts/Form', 'name' => 'dashboard.posts.create'],
            ['path' => '/dashboard/posts/{post}/edit', 'component' => 'Posts/Form', 'name' => 'dashboard.posts.edit'],
            ['path' => '/dashboard/posts/categories', 'component' => 'Posts/Categories/Index', 'name' => 'dashboard.posts.categories.index'],
            ['path' => '/dashboard/posts/tags', 'component' => 'Posts/Tags/Index', 'name' => 'dashboard.posts.tags.index'],
        ];
    }

    public function migrationPaths(): array
    {
        return [__DIR__.'/database/migrations'];
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

    public function hasSettings(): bool
    {
        return false;
    }

    public function getRepeaterConfig(): array
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

    public function getProfileFieldsConfig(): array
    {
        return [];
    }

    public function getEmailTemplates(): array
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

    public function onUserDelete(int $userId): void
    {
        Post::where('user_id', $userId)->each(function (Post $post) {
            $post->delete();
        });
    }
}
