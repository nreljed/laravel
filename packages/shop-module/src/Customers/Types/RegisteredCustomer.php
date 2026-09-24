<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Customers\Types;

use Nreljed\ShopModule\Customers\CustomerType;

final class RegisteredCustomer extends CustomerType
{
    public function __construct(
        private readonly string $code,
        private readonly string $email,
        private readonly string $displayName,
    ) {
    }

    public function code(): string
    {
        return $this->code;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function displayName(): string
    {
        return $this->displayName;
    }

    public function label(): string
    {
        return 'Registered customer';
    }

    public function allowsBusinessPricing(): bool
    {
        return false;
    }
}