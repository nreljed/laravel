<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('notifications:purge', ['--days' => 90])->daily();
Schedule::command('app:prune-audit-logs')->daily();

// Shared-hosting queue worker: processes the database queue in short bursts
// so SendEmail / GenerateSeoForPost (and batch SEO jobs) actually execute.
Schedule::command('queue:work', ['--stop-when-empty' => true])
    ->everyMinute()
    ->withoutOverlapping();
