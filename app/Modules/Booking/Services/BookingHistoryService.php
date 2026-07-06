<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Models\BookingAction;

class BookingHistoryService
{
    public static function getTimeline(int $bookingId, int $limit = 0, int $offset = 0): array
    {
        $query = BookingAction::where('booking_id', $bookingId)
            ->orderBy('created_at');

        if ($limit > 0) {
            $query->limit($limit);
        }
        if ($offset > 0) {
            $query->offset($offset);
        }

        return $query->get()->toArray();
    }

    public static function getRecentTimeline(int $bookingId, int $limit = 5): array
    {
        return self::getTimeline($bookingId, $limit);
    }

    public static function getActionsByType(int $bookingId, string $actionType, int $limit = 0): array
    {
        $query = BookingAction::where('booking_id', $bookingId)
            ->where('action_type', $actionType)
            ->orderBy('created_at', 'desc');

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get()->toArray();
    }

    public static function getRecentActions(int $propertyId, int $limit = 10): array
    {
        $bookingIds = Booking::where('property_id', $propertyId)->pluck('id');

        if ($bookingIds->isEmpty()) {
            return [];
        }

        return BookingAction::whereIn('booking_id', $bookingIds)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public static function getTodayActionsForProperty(int $propertyId, string $date = ''): array
    {
        if (empty($date)) {
            $date = now()->format('Y-m-d');
        }

        $bookingIds = Booking::where('property_id', $propertyId)
            ->whereIn('status', ['pending', 'confirmed', 'checked_in', 'checked_out'])
            ->pluck('id');

        if ($bookingIds->isEmpty()) {
            return [];
        }

        return BookingAction::whereIn('booking_id', $bookingIds)
            ->whereDate('created_at', $date)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }
}
