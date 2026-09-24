<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Tax;

use InvalidArgumentException;

abstract class TaxZoneType implements TaxZoneContract
{
    /**
     * @param  array<int, string>  $countries
     * @param  array<int, string>  $states
     * @param  array<string, float>  $classRates
     */
    public function __construct(
        protected readonly string $code,
        protected readonly string $label,
        protected readonly array $countries,
        protected readonly array $states,
        protected readonly array $classRates,
    ) {
        if ($code === '') {
            throw new InvalidArgumentException('Tax zone code must not be empty.');
        }
    }

    public function code(): string
    {
        return $this->code;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function countries(): array
    {
        return $this->countries;
    }

    public function states(): array
    {
        return $this->states;
    }

    public function rateFor(TaxClassContract $class): float
    {
        return $this->classRates[$class->code()] ?? $class->defaultRate();
    }

    public function appliesTo(string $countryCode, ?string $state = null): bool
    {
        if (in_array($countryCode, $this->countries, true)) {
            return true;
        }

        return $state !== null && in_array($state, $this->states, true);
    }
}
