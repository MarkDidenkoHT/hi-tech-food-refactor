<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:send-photo-question-reminders')
    ->hourly()
    ->between('10:00', '18:00');

// Drop expired API tokens from the database once a day.
Schedule::command('sanctum:prune-expired --hours=24')
    ->daily();
