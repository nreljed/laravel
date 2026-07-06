<?php

namespace App\Modules;

use App\Modules\Contracts\Bookable;
use Illuminate\Support\Collection;

class BookableRegistry
{
    protected Collection $providers;

    public function __construct()
    {
        $this->providers = new Collection;
    }

    public function register(string $slug, callable $resolver): void
    {
        $this->providers->put($slug, $resolver);
    }

    public function resolve(string $providerSlug, int $id): ?Bookable
    {
        $resolver = $this->providers->get($providerSlug);

        if (! $resolver) {
            return null;
        }

        return $resolver($id);
    }

    public function hasProvider(string $slug): bool
    {
        return $this->providers->has($slug);
    }

    public function getProviderSlugs(): array
    {
        return $this->providers->keys()->toArray();
    }
}
