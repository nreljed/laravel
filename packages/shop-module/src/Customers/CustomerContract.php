<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Customers;

interface CustomerContract
{
    public function code(): string;

    public function email(): string;

    public function displayName(): string;
}
