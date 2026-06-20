<?php

namespace App\Modules\Booking\Services;

use Illuminate\Support\Facades\Cache;

class BookingCacheService
{
    public const AVAILABILITY_TTL = 300;
    public const PRICE_TTL = 3600;

    public static function getCacheVersion(int $propertyId): int
    {
        return (int) Cache::get('booking_cache_v_' . $propertyId, 1);
    }

    public static function bustPropertyCache(int $propertyId): void
    {
        $version = self::getCacheVersion($propertyId);
        Cache::put('booking_cache_v_' . $propertyId, $version + 1, now()->addDays(30));
    }

    /**
     * @param callable(): array<int|string, mixed> $callback
     * @return array<int|string, mixed>
     */
    public static function cacheResult(string $cacheKey, int $ttl, callable $callback): array
    {
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $queryStart = microtime(true);
        $result = $callback();

        Cache::put($cacheKey, $result, now()->addSeconds($ttl));
        return $result;
    }
}
