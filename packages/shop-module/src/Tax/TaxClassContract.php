<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Tax;

interface TaxClassContract
{
    public function code(): string;

    public function label(): string;

    public function defaultRate(): float;
}
