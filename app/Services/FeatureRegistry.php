<?php

namespace App\Services;

use App\Blog\BlogServiceProvider;
use App\TestPosts\TestPostsServiceProvider;

class FeatureRegistry
{
    protected BlogServiceProvider $blog;

    protected TestPostsServiceProvider $testPosts;

    public function __construct()
    {
        $this->blog = app()->getProvider(BlogServiceProvider::class);
        $this->testPosts = app()->getProvider(TestPostsServiceProvider::class);
    }

    public function getAllNavItems(): array
    {
        return array_merge(
            $this->blog->getNavItems(),
            $this->testPosts->getNavItems(),
        );
    }

    public function getAllSharedData(): array
    {
        return array_merge(
            $this->blog->getSharedData(),
            $this->testPosts->getSharedData(),
        );
    }

    public function getAllDashboardData(): array
    {
        return array_merge(
            $this->blog->getDashboardData(),
            $this->testPosts->getDashboardData(),
        );
    }

    public function getAllNotificationTypes(): array
    {
        return array_merge(
            $this->blog->getNotificationTypes(),
            $this->testPosts->getNotificationTypes(),
        );
    }

    public function getAllEmailTemplates(): array
    {
        return array_merge(
            $this->blog->getEmailTemplates(),
            $this->testPosts->getEmailTemplates(),
        );
    }

    public function getAllGalleryConfigs(): array
    {
        return array_merge(
            $this->blog->getGalleryConfigs(),
            $this->testPosts->getGalleryConfigs(),
        );
    }

    public function permissions(): array
    {
        return array_merge(
            $this->blog->permissions(),
            $this->testPosts->permissions(),
        );
    }

    public function getAll(): array
    {
        return [
            [
                'name' => 'Blog',
                'slug' => 'blog',
                'enabled' => true,
                'version' => null,
            ],
            [
                'name' => 'Test',
                'slug' => 'test-posts',
                'enabled' => true,
                'version' => null,
            ],
        ];
    }

    public function cleanupUserData(int $userId): void
    {
        $this->blog->onUserDelete($userId);
        $this->testPosts->onUserDelete($userId);
    }
}
