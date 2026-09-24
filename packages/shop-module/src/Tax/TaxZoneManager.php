<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Tax;

use Nreljed\ShopModule\Tax\Zones\EuZone;
use Nreljed\ShopModule\Tax\Zones\NzZone;
use Nreljed\ShopModule\Tax\Zones\UsZone;

final class TaxZoneManager
{
    /** @var array<string, TaxZoneContract> */
    private array $zones = [];

    /**
     * @param  array<int, TaxZoneContract>  $zones
     */
    public function __construct(array $zones = [])
    {
        foreach ($zones as $zone) {
            $this->register($zone);
        }
    }

    public static function defaults(): self
    {
        return new self([new EuZone(), new UsZone(), new NzZone()]);
    }

    public function register(TaxZoneContract $zone): void
    {
        $this->zones[$zone->code()] = $zone;
    }

    public function zone(string $code): ?TaxZoneContract
    {
        return $this->zones[$code] ?? null;
    }

    public function resolve(string $country, ?string $state = null): ?TaxZoneContract
    {
        foreach ($this->zones as $zone) {
            if (in_array($country, $zone->countries(), true)) {
                return $zone;
            }
            if ($state !== null && in_array($state, $zone->states(), true)) {
                return $zone;
            }
        }

        return null;
    }

    /**
     * @return array<string, TaxZoneContract>
     */
    public function all(): array
    {
        return $this->zones;
    }
}
