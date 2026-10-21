<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams tabular data as a CSV download for reports, templates and
 * statements. Rows are written as given so callers can compose sectioned
 * files with their own heading rows.
 */
class CsvExporter
{
    /**
     * Stream the given rows as a CSV download.
     *
     * @param  iterable<int, array<int, string|int|float|null>>  $rows
     */
    public function stream(string $filename, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
