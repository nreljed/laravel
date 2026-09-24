<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Tax\Zones;

use Nreljed\ShopModule\Tax\TaxClassContract;
use Nreljed\ShopModule\Tax\TaxZoneContract;

final class UsZone implements TaxZoneContract
{
    public function code(): string
    {
        return 'us';
    }

    public function label(): string
    {
        return 'United States';
    }

    /**
     * @return array<int, string>
     */
    public function countries(): array
    {
        return ['US'];
    }

    /**
     * @return array<int, string>
     */
    public function states(): array
    {
        return ['CA', 'NY', 'TX'];
    }

    public function rateFor(TaxClassContract $class): float
    {
        return match ($class->code()) {
            'standard', 'reduced' => 0.0725,
            default => 0.0,
        };
    }
}
