<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('votes:close-expired')->everyMinute()->withoutOverlapping();
Schedule::command('contributions:process-dues')->dailyAt('01:00')->withoutOverlapping();
Schedule::command('loans:process')->dailyAt('01:15')->withoutOverlapping();
