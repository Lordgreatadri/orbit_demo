<?php

namespace App\Reports\Export;

use App\Reports\Contracts\ReportProvider;
use App\Reports\ReportFilters;
use App\Reports\ReportResult;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

final class PdfExporter
{
    public const MAX_ROWS = 2000;

    public function download(
        ReportProvider $provider,
        ReportFilters $filters,
        ReportResult $result,
        string $filename,
    ): Response {
        $pdf = $this->make($provider, $filters, $result);

        return $pdf->download($filename);
    }

    public function write(
        ReportProvider $provider,
        ReportFilters $filters,
        ReportResult $result,
        string $path,
    ): void {
        file_put_contents($path, $this->make($provider, $filters, $result)->output());
    }

    private function make(
        ReportProvider $provider,
        ReportFilters $filters,
        ReportResult $result,
    ): \Barryvdh\DomPDF\PDF {
        $rows = [];
        foreach ($provider->exportRows($filters) as $row) {
            $rows[] = $row;
            if (count($rows) >= self::MAX_ROWS) {
                break;
            }
        }

        return Pdf::loadView('reports.pdf', [
            'result' => $result,
            'rows' => $rows,
            'truncated' => count($rows) >= self::MAX_ROWS,
        ])->setPaper('a4', 'landscape');
    }
}
