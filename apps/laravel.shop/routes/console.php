<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('marketing:sync-customers')->hourly();
Schedule::command('marketing:process-orders')->everyFiveMinutes();
Schedule::command('shop:abandoned-cart-process')->hourly();
Schedule::command('webhooks:process')->everyMinute();
Schedule::command('shop:release-expired-holds')->everyFiveMinutes();
Schedule::command('shop:process-scheduled-sales')->everyMinute();
