<?php

namespace App\Modules\Shop\Services;

use App\Modules\Shop\Models\ShippingMethod;
use App\Modules\Shop\Models\ShippingZone;
use Illuminate\Support\Collection;

class ShippingService
{
    public function getAvailableMethods(?array $billingAddress): Collection
    {
        $country = $billingAddress['country'] ?? null;

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
