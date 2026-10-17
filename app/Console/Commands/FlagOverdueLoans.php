<?php

namespace App\Console\Commands;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Services\LoanService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('loans:flag-overdue {--days= : Days past due before a loan is defaulted}')]
#[Description('Refresh the overdue flag on loans and default those past the threshold')]
class FlagOverdueLoans extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(LoanService $loanService): int
    {
        $threshold = (int) ($this->option('days') ?: config('lending.default_after_days', 90));

        $loans = Loan::query()
            ->whereIn('status', [LoanStatus::Active, LoanStatus::Defaulted])
            ->with('installments.allocations')
            ->get();

        $flagged = 0;
        $refreshed = 0;

        foreach ($loans as $loan) {
            $daysPastDue = (int) $loan->installments
                ->max(fn (LoanInstallment $installment): int => $installment->daysPastDue());

            if ((int) $loan->days_past_due !== $daysPastDue) {
                $loan->forceFill(['days_past_due' => $daysPastDue])->save();
                $refreshed++;
            }

            if ($loan->status === LoanStatus::Active && $daysPastDue >= $threshold) {
                $loanService->markDefaulted($loan);
                $flagged++;
            }
        }

        // Terminal loans no longer carry arrears.
        Loan::query()
            ->whereIn('status', [LoanStatus::Closed, LoanStatus::WrittenOff, LoanStatus::Rejected])
            ->where('days_past_due', '!=', 0)
            ->update(['days_past_due' => 0]);

        $this->info(sprintf(
            'Refreshed %d loan(s); flagged %d as defaulted (threshold %d day(s) past due).',
            $refreshed,
            $flagged,
            $threshold,
        ));

        return self::SUCCESS;
    }
}
