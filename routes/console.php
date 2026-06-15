<?php

use Illuminate\Support\Facades\Schedule;

// Weekly metric snapshots — runs every Monday at 8:00 AM
Schedule::command('metrics:snapshot')
    ->weeklyOn(1, '08:00')
    ->withoutOverlapping()
    ->runInBackground();
