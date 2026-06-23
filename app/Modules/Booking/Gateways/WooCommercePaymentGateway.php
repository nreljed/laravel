<?php

namespace App\Modules\Booking\Gateways;

use App\Models\Booking;
use App\Modules\Booking\Contracts\BookingPaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WooCommercePaymentGateway implements BookingPaymentGatewayInterface
{
    protected ?string $apiUrl;

    protected ?string $consumerKey;

    protected ?string $consumerSecret;

    public function __construct()
    {
        $this->apiUrl = config('services.woocommerce.api_url');
        $this->consumerKey = config('services.woocommerce.consumer_key');
        $this->consumerSecret = config('services.woocommerce.consumer_secret');
    }

    public function processPayment(int $bookingId): array
    {
        $booking = Booking::findOrFail($bookingId);
        $total = (float) ($booking->booking_total_price ?? 0);

        if ($total <= 0) {
            $booking->booking_payment_status = 'paid';
            $booking->save();

            return ['status' => 'paid', 'message' => 'No payment required'];
        }

        if (! $this->isAvailable()) {
            $booking->booking_payment_status = 'failed';
            $booking->save();

            return ['status' => 'failed', 'message' => 'WooCommerce API is not configured'];
        }

        return $this->createWooCommerceOrder($booking, $total);
    }

    public function getPaymentStatus(int $bookingId): string
    {
        $booking = Booking::find($bookingId);

        return ($booking?->booking_payment_status) ?: 'unpaid';
    }

    public function getCheckoutUrl(int $bookingId): ?string
    {
        $orderId = $this->getOrderId($bookingId);

        if ($orderId) {
            return $this->getWooCommerceCheckoutUrl($orderId);
        }

        return null;
    }

    public function getOrderId(int $bookingId): ?int
    {
        $booking = Booking::find($bookingId);

        $orderId = $booking?->booking_woocommerce_order_id;

        return $orderId ? (int) $orderId : null;
    }

    public function processRefund(int $bookingId, float $amount): array
    {
        $orderId = $this->getOrderId($bookingId);

        if (! $orderId) {
            return ['success' => false, 'message' => 'No WooCommerce order found for this booking'];
        }

        try {
            $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->post("{$this->apiUrl}/orders/{$orderId}/refunds", [
                    'amount' => (string) $amount,
                    'reason' => sprintf('Booking #%d cancellation refund', $bookingId),
                ]);

            if ($response->failed()) {
                return ['success' => false, 'message' => $response->json('message', 'WooCommerce refund failed')];
            }

            return [
                'success' => true,
                'message' => 'Refund processed via WooCommerce',
                'refund_id' => (string) $response->json('id'),
            ];
        } catch (\Exception $e) {
            Log::error('WooCommerce refund failed', ['booking_id' => $bookingId, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function getGatewayId(): string
    {
        return 'woocommerce';
    }

    public static function getGatewayName(): string
    {
        return 'WooCommerce';
    }

    protected function isAvailable(): bool
    {
        return ! empty($this->apiUrl) && ! empty($this->consumerKey) && ! empty($this->consumerSecret);
    }

    protected function createWooCommerceOrder(Booking $booking, float $total): array
    {
        $existingOrderId = $booking->booking_woocommerce_order_id;

        if ($existingOrderId) {
            $existingStatus = $this->getWooCommerceOrderStatus($existingOrderId);

            if ($existingStatus && ! in_array($existingStatus, ['cancelled', 'failed', 'refunded'], true)) {
                return ['status' => 'exists', 'message' => 'Order already exists', 'order_id' => (int) $existingOrderId];
            }
        }

        $productId = $this->getBookingProductId();

        if (! $productId) {
            $booking->booking_payment_status = 'failed';
            $booking->save();

            return ['status' => 'failed', 'message' => 'Booking product not found in WooCommerce'];
        }

        try {
            $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->post("{$this->apiUrl}/orders", [
                    'payment_method' => 'woocommerce_payments',
                    'payment_method_title' => 'Card Payment',
                    'set_paid' => false,
                    'line_items' => [
                        [
                            'product_id' => $productId,
                            'quantity' => 1,
                            'total' => (string) $total,
                        ],
                    ],
                    'customer_note' => sprintf(
                        'Booking #%d — %s',
                        $booking->id,
                        $booking->booking_property_title ?? 'Property'
                    ),
                    'billing' => [
                        'email' => $booking->booking_guest_email ?? '',
                        'first_name' => $booking->booking_guest_name ?? '',
                    ],
                    'meta_data' => [
                        ['key' => '_booking_id', 'value' => (string) $booking->id],
                        ['key' => '_booking_check_in', 'value' => $booking->booking_check_in_date ?? ''],
                        ['key' => '_booking_check_out', 'value' => $booking->booking_check_out_date ?? ''],
                        ['key' => '_booking_cleaning_fee', 'value' => (string) ($booking->booking_cleaning_fee ?? 0)],
                    ],
                    'status' => 'pending',
                ]);

            if ($response->failed()) {
                $booking->booking_payment_status = 'failed';
                $booking->save();

                return ['status' => 'failed', 'message' => $response->json('message', 'Failed to create WooCommerce order')];
            }

            $orderData = $response->json();
            $orderId = $orderData['id'];

            $booking->booking_woocommerce_order_id = $orderId;
            $booking->booking_payment_status = 'pending';
            $booking->save();

            return ['status' => 'pending', 'message' => 'WooCommerce order created', 'order_id' => $orderId];
        } catch (\Exception $e) {
            Log::error('WooCommerce order creation failed', ['booking_id' => $booking->id, 'error' => $e->getMessage()]);

            $booking->booking_payment_status = 'failed';
            $booking->save();

            return ['status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    protected function getWooCommerceOrderStatus(int $orderId): ?string
    {
        try {
            $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->get("{$this->apiUrl}/orders/{$orderId}");

            if ($response->successful()) {
                return $response->json('status');
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch WooCommerce order status', ['order_id' => $orderId]);
        }

        return null;
    }

    protected function getWooCommerceCheckoutUrl(int $orderId): ?string
    {
        try {
            $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->get("{$this->apiUrl}/orders/{$orderId}");

            if ($response->successful()) {
                return $response->json('checkout_payment_url');
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch WooCommerce checkout URL', ['order_id' => $orderId]);
        }

        return null;
    }

    protected function getBookingProductId(): int
    {
        $productId = (int) config('booking.woocommerce_product_id', 0);

        if ($productId) {
            try {
                $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                    ->get("{$this->apiUrl}/products/{$productId}");

                if ($response->successful()) {
                    return $productId;
                }
            } catch (\Exception $e) {
                Log::warning('WooCommerce product fetch failed', ['product_id' => $productId]);
            }
        }

        try {
            $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->get("{$this->apiUrl}/products", [
                    'search' => 'Booking Fee',
                    'per_page' => 1,
                ]);

            if ($response->successful() && ! empty($response->json())) {
                return (int) $response->json()[0]['id'];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to search for Booking Fee product');
        }

        return 0;
    }
}
