<?php

namespace App\Modules\Shop\Services;

use App\Modules\Shop\Models\TaxRate;

class TaxService
{
    public function calculate(float $subtotal, ?array $billingAddress, bool $isTaxable = true): array
    {
        if (! $isTaxable) {
            return ['tax_total' => 0, 'breakdown' => []];
        }

        $country = $billingAddress['country'] ?? null;

        $rates = TaxRate::active()
            ->forCountry($country)
            ->get();

        if ($rates->isEmpty()) {
            $rates = TaxRate::active()->where('is_default', true)->get();
        }

        $totalTax = 0;
        $breakdown = [];

        foreach ($rates as $rate) {
            if ($rate->type === 'percentage') {
                $tax = $subtotal * ((float) $rate->rate / 100);
            } else {
                $tax = (float) $rate->rate;
            }

            $totalTax += $tax;
            $breakdown[] = [
                'name' => $rate->name,
                'rate' => (float) $rate->rate,
                'type' => $rate->type,
                'amount' => round($tax, 2),
            ];
        }

        return [
            'tax_total' => round($totalTax, 2),
            'breakdown' => $breakdown,
        ];
    }
}
