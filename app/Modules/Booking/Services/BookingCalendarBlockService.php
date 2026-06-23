<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\Models\Booking;

class BookingCalendarBlockService
{
    public static function blockDatesForBooking(int $bookingId): bool
    {
        $booking = Booking::find($bookingId);
        if (! $booking || ! $booking->start_date || ! $booking->end_date) {
            return false;
        }

        $blocked = self::getBlockedDates($bookingId);
        $dates = self::generateDateRange(
            $booking->start_date->toDateString(),
            $booking->end_date->toDateString()
        );

        $blocked = array_unique(array_merge($blocked, $dates));

        $meta = $booking->metadata ?? [];
        $meta['calendar_blocked_dates'] = array_values($blocked);
        $meta['calendar_blocked'] = true;
        $booking->update(['metadata' => $meta]);

        return true;
    }

    public static function unblockDatesForBooking(int $bookingId): bool
    {
        $booking = Booking::find($bookingId);
        if (! $booking) {
            return false;
        }

        $meta = $booking->metadata ?? [];
        unset($meta['calendar_blocked_dates']);
        $meta['calendar_blocked'] = false;
        $booking->update(['metadata' => $meta]);

        return true;
    }

    /**
     * @return string[]
     */
    public static function getBlockedDates(int $bookingId): array
    {
        $booking = Booking::find($bookingId);
        if (! $booking) {
            return [];
        }

        $meta = $booking->metadata ?? [];
        $raw = $meta['calendar_blocked_dates'] ?? [];

        return is_array($raw) ? $raw : [];
    }

    /**
     * @return string[]
     */
    private static function generateDateRange(string $startDate, string $endDate): array
    {
        $dates = [];
        $current = new \DateTime($startDate, new \DateTimeZone(config('app.timezone')));
        $end = new \DateTime($endDate, new \DateTimeZone(config('app.timezone')));
        $end->modify('-1 day');

        while ($current <= $end) {
            $dates[] = $current->format('Y-m-d');
            $current->modify('+1 day');
        }

        return $dates;
    }
}
