<?php

namespace Nreljed\BookingModule\Booking\Services;

use Nreljed\BookingModule\Booking\Models\BookingAction;

class BookingActionService
{
    public static function logAction(
        int $bookingId,
        string $actionType,
        string $actorType = 'system',
        int $actorId = 0,
        string $actorName = '',
        array $oldValue = [],
        array $newValue = [],
        string $notes = '',
        array $metadata = [],
    ): BookingAction {
        return BookingAction::create([
            'booking_id' => $bookingId,
            'action_type' => $actionType,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'old_value' => ! empty($oldValue) ? $oldValue : null,
            'new_value' => ! empty($newValue) ? $newValue : null,
            'notes' => $notes,
            'metadata' => ! empty($metadata) ? $metadata : null,
        ]);
    }

    public static function deleteActions(int $bookingId): void
    {
        BookingAction::where('booking_id', $bookingId)->delete();
    }
}
