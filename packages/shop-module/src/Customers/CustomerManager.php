<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Customers;

use RuntimeException;

final class CustomerManager
{
    /** @var array<string, CustomerContract> */
    private array $customers = [];

    /** @var array<string, CustomerGroupContract> */
    private array $groups = [];

    /** @var array<string, CustomerAddressContract> */
    private array $addresses = [];

    public function registerCustomer(CustomerContract $customer): void
    {
        $this->customers[$customer->code()] = $customer;
    }

    public function registerGroup(CustomerGroupContract $group): void
    {
        $this->groups[$group->code()] = $group;
    }

    public function registerAddress(CustomerAddressContract $address): void
    {
        $this->addresses[$address->code()] = $address;
    }

    public function customer(string $code): CustomerContract
    {
        if (!isset($this->customers[$code])) {
            throw new RuntimeException(sprintf('Unknown customer "%s".', $code));
        }

        return $this->customers[$code];
    }

    public function group(string $code): CustomerGroupContract
    {
        if (!isset($this->groups[$code])) {
            throw new RuntimeException(sprintf('Unknown customer group "%s".', $code));
        }

        return $this->groups[$code];
    }

    public function address(string $code): CustomerAddressContract
    {
        if (!isset($this->addresses[$code])) {
            throw new RuntimeException(sprintf('Unknown customer address "%s".', $code));
        }

        return $this->addresses[$code];
    }

    public function customerCodes(): array
    {
        return array_keys($this->customers);
    }

    public function groupCodes(): array
    {
        return array_keys($this->groups);
    }

    public function addressCodes(): array
    {
        return array_keys($this->addresses);
    }
}
