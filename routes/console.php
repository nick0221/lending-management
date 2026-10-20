<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('loans:accrue-penalties')->dailyAt('01:00')->withoutOverlapping()->onOneServer();
Schedule::command('loans:flag-overdue')->dailyAt('01:30')->withoutOverlapping()->onOneServer();
Schedule::command('loans:send-due-reminders')->dailyAt('08:00')->withoutOverlapping()->onOneServer();
Schedule::command('loans:send-overdue-notices')->dailyAt('08:15')->withoutOverlapping()->onOneServer();
