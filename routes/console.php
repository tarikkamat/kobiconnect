<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('email:daily-sales-summary')->dailyAt('09:00')->timezone('Europe/Istanbul');
Schedule::command('email:daily-stock-alert')->dailyAt('08:00')->timezone('Europe/Istanbul');
Schedule::command('email:weekly-performance')->weeklyOn(1, '09:00')->timezone('Europe/Istanbul');
Schedule::command('email:weekly-ops-summary')->weeklyOn(1, '10:00')->timezone('Europe/Istanbul');
