<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateNotificationPreferencesRequest;
use App\Models\Borrower;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * Show the borrower's notification preferences.
     */
    public function edit(Request $request): Response
    {
        $borrower = $this->borrowerFor($request);

        return Inertia::render('settings/notifications', [
            'preferences' => [
                'email' => $borrower->email,
                'phone' => $borrower->phone,
                'notify_due_soon' => $borrower->notify_due_soon,
                'notify_overdue' => $borrower->notify_overdue,
                'notify_receipts' => $borrower->notify_receipts,
                'notify_statements' => $borrower->notify_statements,
                'notify_sms' => $borrower->notify_sms,
            ],
        ]);
    }

    /**
     * Save the borrower's notification preferences.
     */
    public function update(UpdateNotificationPreferencesRequest $request): RedirectResponse
    {
        $borrower = $this->borrowerFor($request);

        $borrower->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Notification preferences updated.'),
        ]);

        return to_route('notifications.edit');
    }

    /**
     * Resolve the borrower profile linked to the signed-in account.
     */
    private function borrowerFor(Request $request): Borrower
    {
        $borrower = $request->user()->borrower;

        abort_if($borrower === null, 403);

        return $borrower;
    }
}
