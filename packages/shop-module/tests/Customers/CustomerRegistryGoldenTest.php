<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Tests\Customers;

use Nreljed\ShopModule\Customers\CustomerContract;
use Nreljed\ShopModule\Customers\CustomerGroupContract;
use Nreljed\ShopModule\Customers\CustomerAddressContract;
use Nreljed\ShopModule\Customers\CustomerManager;
use PHPUnit\Framework\TestCase;

final class CustomerRegistryGoldenTest extends TestCase
{
    public function test_registry_registers_and_returns_customers_groups_addresses(): void
    {
        $manager = new CustomerManager();

        $customer = new class() implements CustomerContract {
            public function code(): string { return 'cus-001'; }
            public function email(): string { return 'ada@example.com'; }
            public function displayName(): string { return 'Ada Portal'; }
        };

        $group = new class() implements CustomerGroupContract {
            public function code(): string { return 'grp-retail'; }
            public function label(): string { return 'Retail'; }
            public function allowsBusinessPricing(): bool { return false; }
        };

        $address = new class() implements CustomerAddressContract {
            public function code(): string { return 'addr-home'; }
            public function type(): string { return 'billing'; }
            public function line1(): string { return '1 Portal Lane'; }
            public function line2(): string { return ''; }
            public function city(): string { return 'Wellington'; }
            public function postalCode(): string { return '6011'; }
            public function countryCode(): string { return 'NZ'; }
            public function isPrimary(): bool { return true; }
        };

        $manager->registerCustomer($customer);
        $manager->registerGroup($group);
        $manager->registerAddress($address);

        self::assertSame('cus-001', $manager->customer('cus-001')->code());
        self::assertSame('Ada Portal', $manager->customer('cus-001')->displayName());
        self::assertSame('grp-retail', $manager->group('grp-retail')->code());
        self::assertSame('Wellington', $manager->address('addr-home')->city());
        self::assertTrue($manager->address('addr-home')->isPrimary());
    }
}
