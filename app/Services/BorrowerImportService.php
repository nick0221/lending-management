<?php

namespace App\Services;

use App\Enums\KycStatus;
use App\Http\Requests\Admin\StoreBorrowerRequest;
use App\Models\Borrower;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BorrowerImportService
{
    /**
     * Column aliases accepted in addition to the template headers.
     *
     * @var array<string, string>
     */
    private const HEADER_ALIASES = [
        'full_name' => 'name',
        'borrower_name' => 'name',
        'phone_number' => 'phone',
        'mobile' => 'phone',
        'mobile_number' => 'phone',
        'mobile_phone' => 'phone',
        'email_address' => 'email',
        'e_mail' => 'email',
        'id_number' => 'national_id',
        'national_id_number' => 'national_id',
        'nationalid' => 'national_id',
        'dob' => 'date_of_birth',
        'birth_date' => 'date_of_birth',
        'kyc' => 'kyc_status',
    ];

    /**
     * Import borrowers from a CSV upload.
     *
     * Valid rows are created in a single transaction. Duplicate national IDs
     * and invalid rows are skipped and reported by line number.
     *
     * @return array{imported: int, skipped: int, total_rows: int, errors: array<int, array{line: int, message: string}>, error_count: int}
     */
    public function import(UploadedFile $file): array
    {
        $path = $file->getRealPath();

        if ($path === false) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file could not be read.',
            ]);
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file could not be read.',
            ]);
        }

        try {
            $headers = fgetcsv($handle);

            if ($headers === false) {
                throw ValidationException::withMessages([
                    'file' => 'The CSV must include a header row.',
                ]);
            }

            $headerMap = $this->headerMap($headers);

            if (! isset($headerMap['name'], $headerMap['phone'])) {
                throw ValidationException::withMessages([
                    'file' => 'The CSV must include name and phone columns.',
                ]);
            }

            $validRows = [];
            $errors = [];
            $seenNationalIds = [];
            $totalRows = 0;
            $line = 1;

            while (($row = fgetcsv($handle)) !== false) {
                $line++;

                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $totalRows++;

                $nationalId = $this->value($row, $headerMap, 'national_id');
                $nationalIdKey = $nationalId !== null ? strtolower($nationalId) : null;

                if ($nationalId !== null && Borrower::query()->where('national_id', $nationalId)->exists()) {
                    $errors[] = [
                        'line' => $line,
                        'message' => "National ID {$nationalId} is already registered.",
                    ];

                    continue;
                }

                $kycStatus = strtolower((string) $this->value($row, $headerMap, 'kyc_status'));

                $data = [
                    'name' => (string) $this->value($row, $headerMap, 'name'),
                    'national_id' => $nationalId,
                    'phone' => (string) $this->value($row, $headerMap, 'phone'),
                    'email' => $this->value($row, $headerMap, 'email'),
                    'date_of_birth' => $this->value($row, $headerMap, 'date_of_birth'),
                    'address' => $this->value($row, $headerMap, 'address'),
                    'kyc_status' => $kycStatus !== '' ? $kycStatus : KycStatus::Pending->value,
                    'notes' => $this->value($row, $headerMap, 'notes'),
                    'notify_due_soon' => true,
                    'notify_overdue' => true,
                    'notify_receipts' => true,
                    'notify_statements' => true,
                    'notify_sms' => false,
                ];

                $validator = Validator::make($data, StoreBorrowerRequest::baseRules());

                if ($validator->fails()) {
                    $errors[] = [
                        'line' => $line,
                        'message' => (string) $validator->errors()->first(),
                    ];

                    continue;
                }

                if ($nationalIdKey !== null && isset($seenNationalIds[$nationalIdKey])) {
                    $errors[] = [
                        'line' => $line,
                        'message' => "National ID {$nationalId} appears more than once in the file.",
                    ];

                    continue;
                }

                if ($nationalIdKey !== null) {
                    $seenNationalIds[$nationalIdKey] = true;
                }

                $validRows[] = $data;
            }
        } finally {
            fclose($handle);
        }

        if ($totalRows === 0) {
            throw ValidationException::withMessages([
                'file' => 'The CSV does not contain any borrower rows.',
            ]);
        }

        $imported = 0;

        try {
            DB::transaction(function () use ($validRows, &$imported): void {
                foreach ($validRows as $data) {
                    Borrower::create($data);
                    $imported++;
                }
            });
        } catch (QueryException $exception) {
            report($exception);

            $imported = 0;
            $errors[] = [
                'line' => 0,
                'message' => 'The import could not be completed. No borrowers were created.',
            ];
        }

        return [
            'imported' => $imported,
            'skipped' => $totalRows - $imported,
            'total_rows' => $totalRows,
            'errors' => array_slice($errors, 0, 20),
            'error_count' => count($errors),
        ];
    }

    /**
     * Map the normalized headers to their column indexes.
     *
     * @param  array<int, string|null>  $headers
     * @return array<string, int>
     */
    private function headerMap(array $headers): array
    {
        $map = [];

        foreach ($headers as $index => $header) {
            $normalized = $this->normalizeHeader((string) $header);

            if ($normalized !== '' && ! isset($map[$normalized])) {
                $map[$normalized] = $index;
            }
        }

        return $map;
    }

    /**
     * Normalize a CSV header and resolve its alias.
     */
    private function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
        $header = strtolower(trim($header));
        $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?? $header;
        $header = trim($header, '_');

        return self::HEADER_ALIASES[$header] ?? $header;
    }

    /**
     * Read a trimmed value from the row, treating blanks as null.
     *
     * @param  array<int, string|null>  $row
     * @param  array<string, int>  $headerMap
     */
    private function value(array $row, array $headerMap, string $key): ?string
    {
        if (! isset($headerMap[$key])) {
            return null;
        }

        $value = $row[$headerMap[$key]] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Determine whether the row has no values at all.
     *
     * @param  array<int, string|null>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (is_string($value) && trim($value) !== '') {
                return false;
            }
        }

        return true;
    }
}
