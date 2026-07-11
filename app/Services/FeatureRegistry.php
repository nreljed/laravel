<?php

namespace App\Services;

use App\Blog\BlogServiceProvider;

class FeatureRegistry
{
    protected BlogServiceProvider $blog;

    public function __construct()
    {
        $this->blog = new BlogServiceProvider(app());
    }

    public function getAllNavItems(): array
    {
        return $this->blog->getNavItems();
    }

    public function getAllSharedData(): array
    {
        return $this->blog->getSharedData();
    }

    public function getAllDashboardData(): array
    {
        return $this->blog->getDashboardData();
    }

    public function getAllNotificationTypes(): array
    {
        return $this->blog->getNotificationTypes();
    }

    public function getAllEmailTemplates(): array
    {
        return $this->blog->getEmailTemplates();
    }

    public function getAllGalleryConfigs(): array
    {
        return $this->blog->getGalleryConfigs();
    }

    public function permissions(): array
    {
        return $this->blog->permissions();
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
        ];
    }

    public function cleanupUserData(int $userId): void
    {
        $this->blog->onUserDelete($userId);
    }
}
