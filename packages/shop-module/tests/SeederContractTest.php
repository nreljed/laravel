<?php

namespace Nreljed\ShopModule\Tests;

use Nreljed\ShopModule\Database\Seeders\CurrencySeeder;
use Nreljed\ShopModule\Models\Currency;
use Nreljed\ShopModule\ShopServiceProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Structural guard for the module-owned baseline seeder:
 *
 *   - CurrencySeeder must live in the package namespace and target the
 *     package's own Currency model (not raw DB::table against an app table).
 *   - The package must not ship demo-tier seeders or commands that shell out
 *     to app-namespace seeders (package → app inversion).
 */
final class SeederContractTest extends TestCase
{
    public function test_currency_seeder_exists_in_package_namespace(): void
    {
        $this->assertTrue(class_exists(CurrencySeeder::class));
    }

    public function test_currency_seeder_uses_the_package_currency_model(): void
    {
        $source = (string) file_get_contents((new ReflectionClass(CurrencySeeder::class))->getFileName());

        $this->assertStringContainsString('use '.Currency::class.';', $source);
        $this->assertStringContainsString('Currency::updateOrCreate', $source);
        $this->assertStringNotContainsString("DB::table('currencies')", $source);
    }

    public function test_currency_seeder_is_idempotent(): void
    {
        $source = (string) file_get_contents((new ReflectionClass(CurrencySeeder::class))->getFileName());

        $this->assertStringContainsString('updateOrCreate', $source);
    }

    public function test_package_does_not_depend_on_app_namespace_seeders(): void
    {
        $providerSource = (string) file_get_contents((new ReflectionClass(ShopServiceProvider::class))->getFileName());

        $this->assertStringNotContainsString('ShopProductSeed', $providerSource);
        $this->assertStringNotContainsString('\\Database\\Seeders\\', $providerSource);
    }
}