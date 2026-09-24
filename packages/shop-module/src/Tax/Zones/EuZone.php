<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Tax\Zones;

use Nreljed\ShopModule\Tax\TaxClassContract;
use Nreljed\ShopModule\Tax\TaxZoneContract;

final class EuZone implements TaxZoneContract
{
    public function code(): string
    {
        return 'eu';
    }

    public function label(): string
    {
        return 'European Union';
    }

    /**
     * @return array<int, string>
     */
    public function countries(): array
    {
        return ['DE', 'FR', 'ES', 'IT', 'NL'];
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
            'standard' => 0.19,
            'reduced' => 0.07,
            default => 0.0,
        };
    }
}
