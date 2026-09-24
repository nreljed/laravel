<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Tax\Zones;

use Nreljed\ShopModule\Tax\TaxClassContract;
use Nreljed\ShopModule\Tax\TaxZoneContract;

final class NzZone implements TaxZoneContract
{
    public function code(): string
    {
        return 'nz';
    }

    public function label(): string
    {
        return 'New Zealand';
    }

    /**
     * @return array<int, string>
     */
    public function countries(): array
    {
        return ['NZ'];
    }

    /**
     * @return array<int, string>
     */
    public function states(): array
    {
        return [];
    }

    public function rateFor(TaxClassContract $class): float
    {
        return match ($class->code()) {
            'standard', 'snapshot' => 0.15,
            default => 0.0,
        };
    }
}
