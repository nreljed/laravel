<?php

namespace App\Modules\Booking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingAction extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'booking_id',
        'action_type',
        'actor_type',
        'actor_id',
        'actor_name',
        'old_value',
        'new_value',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'old_value' => 'array',
            'new_value' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public static function actionTypeLabel(string $type): string
    {
        $labels = [
            'booking_created' => 'Booking Created',
            'booking_confirmed' => 'Booking Confirmed',
            'booking_cancelled' => 'Booking Cancelled',
            'booking_checked_in' => 'Guest Checked In',
            'booking_checked_out' => 'Guest Checked Out',
            'booking_modified' => 'Booking Modified',
            'booking_adjustment' => 'Host Adjustment',
            'booking_note' => 'Host Note',
            'booking_auto_checked_out' => 'Auto Checked Out',
            'booking_auto_cancelled_no_show' => 'Auto Cancelled (No Show)',
            'booking_deposit_collected' => 'Deposit Collected',
            'booking_deposit_refunded' => 'Deposit Refunded',
            'booking_deposit_claimed' => 'Deposit Claimed',
        ];

        return $labels[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    public static function isValidActionType(string $type): bool
    {
        return in_array($type, self::actionTypes(), true);
    }

    public static function actionTypes(): array
    {
        return [
            'booking_created',
            'booking_confirmed',
            'booking_cancelled',
            'booking_checked_in',
            'booking_checked_out',
            'booking_modified',
            'booking_adjustment',
            'booking_note',
            'booking_auto_checked_out',
            'booking_auto_cancelled_no_show',
            'booking_deposit_collected',
            'booking_deposit_refunded',
            'booking_deposit_claimed',
        ];
    }
}
