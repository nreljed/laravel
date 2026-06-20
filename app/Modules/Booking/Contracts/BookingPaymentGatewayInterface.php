<?php

namespace App\Modules\Booking\Contracts;

interface BookingPaymentGatewayInterface
{
    /**
     * Process payment for a booking.
     *
     * @return array{status: string, message: string, order_id?: int, checkout_url?: string}
     */
    public function processPayment(int $bookingId): array;

    /**
     * Get the current payment status for a booking.
     */
    public function getPaymentStatus(int $bookingId): string;

    /**
     * Get a checkout/payment URL if one exists.
     */
    public function getCheckoutUrl(int $bookingId): ?string;

    /**
     * Get the external order/transaction ID associated with this booking.
     */
    public function getOrderId(int $bookingId): ?int;

    /**
     * Process a refund for the given amount.
     *
     * @return array{success: bool, message: string, refund_id?: string}
     */
    public function processRefund(int $bookingId, float $amount): array;

    /**
     * Unique identifier for this gateway (e.g., 'woocommerce', 'offline').
     */
    public static function getGatewayId(): string;

    /**
     * Human-readable name for this gateway (e.g., 'WooCommerce', 'Offline / Cash').
     */
    public static function getGatewayName(): string;
}
