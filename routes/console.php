<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('schedule:publish')->everyMinute();
Schedule::command('notifications:purge', ['--days' => 90])->daily();
Schedule::command('app:prune-audit-logs')->daily();
