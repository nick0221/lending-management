<?php

use App\Services\OpsHeartbeat;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$heartbeat = fn (string $command): Closure => fn () => app(OpsHeartbeat::class)->record($command);

Schedule::command('loans:accrue-penalties')->dailyAt('01:00')->withoutOverlapping()->onOneServer()
    ->onSuccess($heartbeat('loans:accrue-penalties'));
Schedule::command('loans:flag-overdue')->dailyAt('01:30')->withoutOverlapping()->onOneServer()
    ->onSuccess($heartbeat('loans:flag-overdue'));
Schedule::command('loans:send-due-reminders')->dailyAt('08:00')->withoutOverlapping()->onOneServer()
    ->onSuccess($heartbeat('loans:send-due-reminders'));
Schedule::command('loans:send-overdue-notices')->dailyAt('08:15')->withoutOverlapping()->onOneServer()
    ->onSuccess($heartbeat('loans:send-overdue-notices'));
