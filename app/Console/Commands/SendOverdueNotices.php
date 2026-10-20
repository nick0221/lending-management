<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('loans:send-overdue-notices')]
#[Description('Email borrowers with overdue installments')]
class SendOverdueNotices extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService): int
    {
        $sent = $notificationService->sendOverdueNotices();

        $this->info(sprintf('Sent %d overdue notice(s).', $sent));

        return self::SUCCESS;
    }
}
