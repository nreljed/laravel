<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Tax;

final class PerLineTaxResolver
{
    public function __construct(
        private readonly TaxClassManager $classes,
        private readonly TaxZoneManager $zones,
    ) {
    }

    public static function defaults(): self
    {
        return new self(TaxClassManager::defaults(), TaxZoneManager::defaults());
    }

    /**
     * @param  array<string, mixed>  $line
     */
    public function snapshot(
        string $lineCode,
        array $line,
        string $taxClassCode,
        ?string $country = null,
        ?string $state = null,
    ): PerLineTaxSnapshot {
        $class = $this->classes->class($taxClassCode);
        $zone = $country !== null ? $this->zones->resolve($country, $state) : null;

        $taxable = (float) ($line['taxable_amount'] ?? $line['subtotal'] ?? 0.0);
        $rate = 0.0;

        if ($class !== null) {
            $rate = $zone !== null ? $zone->rateFor($class) : $class->defaultRate();
        }

        $basisPoints = (int) round($rate * 10000);
        $cents = (int) round($taxable * $basisPoints / 100);

        return new PerLineTaxSnapshot(
            $lineCode,
            $taxClassCode,
            $zone?->code(),
            $rate,
            $taxable,
            $cents / 100,
        );
    }
}
