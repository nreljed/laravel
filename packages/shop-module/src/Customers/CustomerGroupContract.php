<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Customers;

interface CustomerGroupContract
{
    public function code(): string;

    public function label(): string;

    public function allowsBusinessPricing(): bool;
}
