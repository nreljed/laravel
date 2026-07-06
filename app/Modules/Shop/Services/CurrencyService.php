<?php

namespace App\Modules\Shop\Services;

use App\Modules\Shop\Models\Currency;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Cache;

class CurrencyService
{
    public function getActiveCurrencies()
    {
        return Cache::remember('currencies.active', 3600, function () {
            return Currency::active()->orderBy('code')->get();
        });
    }

    public function getDefaultCurrency(): ?Currency
    {
        return Cache::remember('currencies.default', 3600, function () {
            return Currency::default()->first() ?? Currency::active()->first();
        });
    }

    public function convert(float $amount, string $fromCode, string $toCode): float
    {
        if ($fromCode === $toCode) {
            return $amount;
        }

        $from = $this->getByCode($fromCode);
        $to = $this->getByCode($toCode);

        if (! $from || ! $to) {
            return $amount;
        }

        $baseAmount = $amount / $from->exchange_rate;

        return round($baseAmount * $to->exchange_rate, $to->decimal_places);
    }

    public function getByCode(string $code): ?Currency
    {
        return Currency::where('code', strtoupper($code))->first();
    }

    public function format(float $amount, string $code): string
    {
        $currency = $this->getByCode($code);

        if (! $currency) {
            return number_format($amount, 2).' '.$code;
        }

        $formatted = number_format($amount, $currency->decimal_places, '.', ',');

        $symbol = $currency->symbol;
        $position = SettingsService::get('currency_position', 'before');

        if ($position === 'after') {
            return $formatted.' '.$symbol;
        }

        return $symbol.$formatted;
    }

    public function getAllForSelector(): array
    {
        return $this->getActiveCurrencies()->map(fn ($c) => [
            'code' => $c->code,
            'name' => $c->name,
            'symbol' => $c->symbol,
            'is_default' => $c->is_default,
        ])->values()->toArray();
    }
}
