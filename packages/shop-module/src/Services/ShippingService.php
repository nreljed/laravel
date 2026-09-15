<?php

namespace Nreljed\ShopModule\Services;

use Illuminate\Support\Collection;
use Nreljed\ShopModule\Models\ShippingMethod;
use Nreljed\ShopModule\Models\ShippingZone;

class ShippingService
{
    public function __construct(
        protected ShopSettingsService $settings,
    ) {}

    public function getAvailableMethods(?array $billingAddress): Collection
    {
        $country = $billingAddress['country'] ?? null ?: $this->settings->baseCountry();

        if (! $country) {
            return ShippingMethod::active()->get();
        }

        $zone = ShippingZone::active()
            ->whereJsonContains('countries', $country)
            ->with('methods')
            ->first();

        if ($zone && $zone->methods->isNotEmpty()) {
            return $zone->methods->map(function ($method) {
                if ($method->pivot && $method->pivot->override_price !== null) {
                    $method->price = (float) $method->pivot->override_price;
                }

                return $method;
            })->sortBy('sort_order')->values();
        }

        return ShippingMethod::active()->get();
    }
}
