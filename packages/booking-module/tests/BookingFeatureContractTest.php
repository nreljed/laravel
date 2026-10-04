<?php

namespace Nreljed\BookingModule\Tests;

use Nreljed\BookingModule\Booking\BookingServiceProvider;
use Nreljed\BookingModule\Properties\PropertiesServiceProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Feature dependency contract for the booking module:
 *
 *   - BookingServiceProvider consumes Properties models/services directly, so it
 *     must declare the `properties` feature dependency (checked by the platform
 *     FeatureRegistry before enabling a feature).
 *   - the dependency must resolve to a provider that is registered by this
 *     package, so the signature survives renames.
 */
final class BookingFeatureContractTest extends TestCase
{
    public function test_booking_declares_properties_dependency(): void
    {
        $this->assertSame(
            ['properties'],
            $this->bookingProvider()->dependencies(),
            'BookingServiceProvider must declare the "properties" feature dependency.'
        );
    }

    public function test_properties_provider_matches_the_declared_slug(): void
    {
        $provider = new ReflectionClass(PropertiesServiceProvider::class);

        $this->assertTrue(
            $provider->implementsInterface(\Nreljed\Platform\Contracts\FeatureProvider::class),
            'PropertiesServiceProvider must implement the FeatureProvider contract.'
        );

        $this->assertSame(
            'properties',
            $this->propertiesProvider()->slug(),
            'The property provider slug must stay "properties" (BookingServiceProvider depends on it).'
        );
    }

    private function bookingProvider(): BookingServiceProvider
    {
        $reflection = new ReflectionClass(BookingServiceProvider::class);

        /** @var BookingServiceProvider $provider */
        return $reflection->newInstanceWithoutConstructor();
    }

    private function propertiesProvider(): PropertiesServiceProvider
    {
        $reflection = new ReflectionClass(PropertiesServiceProvider::class);

        /** @var PropertiesServiceProvider $provider */
        return $reflection->newInstanceWithoutConstructor();
    }
}