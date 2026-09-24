<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Tax\Types;

use Nreljed\ShopModule\Tax\TaxClassType;

final class Zero extends TaxClassType
{
    public function code(): string
    {
        return 'zero';
    }

    public function label(): string
    {
        return 'Zero';
    }

    public function defaultRate(): float
    {
        return 0.0;
    }
}
