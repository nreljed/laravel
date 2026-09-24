<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Tax\Types;

use Nreljed\ShopModule\Tax\TaxClassType;

final class Reduced extends TaxClassType
{
    public function code(): string
    {
        return 'reduced';
    }

    public function label(): string
    {
        return 'Reduced';
    }

    public function defaultRate(): float
    {
        return 0.07;
    }
}
