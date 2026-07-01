<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\Models\Booking;

class BookingHostAdjustmentService
{
    public static function getAdjustments(Booking $booking): array
    {
        $meta = $booking->metadata ?? [];

        return [
            'adjusted_check_in_time' => $meta['adjusted_check_in_time'] ?? null,
            'adjusted_check_out_time' => $meta['adjusted_check_out_time'] ?? null,
            'host_notes' => $meta['host_notes'] ?? '',
        ];
    }

    public static function saveAdjustments(Booking $booking, array $data, int $actorId): array
    {
        $old = self::getAdjustments($booking);
        $meta = $booking->metadata ?? [];
        $changes = [];

        if (array_key_exists('adjusted_check_in_time', $data)) {
            $value = $data['adjusted_check_in_time'] ? trim($data['adjusted_check_in_time']) : null;
            if ($value !== $old['adjusted_check_in_time']) {
                $meta['adjusted_check_in_time'] = $value;
                $changes['adjusted_check_in_time'] = ['old' => $old['adjusted_check_in_time'], 'new' => $value];
            }
        }

        if (array_key_exists('adjusted_check_out_time', $data)) {
            $value = $data['adjusted_check_out_time'] ? trim($data['adjusted_check_out_time']) : null;
            if ($value !== $old['adjusted_check_out_time']) {
                $meta['adjusted_check_out_time'] = $value;
                $changes['adjusted_check_out_time'] = ['old' => $old['adjusted_check_out_time'], 'new' => $value];
            }
        }

        if (array_key_exists('host_notes', $data)) {
            $value = trim($data['host_notes']);
            if ($value !== $old['host_notes']) {
                $meta['host_notes'] = $value;
                $changes['host_notes'] = ['old' => $old['host_notes'], 'new' => $value];
            }
        }

        if (! empty($changes)) {
            $booking->update(['metadata' => $meta]);

            BookingActionService::logAdjustment(
                $booking,
                $old,
                self::getAdjustments($booking),
                $actorId,
            );
        }

        return self::getAdjustments($booking);
    }

    public static function getEffectiveCheckInTime(Booking $booking, string $defaultTime = '15:00'): string
    {
        $adjusted = $booking->metadata['adjusted_check_in_time'] ?? null;

        return $adjusted ?: $defaultTime;
    }

    public static function getEffectiveCheckOutTime(Booking $booking, string $defaultTime = '11:00'): string
    {
        $adjusted = $booking->metadata['adjusted_check_out_time'] ?? null;

        return $adjusted ?: $defaultTime;
    }
}
