<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\Models\Booking;

class BookingAvailabilityService
{
    public function isAvailable(int $propertyId, string $startDate, string $endDate, ?int $excludeBookingId = null): bool
    {
        return Booking::overlapping($propertyId, $startDate, $endDate, $excludeBookingId)->count() === 0;
    }

    public function getBlockedDates(int $propertyId, string $from, string $to): array
    {
        $bookings = Booking::where('property_id', $propertyId)
            ->whereIn('status', ['pending', 'confirmed', 'checked_in', 'checked_out'])
            ->where('start_date', '<', $to)
            ->where('end_date', '>', $from)
            ->get(['start_date', 'end_date', 'status', 'id']);

        $dates = [];

        foreach ($bookings as $booking) {
            $current = \Carbon\Carbon::parse($booking->start_date);
            $end = \Carbon\Carbon::parse($booking->end_date);

            while ($current->lt($end)) {
                $dates[] = [
                    'date' => $current->toDateString(),
                    'booking_id' => $booking->id,
                    'status' => $booking->status,
                ];
                $current->addDay();
            }
        }

        return $dates;
    }

    public function getMonthData(int $propertyId, int $year, int $month): array
    {
        $start = \Carbon\Carbon::create($year, $month, 1);
        $end = (clone $start)->addMonth();

        $bookings = Booking::with(['property:id,name'])
            ->where('property_id', $propertyId)
            ->whereIn('status', ['pending', 'confirmed', 'checked_in', 'checked_out'])
            ->where('start_date', '<', $end->toDateString())
            ->where('end_date', '>', $start->toDateString())
            ->get();

        $days = [];
        $cursor = clone $start;

        while ($cursor->lt($end)) {
            $dateStr = $cursor->toDateString();
            $dayBookings = [];

            foreach ($bookings as $booking) {
                if ($dateStr >= $booking->start_date && $dateStr < $booking->end_date) {
                    $dayBookings[] = [
                        'id' => $booking->id,
                        'status' => $booking->status,
                    ];
                }
            }

            $days[$dateStr] = [
                'date' => $dateStr,
                'booked' => count($dayBookings) > 0,
                'bookings' => $dayBookings,
            ];

            $cursor->addDay();
        }

        return [
            'year' => $year,
            'month' => $month,
            'days' => $days,
        ];
    }
}
