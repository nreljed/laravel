<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\Contracts\BookingPaymentGatewayInterface;
use App\Modules\Booking\Gateways\WooCommercePaymentGateway;
use App\Modules\Booking\Gateways\OfflinePaymentGateway;

class BookingPaymentGateway
{
    /** @var array<string, class-string<BookingPaymentGatewayInterface>> */
    protected static array $gateways = [];

    protected bool $wooCommerceEnabled;

    public function __construct()
    {
        $this->wooCommerceEnabled = config('booking.woocommerce.enabled', false);
        self::registerDefaultGateways();
    }

    /**
     * @param class-string<BookingPaymentGatewayInterface> $gatewayClass
     */
    public static function registerGateway(string $gatewayClass): void
    {
        if (is_subclass_of($gatewayClass, BookingPaymentGatewayInterface::class)) {
            self::$gateways[$gatewayClass::getGatewayId()] = $gatewayClass;
        }
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public static function getAvailableGateways(): array
    {
        self::registerDefaultGateways();
        $list = [];
        foreach (self::$gateways as $id => $class) {
            $list[] = [
                'id' => $id,
                'name' => $class::getGatewayName(),
            ];
        }
        return $list;
    }

    public function getGatewayForBooking(int $bookingId): BookingPaymentGatewayInterface
    {
        self::registerDefaultGateways();

        if ($this->wooCommerceEnabled && isset(self::$gateways['woocommerce'])) {
            $class = self::$gateways['woocommerce'];
            return new $class();
        }

        return new OfflinePaymentGateway();
    }

    /**
     * @return array<string, mixed>
     */
    public function processPayment(int $bookingId): array
    {
        $gateway = $this->getGatewayForBooking($bookingId);
        $result = $gateway->processPayment($bookingId);
        $result['gateway'] = $gateway::getGatewayId();
        return $result;
    }

    public function getPaymentStatus(int $bookingId): string
    {
        $gateway = $this->getGatewayForBooking($bookingId);
        return $gateway->getPaymentStatus($bookingId);
    }

    public function getCheckoutUrl(int $bookingId): ?string
    {
        $gateway = $this->getGatewayForBooking($bookingId);
        return $gateway->getCheckoutUrl($bookingId);
    }

    public function getOrderId(int $bookingId): ?int
    {
        $gateway = $this->getGatewayForBooking($bookingId);
        return $gateway->getOrderId($bookingId);
    }

    protected static function registerDefaultGateways(): void
    {
        if (empty(self::$gateways)) {
            self::registerGateway(WooCommercePaymentGateway::class);
            self::registerGateway(OfflinePaymentGateway::class);
        }
    }
}
