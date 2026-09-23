<?php

namespace Nreljed\ShopModule\Tests;

use Nreljed\ShopModule\Database\Factories\CartFactory;
use Nreljed\ShopModule\Database\Factories\CouponFactory;
use Nreljed\ShopModule\Database\Factories\OrderFactory;
use Nreljed\ShopModule\Database\Factories\ProductFactory;
use Nreljed\ShopModule\Models\Cart;
use Nreljed\ShopModule\Models\Coupon;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Models\Product;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Structural guard for the module-owned factories:
 *
 *   - every Eloquent model that uses HasFactory must resolve to a factory
 *     declared inside this package, so consumers never fall back to the app
 *     namespace or a missing global Database\Factories\* class.
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
            [Product::class, ProductFactory::class],
            [Order::class, OrderFactory::class],
            [Cart::class, CartFactory::class],
            [Coupon::class, CouponFactory::class],
        ];
    }

    /**
     * @param class-string $model
     * @param class-string $factory
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
     * @param class-string $model
     * @param class-string $factory
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