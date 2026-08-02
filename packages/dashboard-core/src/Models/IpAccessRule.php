<?php

namespace Nreljed\DashboardCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class IpAccessRule extends Model
{
    protected $fillable = ['ip_address', 'type', 'description', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    private const string CACHE_KEY = 'ip_access_rules';

    public static function isIpAllowed(string $ip): bool
    {
        $rules = static::getActiveRules();

        $blocklisted = collect($rules)->where('type', 'block')->pluck('ip_address')->toArray();
        if (self::ipMatchesAny($ip, $blocklisted)) {
            return false;
        }

        $allowlisted = collect($rules)->where('type', 'allow')->pluck('ip_address')->toArray();
        if (count($allowlisted) > 0 && ! self::ipMatchesAny($ip, $allowlisted)) {
            return false;
        }

        return true;
    }

    /**
     * @return array<int, static>
     */
    public static function getActiveRules(): array
    {
        if (! Schema::hasTable('ip_access_rules')) {
            return [];
        }

        return Cache::remember(self::CACHE_KEY, 300, fn () => static::where('is_active', true)->get()->toArray());
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    #[\Override]
    protected static function boot(): void
    {
        parent::boot();

        static::created(fn () => static::clearCache());
        static::updated(fn () => static::clearCache());
        static::deleted(fn () => static::clearCache());
    }

    private static function ipMatchesAny(string $ip, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (str_contains($pattern, '*')) {
                $escaped = str_replace('.', '\\.', $pattern);
                $regex = '/^'.str_replace('*', '.*', $escaped).'$/';
                if (preg_match($regex, $ip)) {
                    return true;
                }
            } elseif (str_contains($pattern, '/')) {
                if (self::cidrMatch($ip, $pattern)) {
                    return true;
                }
            } else {
                if ($ip === $pattern) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function cidrMatch(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr);
        $mask = (int) $mask;

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $maskLong = -1 << (32 - $mask);

        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }
}
