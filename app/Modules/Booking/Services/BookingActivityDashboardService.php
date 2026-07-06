<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\Enums\BookingStatus;
use App\Modules\Booking\Models\Booking;

class BookingActivityDashboardService
{
    public static function getTodaySummary(?int $hostId = null): array
    {
        $today = now()->format('Y-m-d');
        $weekFromNow = now()->addWeek()->format('Y-m-d');

        return [
            'today_check_ins' => self::queryBookings(BookingStatus::Confirmed, 'start_date', $today, $today, $hostId),
            'today_check_outs' => self::queryBookings(BookingStatus::CheckedIn, 'end_date', $today, $today, $hostId),
            'currently_checked_in' => self::queryBookings(BookingStatus::CheckedIn, null, null, null, $hostId),
            'upcoming_arrivals' => self::queryBookings(BookingStatus::Confirmed, 'start_date', $today, $weekFromNow, $hostId),
        ];
    }

    private static function queryBookings(?BookingStatus $status, ?string $dateField = null, ?string $dateFrom = null, ?string $dateTo = null, ?int $hostId = null): array
    {
        $query = Booking::query()
            ->with('property:id,name')
            ->limit(5)
            ->orderBy('created_at');

        if ($status) {
            $query->where('status', $status);
        }

        if ($hostId) {
            $query->where('user_id', $hostId);
        }

        if ($dateField && $dateFrom) {
            $query->where($dateField, '>=', $dateFrom);
        }

        if ($dateField && $dateTo) {
            $query->where($dateField, '<=', $dateTo);
        }

        $bookings = $query->get();

        return [
            'count' => $bookings->count(),
            'bookings' => $bookings->map(fn (Booking $b) => [
                'id' => $b->id,
                'guest_name' => $b->guest_name ?: 'Guest',
                'property_title' => $b->property?->name ?? '',
                'check_in_date' => $b->start_date?->format('Y-m-d'),
                'check_out_date' => $b->end_date?->format('Y-m-d'),
            ])->toArray(),
        ];
    }
}
