<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Tax;

abstract class TaxClassType implements TaxClassContract
{
    abstract public function code(): string;

    abstract public function label(): string;

    abstract public function defaultRate(): float;
}
