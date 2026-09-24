<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Customers;

interface CustomerAddressContract
{
    public function code(): string;

    public function type(): string;

    public function line1(): string;

    public function line2(): string;

    public function city(): string;

    public function postalCode(): string;

    public function countryCode(): string;

    public function isPrimary(): bool;
}
