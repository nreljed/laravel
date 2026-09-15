<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'exchange_rate' => 1.000000, 'decimal_places' => 2, 'is_default' => true, 'is_active' => true],
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'exchange_rate' => 1.080000, 'decimal_places' => 2, 'is_default' => false, 'is_active' => true],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'exchange_rate' => 0.860000, 'decimal_places' => 2, 'is_default' => false, 'is_active' => true],
            ['code' => 'TND', 'name' => 'Tunisian Dinar', 'symbol' => 'DT', 'exchange_rate' => 3.380000, 'decimal_places' => 3, 'is_default' => false, 'is_active' => true],
        ];

        foreach ($currencies as $currency) {
            DB::table('currencies')->updateOrInsert(
                ['code' => $currency['code']],
                $currency,
            );
        }
    }
}
