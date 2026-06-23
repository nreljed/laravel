<?php

namespace App\Modules\Booking\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BookingPipelineTrace
{
    private const CACHE_PREFIX = 'booking_trace_';

    private const CACHE_TTL = 3600;

    public static function add(int $bookingId, string $stage, string $message, array $context = []): void
    {
        if (! config('app.debug')) {
            return;
        }

        $trace = self::load($bookingId);

        $trace[] = [
            'at' => now()->toDateTimeString(),
            'stage' => $stage,
            'message' => $message,
            'context' => $context,
        ];

        Cache::put(self::CACHE_PREFIX.$bookingId, $trace, now()->addSeconds(self::CACHE_TTL));

        Log::debug(sprintf(
            '[CRM Booking Trace] #%d | %s | %s',
            $bookingId,
            $stage,
            $message
        ));
    }

    public static function get(int $bookingId): array
    {
        return self::load($bookingId);
    }

    public static function clear(int $bookingId): void
    {
        Cache::forget(self::CACHE_PREFIX.$bookingId);
    }

    private static function load(int $bookingId): array
    {
        $trace = Cache::get(self::CACHE_PREFIX.$bookingId);

        return is_array($trace) ? $trace : [];
    }
}
