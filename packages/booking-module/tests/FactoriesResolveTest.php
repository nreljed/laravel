<?php

namespace Nreljed\BookingModule\Tests;

use Nreljed\BookingModule\Booking\Models\Booking;
use Nreljed\BookingModule\Database\Factories\BookingFactory;
use Nreljed\BookingModule\Database\Factories\PropertyFactory;
use Nreljed\BookingModule\Properties\Models\Property;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Structural guard for the module-owned factories:
 *
 *   - every Eloquent model that uses HasFactory must resolve to a factory
 *     declared inside this package (not the non-existent global
 *     Database\Factories\* namespace).
 *   - each factory must declare the matching model as its $model target.
 */
final class FactoriesResolveTest extends TestCase
{
    /**
     * @return array<int, array{0: class-string, 1: class-string}>
     */
    public static function modelsWithFactories(): array
    {
        return [
            [Booking::class, BookingFactory::class],
            [Property::class, PropertyFactory::class],
        ];
    }

    /**
     * @param  class-string  $model
     * @param  class-string  $factory
     */
    #[DataProvider('modelsWithFactories')]
    public function test_model_resolves_to_package_factory(string $model, string $factory): void
    {
        $method = new ReflectionMethod($model, 'newFactory');
        $method->setAccessible(true);

        $resolved = $method->invoke(null);

        $this->assertInstanceOf($factory, $resolved, sprintf(
            '%s::newFactory() must return %s, the package-owned factory.',
            $model,
            $factory
        ));
    }

    /**
     * @param  class-string  $model
     * @param  class-string  $factory
     */
    #[DataProvider('modelsWithFactories')]
    public function test_factory_targets_matching_model(string $model, string $factory): void
    {
        $reflection = new ReflectionClass($factory);

        $property = $reflection->getProperty('model');
        $property->setAccessible(true);

        $target = $property->getValue($reflection->newInstanceWithoutConstructor());

        $this->assertSame($model, $target, sprintf('%s must target %s.', $factory, $model));
    }
}
