<?php

namespace Nreljed\ShopModule\Tests;

use InvalidArgumentException;
use LogicException;
use Nreljed\ShopModule\Contracts\PaymentDriver;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Payments\ManualPaymentDriver;
use Nreljed\ShopModule\Services\PaymentManager;
use PHPUnit\Framework\TestCase;

/**
 * PaymentManager registry + driver contracts (PR 3).
 *
 * DB-free: the manager is constructed with an explicit config array and a
 * resolver Closure, so no container/service-locator calls escape into the
 * framework fake.
 */
final class PaymentManagerTest extends TestCase
{
    public function test_resolves_driver_by_name_through_the_resolver(): void
    {
        $manager = $this->makeManager(['drivers' => [
            'stripe' => FakeDriver::class,
        ]]);

        $driver = $manager->driver('stripe');

        $this->assertInstanceOf(FakeDriver::class, $driver);
        $this->assertSame(FakeDriver::class, $driver->name());
    }

    public function test_resolves_default_driver_when_name_omitted(): void
    {
        $manager = $this->makeManager([
            'default' => 'manual',
            'drivers' => ['manual' => FakeDriver::class],
        ]);

        $this->assertInstanceOf(FakeDriver::class, $manager->driver());
    }

    public function test_drivers_are_resolved_once_and_cached(): void
    {
        $resolved = [];
        $manager = new PaymentManager(
            ['default' => 'stripe', 'drivers' => ['stripe' => FakeDriver::class]],
            function (string $class) use (&$resolved) {
                $resolved[] = $class;

                return new FakeDriver('stripe');
            },
        );

        $manager->driver();
        $manager->driver('stripe');

        $this->assertCount(1, $resolved);
        $this->assertSame($manager->driver('stripe'), $manager->driver());
    }

    public function test_unknown_driver_throws(): void
    {
        $manager = $this->makeManager(['drivers' => []]);

        $this->expectException(InvalidArgumentException::class);

        $manager->driver('paypal');
    }

    public function test_default_falls_back_to_manual(): void
    {
        $this->assertSame('manual', $this->makeManager([])->default());
    }

    public function test_has_reports_configured_drivers(): void
    {
        $manager = $this->makeManager(['drivers' => ['stripe' => FakeDriver::class]]);

        $this->assertTrue($manager->has('stripe'));
        $this->assertFalse($manager->has('paypal'));
    }

    public function test_drivers_returns_config_map(): void
    {
        $manager = $this->makeManager(['drivers' => ['stripe' => FakeDriver::class]]);

        $this->assertSame(['stripe' => FakeDriver::class], $manager->drivers());
    }

    public function test_manual_driver_is_always_available_without_a_key(): void
    {
        $driver = new ManualPaymentDriver;

        $this->assertTrue($driver->isAvailable());
        $this->assertNull($driver->getPublishableKey());
        $this->assertSame('manual', $driver->name());
    }

    public function test_manual_driver_rejects_intents_and_webhooks(): void
    {
        $driver = new ManualPaymentDriver;
        $order = new Order(['id' => 1, 'total' => 12.5, 'currency' => 'EUR']);

        try {
            $driver->createPaymentIntent($order, 'EUR');
            $this->fail('Expected LogicException for manual intents.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }

        try {
            $driver->verifyWebhook('payload', 'sig');
            $this->fail('Expected LogicException for manual webhooks.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }
    }

    public function test_manual_driver_refund_is_a_local_noop(): void
    {
        $driver = new ManualPaymentDriver;
        $order = new Order(['id' => 1, 'payment_intent_id' => null]);

        $driver->refund($order);

        $this->addToAssertionCount(1);
    }

    /*
    |--------------------------------------------------------------------------
    | Fixtures
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array{default?: string, drivers?: array<string, class-string>}  $config
     */
    private function makeManager(array $config): PaymentManager
    {
        return new PaymentManager(
            $config,
            fn (string $class) => new FakeDriver($class),
        );
    }
}

final class FakeDriver implements PaymentDriver
{
    public function __construct(
        protected string $driverName,
    ) {}

    public function name(): string
    {
        return $this->driverName;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function getPublishableKey(): ?string
    {
        return 'pk_test_fake';
    }

    public function createPaymentIntent(Order $order, string $currency = 'eur'): \Nreljed\ShopModule\Payments\PaymentIntentResult
    {
        return new \Nreljed\ShopModule\Payments\PaymentIntentResult(
            id: 'pi_fake',
            clientSecret: 'pi_fake_secret_abc',
        );
    }

    public function verifyPayment(string $paymentReference, Order $order): \Nreljed\ShopModule\Payments\PaymentVerification
    {
        return \Nreljed\ShopModule\Payments\PaymentVerification::paid();
    }

    public function verifyWebhook(string $payload, string $signature): array
    {
        return ['type' => 'payment_intent.succeeded'];
    }

    public function refund(Order $order, ?float $amount = null): void
    {
        // Fake gateway: no-op.
    }
}