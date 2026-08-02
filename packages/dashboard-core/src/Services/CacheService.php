<?php

namespace Nreljed\DashboardCore\Services;

use Illuminate\Support\Facades\Cache;

class CacheService
{
    public static function getVersion(string $prefix, int $id): int
    {
        return Cache::get("{$prefix}_cache_version_{$id}", 0);
    }

    public static function bust(string $prefix, int $id): void
    {
        Cache::forever("{$prefix}_cache_version_{$id}", time());
    }

    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        return Cache::remember($key, $ttl, $callback);
    }

    public static function rememberWithVersion(string $prefix, int $id, string $key, int $ttl, callable $callback): mixed
    {
        $version = self::getVersion($prefix, $id);

        $cached = Cache::get($key);

        if ($cached !== null && isset($cached['version']) && $cached['version'] === $version) {
            return $cached['data'];
        }

        $data = $callback();

        Cache::put($key, ['version' => $version, 'data' => $data], $ttl);

        return $data;
    }

    public static function forget(string $key): bool
    {
        return Cache::forget($key);
    }

    public static function flushTagged(string $tag): void
    {
        $keys = Cache::get("tag:{$tag}:keys", []);

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        Cache::forget("tag:{$tag}:keys");
    }

    public static function tag(string $tag, string $key): void
    {
        $keys = Cache::get("tag:{$tag}:keys", []);

        if (! in_array($key, $keys, true)) {
            $keys[] = $key;
            Cache::forever("tag:{$tag}:keys", $keys);
        }
    }
}
