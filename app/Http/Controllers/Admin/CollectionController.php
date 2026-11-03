<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CollectionOutcome;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LogCollectionContactRequest;
use App\Models\Loan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CollectionController extends Controller
{
    /**
     * Log a collection contact against the given loan.
     */
    public function store(LogCollectionContactRequest $request, Loan $loan): RedirectResponse
    {
        $validated = $request->validated();
        $outcome = CollectionOutcome::from($validated['outcome']);
        $promisedAt = $outcome->isPromise() ? $validated['promised_at'] : null;

        $loan->collectionContacts()->create([
            'user_id' => $request->user()->id,
            'channel' => $validated['channel'],
            'outcome' => $validated['outcome'],
            'contacted_at' => $validated['contacted_at'] ?? now(),
            'notes' => $validated['notes'] ?? null,
            'promised_amount' => $outcome->isPromise() ? $validated['promised_amount'] : null,
            'promised_at' => $promisedAt,
            'follow_up_at' => $validated['follow_up_at'] ?? $promisedAt,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Collection contact logged.'),
        ]);

        return back();
    }
}
