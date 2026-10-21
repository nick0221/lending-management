<?php

namespace App\Http\Controllers\Admin;

use App\Enums\KycStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportBorrowersRequest;
use App\Models\Borrower;
use App\Services\BorrowerImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BorrowerImportController extends Controller
{
    /**
     * Show the borrower CSV import page.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Borrower::class);

        return Inertia::render('admin/borrowers/import', [
            'summary' => $request->session()->get('import_summary'),
        ]);
    }

    /**
     * Import the uploaded borrower CSV.
     */
    public function store(ImportBorrowersRequest $request, BorrowerImportService $importer): RedirectResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('file');

        $summary = $importer->import($file);

        $message = match (true) {
            $summary['imported'] === 0 => __('No borrowers were imported. Review the row errors and try again.'),
            $summary['skipped'] > 0 => __(':imported imported, :skipped skipped.', [
                'imported' => $summary['imported'],
                'skipped' => $summary['skipped'],
            ]),
            default => __(':count borrowers imported.', ['count' => $summary['imported']]),
        };

        Inertia::flash('toast', [
            'type' => $summary['imported'] > 0 && $summary['skipped'] === 0 ? 'success' : 'warning',
            'message' => $message,
        ]);

        return to_route('admin.borrowers.imports.create')
            ->with('import_summary', $summary);
    }

    /**
     * Download the CSV template.
     */
    public function template(): StreamedResponse
    {
        Gate::authorize('create', Borrower::class);

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'name',
                'phone',
                'email',
                'national_id',
                'date_of_birth',
                'address',
                'kyc_status',
                'notes',
            ]);

            fputcsv($handle, [
                'Ada Lovelace',
                '+1-555-0100',
                'ada@example.com',
                'NID-0001',
                '1990-01-01',
                '1 Analytical Engine Way',
                KycStatus::Verified->value,
                '',
            ]);

            fclose($handle);
        }, 'borrowers-template.csv', ['Content-Type' => 'text/csv']);
    }
}
