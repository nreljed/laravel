<?php

namespace App\Modules\Booking\Services;

use App\Enums\AuditAction;
use App\Modules\Booking\Models\Booking;
use App\Services\AuditService;
use Illuminate\Support\Facades\Event;

class BookingActionService
{
    public static function logStatusTransition(
        Booking $booking,
        string $fromStatus,
        string $toStatus,
        ?int $actorId = null,
        string $actorType = 'system',
        ?string $notes = null,
    ): void {
        $context = [
            'booking_id' => $booking->id,
            'property_id' => $booking->property_id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_type' => $actorType,
        ];

        if ($notes) {
            $context['notes'] = $notes;
        }

        if ($actorId) {
            AuditService::log(
                action: self::mapStatusToAuditAction($fromStatus, $toStatus),
                userId: $actorId,
                context: $context,
            );
        }

        Event::dispatch('booking.status-transition', [
            'booking_id' => $booking->id,
            'from' => $fromStatus,
            'to' => $toStatus,
            'actor_id' => $actorId,
            'actor_type' => $actorType,
        ]);
    }

    public static function logAdjustment(
        Booking $booking,
        array $oldValues,
        array $newValues,
        int $actorId,
        string $actorName = 'Host',
    ): void {
        $changed = [];
        foreach ($newValues as $key => $newValue) {
            $oldValue = $oldValues[$key] ?? null;
            if ($newValue !== $oldValue) {
                $changed[$key] = ['old' => $oldValue, 'new' => $newValue];
            }
        }

        if (empty($changed)) {
            return;
        }

        AuditService::log(
            action: AuditAction::BookingUpdated,
            userId: $actorId,
            context: [
                'booking_id' => $booking->id,
                'property_id' => $booking->property_id,
                'changes' => $changed,
                'actor_name' => $actorName,
            ],
        );

        Event::dispatch('booking.adjustment', [
            'booking_id' => $booking->id,
            'changes' => $changed,
            'actor_id' => $actorId,
        ]);
    }

    public static function logNote(Booking $booking, string $note, int $actorId): void
    {
        AuditService::log(
            action: AuditAction::BookingNoteAdded,
            userId: $actorId,
            context: [
                'booking_id' => $booking->id,
                'property_id' => $booking->property_id,
                'note' => $note,
            ],
        );
    }

    public static function logDepositEvent(Booking $booking, string $event, int $actorId): void
    {
        AuditService::log(
            action: AuditAction::BookingUpdated,
            userId: $actorId,
            context: [
                'booking_id' => $booking->id,
                'property_id' => $booking->property_id,
                'deposit_event' => $event,
            ],
        );
    }

    protected static function mapStatusToAuditAction(string $from, string $to): AuditAction
    {
        return match ($to) {
            'confirmed' => AuditAction::BookingConfirmed,
            'cancelled' => AuditAction::BookingCancelled,
            'checked_in' => AuditAction::BookingCheckedIn,
            'checked_out' => AuditAction::BookingCheckedOut,
            default => AuditAction::BookingUpdated,
        };
    }
}
