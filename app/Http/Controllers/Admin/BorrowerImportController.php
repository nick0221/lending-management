<?php

namespace App\Http\Controllers\Admin;

use App\Enums\KycStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportBorrowersRequest;
use App\Models\Borrower;
use App\Services\BorrowerImportService;
use App\Services\CsvExporter;
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

        $summary = $request->session()->get('import_summary');

        if (is_array($summary)) {
            $summary['errors'] = array_slice($summary['errors'] ?? [], 0, 20);
        }

        return Inertia::render('admin/borrowers/import', [
            'summary' => $summary,
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
     * Download every error from the last import as a CSV.
     */
    public function downloadErrors(Request $request, CsvExporter $csvExporter): StreamedResponse|RedirectResponse
    {
        Gate::authorize('create', Borrower::class);

        $summary = $request->session()->get('import_summary');
        $errors = is_array($summary) ? ($summary['errors'] ?? []) : [];

        if ($errors === []) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('There are no import errors to download.'),
            ]);

            return back();
        }

        return $csvExporter->stream(
            'borrower-import-errors-'.now()->format('Y-m-d-His').'.csv',
            [
                ['Line', 'Issue'],
                ...collect($errors)
                    ->map(fn (array $error): array => [
                        $error['line'] === 0 ? 'File' : $error['line'],
                        $error['message'],
                    ])
                    ->all(),
            ],
        );
    }

    /**
     * Download the CSV template.
     */
    public function template(CsvExporter $csvExporter): StreamedResponse
    {
        Gate::authorize('create', Borrower::class);

        return $csvExporter->stream('borrowers-template.csv', [
            [
                'name',
                'phone',
                'email',
                'national_id',
                'date_of_birth',
                'address',
                'kyc_status',
                'notes',
            ],
            [
                'Ada Lovelace',
                '+1-555-0100',
                'ada@example.com',
                'NID-0001',
                '1990-01-01',
                '1 Analytical Engine Way',
                KycStatus::Verified->value,
                '',
            ],
        ]);
    }
}
