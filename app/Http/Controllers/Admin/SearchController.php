<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Borrower;
use App\Models\Disbursement;
use App\Models\Loan;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SearchController extends Controller
{
    /**
     * Return the borrowers, loans, payments and payouts matching a global search query.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim($request->string('q')->toString());

        if ($query === '') {
            return response()->json([
                'query' => '',
                'borrowers' => [],
                'loans' => [],
                'payments' => [],
                'disbursements' => [],
            ]);
        }

        $like = '%'.$query.'%';
        $numericId = ctype_digit(ltrim($query, '#')) ? (int) ltrim($query, '#') : null;
        $loanId = $numericId;

        $borrowers = Borrower::query()
            ->where(function ($builder) use ($like): void {
                $builder->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('national_id', 'like', $like);
            })
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'name', 'phone', 'kyc_status'])
            ->map(fn (Borrower $borrower): array => [
                'id' => $borrower->id,
                'name' => $borrower->name,
                'phone' => $borrower->phone,
                'kyc_status' => $borrower->kyc_status->value,
                'url' => route('admin.borrowers.show', $borrower),
            ]);

        $loans = Loan::query()
            ->with(['borrower:id,name'])
            ->where(function ($builder) use ($loanId, $like): void {
                if ($loanId !== null) {
                    $builder->whereKey($loanId);

                    return;
                }

                $builder->whereHas('borrower', fn ($borrower) => $borrower
                    ->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('national_id', 'like', $like));
            })
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (Loan $loan): array => [
                'id' => $loan->id,
                'borrower' => $loan->borrower?->name,
                'status' => $loan->status->value,
                'status_label' => $loan->status->label(),
                'principal' => (float) $loan->principal,
                'url' => route('admin.loans.show', $loan),
            ]);

        return response()->json([
            'query' => $query,
            'borrowers' => $borrowers,
            'loans' => $loans,
            'payments' => $this->payments($like, $numericId),
            'disbursements' => $this->disbursements($like, $numericId),
        ]);
    }

    /**
     * Find payments by reference or number, with the loan they belong to.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function payments(string $like, ?int $paymentId): Collection
    {
        return Payment::query()
            ->with('loan.borrower:id,name')
            ->where(function ($builder) use ($like, $paymentId): void {
                $builder->where('reference', 'like', $like);

                if ($paymentId !== null) {
                    $builder->orWhereKey($paymentId);
                }
            })
            ->latest('payment_date')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'amount' => (float) $payment->amount,
                'reference' => $payment->reference,
                'method_label' => $payment->method->label(),
                'payment_date' => $payment->payment_date->toDateString(),
                'reversed' => $payment->reversed_at !== null,
                'borrower' => $payment->loan?->borrower?->name,
                'loan_id' => $payment->loan_id,
                'url' => route('admin.loans.show', [
                    'loan' => $payment->loan_id,
                    'tab' => 'payments',
                ]),
            ]);
    }

    /**
     * Find payouts by reference or number, with the loan they belong to.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function disbursements(string $like, ?int $disbursementId): Collection
    {
        return Disbursement::query()
            ->with('loan.borrower:id,name')
            ->where(function ($builder) use ($like, $disbursementId): void {
                $builder->where('reference', 'like', $like);

                if ($disbursementId !== null) {
                    $builder->orWhereKey($disbursementId);
                }
            })
            ->latest('disbursed_at')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (Disbursement $disbursement): array => [
                'id' => $disbursement->id,
                'amount' => (float) $disbursement->amount,
                'reference' => $disbursement->reference,
                'method_label' => $disbursement->method->label(),
                'disbursed_at' => $disbursement->disbursed_at->toDateString(),
                'borrower' => $disbursement->loan?->borrower?->name,
                'loan_id' => $disbursement->loan_id,
                'url' => route('admin.loans.show', [
                    'loan' => $disbursement->loan_id,
                    'tab' => 'disbursements',
                ]),
            ]);
    }
}
