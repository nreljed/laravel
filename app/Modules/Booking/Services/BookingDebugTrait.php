<?php

namespace App\Modules\Booking\Services;

use Illuminate\Support\Facades\Log;

trait BookingDebugTrait
{
    private static function isDebug(): bool
    {
        return config('app.debug');
    }

    private static function debugLog(string $message): void
    {
        if (! self::isDebug()) {
            return;
        }

        BookingDebugCollector::add($message);
        Log::debug('[CRM Booking] '.$message);
    }

    private static function debugTiming(string $label, float $start): void
    {
        if (! self::isDebug()) {
            return;
        }

        $elapsed = round((microtime(true) - $start) * 1000, 2);
        BookingDebugCollector::addTiming($label, $start);

        if ($elapsed > 10) {
            Log::debug("[CRM Booking] {$label} took {$elapsed}ms");
        }
    }

    private static function debugTrace(string $message): void
    {
        if (! self::isDebug()) {
            return;
        }

        BookingDebugCollector::add($message);
        Log::debug('[CRM Booking] '.$message);
    }
}
