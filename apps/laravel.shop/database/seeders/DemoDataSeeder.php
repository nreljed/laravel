<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\BootstrapsAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DemoDataSeeder extends Seeder
{
    use BootstrapsAdmin;

    public function run(): void
    {
        $this->ensureAdminUser();

        Artisan::call('demo:generate', ['--fresh' => true]);

        $this->command->info('Demo data generated via `demo:generate --fresh`.');
    }
}
