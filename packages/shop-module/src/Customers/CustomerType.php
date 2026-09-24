<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Customers;

abstract class CustomerType implements CustomerContract
{
    abstract public function code(): string;

    abstract public function label(): string;

    abstract public function allowsBusinessPricing(): bool;
}
