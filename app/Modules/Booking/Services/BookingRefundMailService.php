<?php

namespace App\Modules\Booking\Services;

use App\Modules\BookableRegistry;
use App\Modules\Booking\Models\Booking;
use App\Services\MailService;

class BookingRefundMailService
{
    protected static function getMailService(): MailService
    {
        return app(MailService::class);
    }

    /**
     * @param  array{refund_percent: int, refund_amount: float, policy_applied: string, reason: string}  $refund
     */
    public static function sendRefundNotification(int $bookingId, array $refund): void
    {
        $booking = Booking::find($bookingId);
        if (! $booking || ! $booking->guest_email) {
            return;
        }

        $property = $booking->property_id ? app(BookableRegistry::class)->resolve('properties', $booking->property_id) : null;

        $data = [
            'bookingId' => $bookingId,
            'guestName' => $booking->guest_name ?: 'Guest',
            'guestEmail' => $booking->guest_email,
            'propertyTitle' => $property?->name ?? 'Property',
            'checkIn' => $booking->start_date?->toDateString(),
            'checkOut' => $booking->end_date?->toDateString(),
            'refundPercent' => $refund['refund_percent'],
            'refundAmount' => $refund['refund_amount'],
            'policyApplied' => $refund['policy_applied'],
            'reason' => $refund['reason'],
            'siteName' => config('app.name'),
        ];

        $mailService = self::getMailService();
        $mailService->send(
            $booking->guest_email,
            sprintf('[%s] Booking Cancelled #%d — Refund Summary', $data['siteName'], $bookingId),
            'booking-refund',
            $data
        );

        $meta = $booking->metadata ?? [];
        $meta['email_refund_sent'] = now()->toDateTimeString();
        $booking->update(['metadata' => $meta]);
    }
}
