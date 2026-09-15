<?php

namespace Nreljed\ShopModule\Commands;

use Database\Seeders\ShopProductSeeder;
use Illuminate\Console\Command;

class ShopProductSeed extends Command
{
    protected $signature = 'shop:product-seed';

    protected $description = 'Seed the demo product catalog (ShopProductSeeder)';

    public function handle(): int
    {
        $this->call('db:seed', ['--class' => ShopProductSeeder::class, '--force' => true]);

        $this->info('Product catalog seeded.');

        return self::SUCCESS;
    }
}
