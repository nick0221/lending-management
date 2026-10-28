<?php

namespace App\Http\Controllers;

use App\Models\Borrower;
use App\Services\ReportingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly ReportingService $reporting) {}

    /**
     * Show the signed-in user's lending overview.
     */
    public function index(Request $request): RedirectResponse|Response
    {
        if ($request->user()->canAccessAdminPanel()) {
            return redirect()->route('admin.dashboard');
        }

        $borrower = Borrower::query()
            ->where('user_id', $request->user()->id)
            ->first();

        return Inertia::render('dashboard', [
            'summary' => $borrower === null
                ? null
                : $this->reporting->borrowerSummary($borrower),
        ]);
    }
}
