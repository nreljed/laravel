<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Tax;

interface TaxZoneContract
{
    public function code(): string;

    public function label(): string;

    public function countries(): array;

    public function states(): array;

    public function rateFor(TaxClassContract $class): float;
}
