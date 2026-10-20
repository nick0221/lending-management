<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('loans:send-due-reminders {--days= : Days before the due date to remind borrowers}')]
#[Description('Email borrowers whose installment falls due in the reminder window')]
class SendDueReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService): int
    {
        $days = $this->option('days');

        $sent = $notificationService->sendDueReminders(
            days: $days === null ? null : (int) $days,
        );

        $this->info(sprintf('Sent %d due reminder(s).', $sent));

        return self::SUCCESS;
    }
}
