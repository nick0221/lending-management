<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InterestMethod;
use App\Enums\RepaymentFrequency;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLoanProductRequest;
use App\Http\Requests\Admin\UpdateLoanProductRequest;
use App\Models\LoanProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LoanProductController extends Controller
{
    /**
     * Display a listing of the loan products.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', LoanProduct::class);

        $actor = $request->user();

        $loanProducts = LoanProduct::query()
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (LoanProduct $loanProduct): array => [
                ...$this->toPayload($loanProduct),
                'can' => [
                    'update' => $actor->can('update', $loanProduct),
                    'delete' => $actor->can('delete', $loanProduct),
                ],
            ]);

        return Inertia::render('admin/loan-products/index', [
            'loanProducts' => $loanProducts,
        ]);
    }

    /**
     * Show the form for creating a new loan product.
     */
    public function create(): Response
    {
        Gate::authorize('create', LoanProduct::class);

        return Inertia::render('admin/loan-products/create', [
            'interestMethods' => $this->interestMethodOptions(),
            'frequencies' => $this->frequencyOptions(),
        ]);
    }

    /**
     * Store a newly created loan product.
     */
    public function store(StoreLoanProductRequest $request): RedirectResponse
    {
        LoanProduct::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Loan product created.')]);

        return to_route('admin.loan-products.index');
    }

    /**
     * Show the form for editing the given loan product.
     */
    public function edit(LoanProduct $loanProduct): Response
    {
        Gate::authorize('update', $loanProduct);

        return Inertia::render('admin/loan-products/edit', [
            'loanProduct' => $this->toPayload($loanProduct),
            'interestMethods' => $this->interestMethodOptions(),
            'frequencies' => $this->frequencyOptions(),
        ]);
    }

    /**
     * Update the given loan product.
     */
    public function update(UpdateLoanProductRequest $request, LoanProduct $loanProduct): RedirectResponse
    {
        $loanProduct->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Loan product updated.')]);

        return to_route('admin.loan-products.index');
    }

    /**
     * Remove the given loan product.
     */
    public function destroy(LoanProduct $loanProduct): RedirectResponse
    {
        Gate::authorize('delete', $loanProduct);

        $loanProduct->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Loan product deleted.')]);

        return to_route('admin.loan-products.index');
    }

    /**
     * Transform the loan product into the payload shared with the client.
     *
     * @return array<string, mixed>
     */
    private function toPayload(LoanProduct $loanProduct): array
    {
        return [
            'id' => $loanProduct->id,
            'name' => $loanProduct->name,
            'description' => $loanProduct->description,
            'interest_method' => $loanProduct->interest_method->value,
            'interest_method_label' => $loanProduct->interest_method->label(),
            'interest_rate' => $loanProduct->interest_rate,
            'repayment_frequency' => $loanProduct->repayment_frequency->value,
            'repayment_frequency_label' => $loanProduct->repayment_frequency->label(),
            'min_amount' => $loanProduct->min_amount,
            'max_amount' => $loanProduct->max_amount,
            'min_term_months' => $loanProduct->min_term_months,
            'max_term_months' => $loanProduct->max_term_months,
            'processing_fee_rate' => $loanProduct->processing_fee_rate,
            'penalty_rate' => $loanProduct->penalty_rate,
            'grace_period_days' => $loanProduct->grace_period_days,
            'is_active' => $loanProduct->is_active,
            'created_at' => $loanProduct->created_at?->toIso8601String(),
        ];
    }

    /**
     * Map the interest methods to select options.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function interestMethodOptions(): array
    {
        return array_map(
            fn (InterestMethod $method): array => ['value' => $method->value, 'label' => $method->label()],
            InterestMethod::cases(),
        );
    }

    /**
     * Map the repayment frequencies to select options.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function frequencyOptions(): array
    {
        return array_map(
            fn (RepaymentFrequency $frequency): array => ['value' => $frequency->value, 'label' => $frequency->label()],
            RepaymentFrequency::cases(),
        );
    }
}
