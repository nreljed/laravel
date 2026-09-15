<?php

namespace Nreljed\ShopModule\Services;

use Nreljed\ShopModule\Models\TaxRate;

class TaxService
{
    public function __construct(
        protected ShopSettingsService $settings,
    ) {}

    public function calculate(
        float $subtotal,
        ?array $billingAddress = null,
        ?array $shippingAddress = null,
        float $shippingTotal = 0,
        bool $isTaxable = true,
    ): array {
        if (! $isTaxable) {
            return [
                'tax_total' => 0,
                'breakdown' => [],
                'base' => 0,
                'prices_include_tax' => $this->settings->pricesIncludeTax(),
            ];
        }

        $country = $this->resolveCountry($billingAddress, $shippingAddress);

        $rates = TaxRate::active()
            ->forCountry($country)
            ->get();

        if ($rates->isEmpty()) {
            $rates = TaxRate::active()->where('is_default', true)->get();
        }

        $base = $subtotal + ($this->settings->taxShipping() ? $shippingTotal : 0);
        $pricesIncludeTax = $this->settings->pricesIncludeTax();

        $totalTax = 0;
        $breakdown = [];

        foreach ($rates as $rate) {
            if ($rate->type === 'percentage') {
                $tax = $pricesIncludeTax
                    ? $base - ($base / (1 + ((float) $rate->rate / 100)))
                    : $base * ((float) $rate->rate / 100);
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
            'base' => round($base, 2),
            'prices_include_tax' => $pricesIncludeTax,
        ];
    }

    protected function resolveCountry(?array $billingAddress, ?array $shippingAddress): ?string
    {
        $billing = $billingAddress['country'] ?? null;
        $shipping = $shippingAddress['country'] ?? null;

        if ($this->settings->taxBasedOn() === 'shipping') {
            return $shipping ?: $billing;
        }

        return $billing ?: $shipping;
    }
}
