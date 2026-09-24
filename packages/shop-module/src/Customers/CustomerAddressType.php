<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Customers;

abstract class CustomerAddressType implements CustomerAddressContract
{
    abstract public function code(): string;

    abstract public function type(): string;

    abstract public function line1(): string;

    abstract public function line2(): string;

    abstract public function city(): string;

    abstract public function postalCode(): string;

    abstract public function countryCode(): string;

    abstract public function isPrimary(): bool;
}
