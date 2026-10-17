<?php

namespace Database\Seeders;

use App\Enums\DisbursementMethod;
use App\Models\Borrower;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\LoanService;
use Illuminate\Database\Seeder;

class LoanSeeder extends Seeder
{
    /**
     * Seed the application's loans.
     */
    public function run(): void
    {
        $borrowers = Borrower::query()->inRandomOrder()->take(6)->get();
        $products = LoanProduct::query()->active()->get();

        if ($borrowers->isEmpty() || $products->isEmpty()) {
            return;
        }

        $loanService = app(LoanService::class);
        $actor = User::query()->orderBy('id')->first();

        foreach ($borrowers as $index => $borrower) {
            $product = $products->random();

            $minimum = (float) $product->min_amount;
            $maximum = (float) $product->max_amount;
            $principal = round($minimum + ($maximum - $minimum) * 0.25, 2);
            $termMonths = $product->min_term_months
                + intdiv($product->max_term_months - $product->min_term_months, 2);

            $loan = $loanService->originate($borrower, $product, $principal, $termMonths, 'Seeded application.');

            if ($index % 3 === 1) {
                $loanService->approve($loan);
            } elseif ($index % 3 === 2) {
                $loanService->reject($loan);
            }

            if ($index === 1 && $actor !== null) {
                $loanService->disburse(
                    $loan,
                    $actor,
                    (float) $loan->principal,
                    DisbursementMethod::BankTransfer,
                    'SEED-'.$loan->id,
                );
            }
        }
    }
}
