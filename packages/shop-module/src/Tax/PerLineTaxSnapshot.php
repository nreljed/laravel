<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Tax;

final class PerLineTaxSnapshot
{
    public function __construct(
        public readonly string $lineCode,
        public readonly string $taxClassCode,
        public readonly ?string $taxZoneCode,
        public readonly float $rate,
        public readonly float $taxableAmount,
        public readonly float $taxAmount,
    ) {
    }

    /**
     * @return array<string, string|float|null>
     */
    public function toArray(): array
    {
        return [
            'line_code' => $this->lineCode,
            'tax_class_code' => $this->taxClassCode,
            'tax_zone_code' => $this->taxZoneCode,
            'rate' => $this->rate,
            'taxable_amount' => $this->taxableAmount,
            'tax_amount' => $this->taxAmount,
        ];
    }
}
