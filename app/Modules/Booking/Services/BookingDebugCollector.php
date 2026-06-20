<?php

namespace App\Modules\Booking\Services;

class BookingDebugCollector
{
    private static array $entries = [];

    public static function add(string $message): void
    {
        if (!config('app.debug')) {
            return;
        }

        self::$entries[] = $message;
    }

    public static function addTiming(string $label, float $start): void
    {
        if (!config('app.debug')) {
            return;
        }

        $elapsed = round((microtime(true) - $start) * 1000, 2);
        self::$entries[] = "{$label}: {$elapsed}ms";
    }

    public static function isDebug(): bool
    {
        return config('app.debug');
    }

    public static function getAll(): array
    {
        return self::$entries;
    }

    public static function clear(): void
    {
        self::$entries = [];
    }
}
