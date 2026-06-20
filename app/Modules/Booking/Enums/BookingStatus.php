<?php

namespace App\Modules\Booking\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::CheckedIn => 'Checked In',
            self::CheckedOut => 'Checked Out',
            self::Cancelled => 'Cancelled',
            self::NoShow => 'No Show',
            self::Completed => 'Completed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'secondary',
            self::Confirmed => 'default',
            self::CheckedIn => 'info',
            self::CheckedOut => 'outline',
            self::Cancelled => 'destructive',
            self::NoShow => 'destructive',
            self::Completed => 'default',
        };
    }

    public static function activeStatuses(): array
    {
        return [self::Pending, self::Confirmed, self::CheckedIn];
    }

    public static function terminalStatuses(): array
    {
        return [self::Cancelled, self::Completed, self::NoShow];
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Pending => in_array($target, [self::Confirmed, self::Cancelled], true),
            self::Confirmed => in_array($target, [self::CheckedIn, self::Cancelled, self::NoShow], true),
            self::CheckedIn => in_array($target, [self::CheckedOut, self::Cancelled], true),
            self::CheckedOut => in_array($target, [self::Completed, self::Cancelled], true),
            self::NoShow => $target === self::Cancelled,
            self::Cancelled, self::Completed => false,
        };
    }

    public static function values(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }
}
