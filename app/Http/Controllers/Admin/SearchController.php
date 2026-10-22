<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Borrower;
use App\Models\Loan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Return the borrowers and loans matching a global search query.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim($request->string('q')->toString());

        if ($query === '') {
            return response()->json([
                'query' => '',
                'borrowers' => [],
                'loans' => [],
            ]);
        }

        $like = '%'.$query.'%';
        $loanId = ctype_digit(ltrim($query, '#')) ? (int) ltrim($query, '#') : null;

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
        ]);
    }
}
