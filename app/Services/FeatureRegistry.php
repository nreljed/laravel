<?php

namespace App\Services;

use App\Blog\BlogServiceProvider;
use App\TestPosts\TestPostsServiceProvider;
use Illuminate\Support\ServiceProvider;

class FeatureRegistry
{
    protected BlogServiceProvider $blog;

    protected TestPostsServiceProvider $testPosts;

    public function __construct()
    {
        $this->blog = $this->getProviderOrFail(BlogServiceProvider::class);
        $this->testPosts = $this->getProviderOrFail(TestPostsServiceProvider::class);
    }

    /**
     * @template T of \Illuminate\Support\ServiceProvider
     *
     * @param  class-string<T>  $class
     * @return T
     */
    protected function getProviderOrFail(string $class): ServiceProvider
    {
        $provider = app()->getProvider($class);

        if ($provider === null) {
            throw new \RuntimeException("Service provider [{$class}] is not registered.");
        }

        return $provider;
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
        $data = [];

        foreach ([$this->blog, $this->testPosts] as $provider) {
            $featureData = $provider->getDashboardData();

            if (! empty($featureData)) {
                $data[$provider->slug()] = $featureData;
            }
        }

        return $data;
    }

    public function getAllAnalyticsSeries(): array
    {
        $series = [];

        foreach ([$this->blog, $this->testPosts] as $provider) {
            if (method_exists($provider, 'getAnalyticsSeries')) {
                $series = array_merge($series, $provider->getAnalyticsSeries());
            }
        }

        return $series;
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
