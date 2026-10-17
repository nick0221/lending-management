<?php

namespace App\Console\Commands;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Services\PenaltyService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

#[Signature('loans:accrue-penalties {--date= : The date to accrue as of (defaults to today)}')]
#[Description('Accrue monthly late-payment penalties on overdue loan installments')]
class AccrueLoanPenalties extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PenaltyService $penaltyService): int
    {
        $asOf = $this->option('date') ? Date::parse($this->option('date')) : Date::today();

        $loans = Loan::query()
            ->whereIn('status', [LoanStatus::Active, LoanStatus::Defaulted])
            ->where('penalty_rate', '>', 0)
            ->with('installments.allocations')
            ->get();

        $totalAccrued = 0.0;
        $installmentsTouched = 0;
        $loansTouched = 0;

        foreach ($loans as $loan) {
            $result = $penaltyService->accrueForLoan($loan, $asOf);

            if ($result['installments'] > 0) {
                $loansTouched++;
                $installmentsTouched += $result['installments'];
                $totalAccrued = round($totalAccrued + $result['amount'], 2);
            }
        }

        $this->info(sprintf(
            'Accrued %s in penalties across %d installment(s) on %d loan(s).',
            number_format($totalAccrued, 2, '.', ''),
            $installmentsTouched,
            $loansTouched,
        ));

        return self::SUCCESS;
    }
}
