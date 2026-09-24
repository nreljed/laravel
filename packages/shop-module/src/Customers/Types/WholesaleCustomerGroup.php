<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Customers\Types;

use Nreljed\ShopModule\Customers\CustomerGroupType;

final class WholesaleCustomerGroup extends CustomerGroupType
{
    public function code(): string
    {
        return 'wholesale';
    }

    public function label(): string
    {
        return 'Wholesale';
    }

    public function allowsBusinessPricing(): bool
    {
        return true;
    }
}