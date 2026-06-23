<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\Enums\BookingStatus;
use App\Modules\Booking\Gateways\WooCommercePaymentGateway;
use App\Modules\Booking\Models\Booking;
use Illuminate\Support\Facades\Log;

class BookingWooCommerceSyncService
{
    protected static function isWooCommerceAvailable(): bool
    {
        return config('booking.woocommerce.enabled', false);
    }

    /**
     * Handle WooCommerce order paid (completed or processing).
     */
    public static function onOrderPaid(int $orderId): void
    {
        if (! self::isWooCommerceAvailable()) {
            return;
        }

        $bookingId = self::getBookingIdFromWcOrder($orderId);
        if (! $bookingId) {
            return;
        }

        $booking = Booking::find($bookingId);
        if (! $booking) {
            return;
        }

        $paymentStatus = $booking->metadata['payment_status'] ?? '';
        if ($paymentStatus === 'paid') {
            return;
        }

        $meta = $booking->metadata ?? [];
        $meta['payment_status'] = 'paid';
        $meta['paid_date'] = now()->toDateString();
        $booking->update(['metadata' => $meta]);

        if ($booking->status === BookingStatus::Pending) {
            app(BookingStatusManager::class)->transition($booking, BookingStatus::Confirmed);
        }
    }

    /**
     * Handle WooCommerce order refunded — cancel the associated booking.
     */
    public static function onOrderRefunded(int $orderId): void
    {
        if (! self::isWooCommerceAvailable()) {
            return;
        }

        $bookingId = self::getBookingIdFromWcOrder($orderId);
        if (! $bookingId) {
            return;
        }

        $booking = Booking::find($bookingId);
        if (! $booking || $booking->status === BookingStatus::Cancelled) {
            return;
        }

        try {
            app(BookingStatusManager::class)->transition($booking, BookingStatus::Cancelled, 'host');
            $meta = $booking->metadata ?? [];
            $meta['wc_refund_processed'] = true;
            Booking::where('id', $bookingId)->update(['metadata' => $meta]);

            Log::info('[BookingWooCommerceSyncService] Booking cancelled via WooCommerce order refund', [
                'booking_id' => $bookingId,
                'order_id' => $orderId,
            ]);
        } catch (\Exception $e) {
            Log::error('[BookingWooCommerceSyncService] Failed to cancel booking via WC refund', [
                'booking_id' => $bookingId,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle WooCommerce order cancelled — cancel booking if still pending.
     */
    public static function onOrderCancelled(int $orderId): void
    {
        if (! self::isWooCommerceAvailable()) {
            return;
        }

        $bookingId = self::getBookingIdFromWcOrder($orderId);
        if (! $bookingId) {
            return;
        }

        $booking = Booking::find($bookingId);
        if (! $booking || $booking->status !== BookingStatus::Pending) {
            return;
        }

        try {
            app(BookingStatusManager::class)->transition($booking, BookingStatus::Cancelled, 'host');
        } catch (\Exception $e) {
            Log::error('[BookingWooCommerceSyncService] Failed to cancel booking via WC order cancel', [
                'booking_id' => $bookingId,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle booking status transition — sync back to WooCommerce order.
     */
    public static function onBookingStatusTransition(int $bookingId, string $oldStatus, string $newStatus): void
    {
        if ($newStatus === BookingStatus::Cancelled->value) {
            self::processWcRefundForBooking($bookingId);
        }
    }

    /**
     * Process WooCommerce refund when a booking is cancelled.
     */
    protected static function processWcRefundForBooking(int $bookingId): void
    {
        $booking = Booking::find($bookingId);
        if (! $booking) {
            return;
        }

        $meta = $booking->metadata ?? [];
        $alreadyProcessed = ! empty($meta['wc_refund_processed']);
        if ($alreadyProcessed) {
            return;
        }

        $paymentModel = $meta['payment_model_applied'] ?? '';
        if ($paymentModel !== 'woocommerce') {
            return;
        }

        $refundAmount = (float) ($meta['refund_amount'] ?? 0);
        if ($refundAmount <= 0) {
            return;
        }

        $gateway = new BookingPaymentGateway;
        $wcGateway = $gateway->getGatewayForBooking($bookingId);

        if ($wcGateway instanceof WooCommercePaymentGateway) {
            $meta['wc_refund_processed'] = true;
            Booking::where('id', $bookingId)->update(['metadata' => $meta]);

            $result = $wcGateway->processRefund($bookingId, $refundAmount);
            if (! empty($result['success'])) {
                Log::info('[BookingWooCommerceSyncService] Auto-processed WC refund', [
                    'booking_id' => $bookingId,
                    'amount' => $refundAmount,
                ]);
            } else {
                Log::error('[BookingWooCommerceSyncService] Failed to auto-process WC refund', [
                    'booking_id' => $bookingId,
                    'message' => $result['message'] ?? 'unknown error',
                ]);
            }
        }
    }

    /**
     * Extract booking ID from a WooCommerce order.
     */
    protected static function getBookingIdFromWcOrder(int $orderId): ?int
    {
        if (! self::isWooCommerceAvailable()) {
            return null;
        }

        $bookingId = (int) SettingsService::get('wc_order_booking_map_'.$orderId, 0);

        return $bookingId ?: null;
    }

    /**
     * Sync booking date changes to the associated WooCommerce order.
     */
    public static function syncBookingDatesToOrder(int $bookingId): void
    {
        if (! self::isWooCommerceAvailable()) {
            return;
        }

        $booking = Booking::find($bookingId);
        if (! $booking) {
            return;
        }

        $meta = $booking->metadata ?? [];
        $orderId = $meta['woocommerce_order_id'] ?? null;
        if (! $orderId) {
            return;
        }

        Log::info('[BookingWooCommerceSyncService] Booking dates changed, WC order may need update', [
            'booking_id' => $bookingId,
            'order_id' => $orderId,
            'check_in' => $booking->start_date?->toDateString(),
            'check_out' => $booking->end_date?->toDateString(),
        ]);
    }
}
