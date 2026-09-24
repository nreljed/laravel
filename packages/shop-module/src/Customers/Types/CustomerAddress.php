<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Customers\Types;

use Nreljed\ShopModule\Customers\CustomerAddressType;

final class CustomerAddress extends CustomerAddressType
{
    public function __construct(
        private readonly string $code,
        private readonly string $type,
        private readonly string $line1,
        private readonly string $line2,
        private readonly string $city,
        private readonly string $postalCode,
        private readonly string $countryCode,
        private readonly bool $isPrimary = false,
    ) {
    }

    public function code(): string
    {
        return $this->code;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function line1(): string
    {
        return $this->line1;
    }

    public function line2(): string
    {
        return $this->line2;
    }

    public function city(): string
    {
        return $this->city;
    }

    public function postalCode(): string
    {
        return $this->postalCode;
    }

    public function countryCode(): string
    {
        return $this->countryCode;
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }
}