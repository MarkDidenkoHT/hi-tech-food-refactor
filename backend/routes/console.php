<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:send-photo-question-reminders')
    ->hourly()
    ->between('10:00', '18:00');

// Drop expired API tokens from the database once a day.
Schedule::command('sanctum:prune-expired --hours=24')
    ->daily();
