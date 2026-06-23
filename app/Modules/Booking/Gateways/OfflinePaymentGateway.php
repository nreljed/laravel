<?php

namespace App\Modules\Booking\Gateways;

use App\Models\Booking;
use App\Modules\Booking\Contracts\BookingPaymentGatewayInterface;

class OfflinePaymentGateway implements BookingPaymentGatewayInterface
{
    public function processPayment(int $bookingId): array
    {
        $booking = Booking::findOrFail($bookingId);
        $total = (float) ($booking->booking_total_price ?? 0);

        if ($total <= 0) {
            $booking->booking_payment_status = 'paid';
            $booking->booking_payment_model_applied = 'offline';
            $booking->save();

            return ['status' => 'paid', 'message' => 'No payment required'];
        }

        $booking->booking_payment_status = 'pending';
        $booking->booking_payment_model_applied = 'offline';
        $booking->save();

        return [
            'status' => 'pending',
            'message' => 'Payment pending — collect at property',
        ];
    }

    public function getPaymentStatus(int $bookingId): string
    {
        $booking = Booking::find($bookingId);

        return ($booking?->booking_payment_status) ?: 'unpaid';
    }

    public function getCheckoutUrl(int $bookingId): ?string
    {
        return null;
    }

    public function getOrderId(int $bookingId): ?int
    {
        return null;
    }

    public function processRefund(int $bookingId, float $amount): array
    {
        $booking = Booking::findOrFail($bookingId);
        $currentStatus = $this->getPaymentStatus($bookingId);

        if (in_array($currentStatus, ['paid', 'partially_refunded'], true)) {
            $total = (float) ($booking->booking_total_price ?? 0);
            $booking->booking_payment_status = $amount >= $total
                ? 'refunded'
                : 'partially_refunded';
            $booking->save();

            return ['success' => true, 'message' => 'Offline payment refund recorded'];
        }

        return ['success' => false, 'message' => 'No paid offline payment to refund'];
    }

    public static function getGatewayId(): string
    {
        return 'offline';
    }

    public static function getGatewayName(): string
    {
        return 'Offline / Cash';
    }
}
