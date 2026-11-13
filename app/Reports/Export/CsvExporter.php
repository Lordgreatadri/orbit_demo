<?php

namespace App\Reports\Export;

use App\Reports\Contracts\ReportProvider;
use App\Reports\ReportFilters;
use App\Reports\ReportFormat;
use App\Reports\ReportResult;
use App\Reports\SpreadsheetSafety;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CsvExporter
{
    public function stream(
        ReportProvider $provider,
        ReportFilters $filters,
        ReportResult $result,
        string $filename,
    ): StreamedResponse {
        return response()->streamDownload(function () use ($provider, $filters, $result): void {
            $this->writeHandle($provider, $filters, $result, fopen('php://output', 'w'));
        }, $filename, ['Content-Type' => ReportFormat::Csv->contentType()]);
    }

    public function write(
        ReportProvider $provider,
        ReportFilters $filters,
        ReportResult $result,
        string $path,
    ): void {
        $handle = fopen($path, 'w');
        $this->writeHandle($provider, $filters, $result, $handle);
        fclose($handle);
    }

    /**
     * @param  resource  $handle
     */
    private function writeHandle(
        ReportProvider $provider,
        ReportFilters $filters,
        ReportResult $result,
        $handle,
    ): void {
        fputcsv($handle, $result->columnLabels());

        foreach ($provider->exportRows($filters) as $row) {
            $cells = [];
            foreach ($result->columnKeys() as $key) {
                $cells[] = $row[$key] ?? '';
            }
            fputcsv($handle, SpreadsheetSafety::row($cells));
        }
    }
}
