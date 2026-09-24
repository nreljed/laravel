<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Tax\Types;

use Nreljed\ShopModule\Tax\TaxClassType;

final class Standard extends TaxClassType
{
    public function code(): string
    {
        return 'standard';
    }

    public function label(): string
    {
        return 'Standard';
    }

    public function defaultRate(): float
    {
        return 0.19;
    }
}
