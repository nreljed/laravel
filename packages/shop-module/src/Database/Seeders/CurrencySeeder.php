<?php

namespace Nreljed\ShopModule\Database\Seeders;

use Illuminate\Database\Seeder;
use Nreljed\ShopModule\Models\Currency;

/**
 * Seeds the baseline shop currencies (EUR, USD, GBP, TND).
 *
 * Idempotent: updateOrCreate by currency code, so re-running never duplicates
 * or clobbers admin-created rows.
 */
class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->baseline() as $currency) {
            Currency::updateOrCreate(
                ['code' => $currency['code']],
                $currency,
            );
        }
    }

    /**
     * @return list<array<string, string|int|float|bool|null>>
     */
    protected function baseline(): array
    {
        return [
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'exchange_rate' => 1.000000, 'decimal_places' => 2, 'is_default' => true, 'is_active' => true],
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'exchange_rate' => 1.080000, 'decimal_places' => 2, 'is_default' => false, 'is_active' => true],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'exchange_rate' => 0.860000, 'decimal_places' => 2, 'is_default' => false, 'is_active' => true],
            ['code' => 'TND', 'name' => 'Tunisian Dinar', 'symbol' => 'DT', 'exchange_rate' => 3.380000, 'decimal_places' => 3, 'is_default' => false, 'is_active' => true],
        ];
    }
}