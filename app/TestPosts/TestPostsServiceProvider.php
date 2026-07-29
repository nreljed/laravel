<?php

namespace App\TestPosts;

use App\TestPosts\Models\TestPost;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class TestPostsServiceProvider extends ServiceProvider
{
    public function name(): string
    {
        return 'TestPosts';
    }

    public function slug(): string
    {
        return 'test-posts';
    }

    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadPublicRoutes();
        $this->loadMigrations();
    }

    protected function loadRoutes(): void
    {
        Route::middleware(['web', 'auth', 'verified', 'throttle:60,1'])
            ->prefix('dashboard/test-posts')
            ->name('dashboard.test-posts.')
            ->group(__DIR__.'/Routes/web.php');
    }

    protected function loadPublicRoutes(): void
    {
        Route::middleware(['web'])
            ->prefix('test-posts')
            ->name('test-posts.')
            ->group(__DIR__.'/Routes/public.php');
    }

    protected function loadMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    public function permissions(): array
    {
        return [
            ['slug' => 'test-posts.view', 'name' => 'View Tests', 'description' => 'View tests.'],
            ['slug' => 'test-posts.create', 'name' => 'Create Tests', 'description' => 'Create new tests.'],
            ['slug' => 'test-posts.edit', 'name' => 'Edit Tests', 'description' => 'Edit existing tests.'],
            ['slug' => 'test-posts.delete', 'name' => 'Delete Tests', 'description' => 'Delete tests.'],
        ];
    }

    public function getNavItems(): array
    {
        return [
            [
                'title' => 'Test',
                'url' => route('dashboard.test-posts.dashboard', [], false),
                'icon' => 'FileCheck',
                'permission' => 'test-posts.view',
                'groupLabel' => 'Testing',
                'items' => [
                    ['title' => 'Dashboard', 'url' => route('dashboard.test-posts.dashboard', [], false)],
                    ['title' => 'All Tests', 'url' => route('dashboard.test-posts.index', [], false)],
                    ['title' => 'Add Test', 'url' => route('dashboard.test-posts.create', [], false)],
                ],
            ],
        ];
    }

    public function getDashboardData(): array
    {
        if (! Schema::hasTable('test_posts')) {
            return [];
        }

        return [
            'label' => 'Test',
            'icon' => 'FileCheck',
            'total' => TestPost::count(),
            'published' => TestPost::where('status', 'published')->count(),
            'draft' => TestPost::where('status', 'draft')->count(),
        ];
    }

    public function getSharedData(): array
    {
        return [];
    }

    public function getNotificationTypes(): array
    {
        return [];
    }

    public function getEmailTemplates(): array
    {
        return [];
    }

    public function getGalleryConfigs(): array
    {
        return [
            'test_post_gallery' => [
                'label' => 'Test Gallery',
                'json_key' => 'test_post_gallery',
                'thumbnail_key' => 'test_post_thumbnail_url',
                'max_images' => 20,
                'upload_action' => 'dashboard.test-posts.upload-image',
                'delete_action' => 'dashboard.test-posts.delete-image',
                'upload_folder' => 'test-posts',
                'hide_in_media_library' => true,
                'create_attachment' => true,
            ],
        ];
    }

    public function onUserDelete(int $userId): void
    {
        TestPost::where('user_id', $userId)->each(function (TestPost $testPost) {
            $testPost->delete();
        });
    }
}
