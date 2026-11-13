<?php

namespace App\Reports\Export;

use App\Reports\Contracts\ReportProvider;
use App\Reports\ReportFilters;
use App\Reports\ReportFormat;
use App\Reports\ReportResult;
use App\Reports\SpreadsheetSafety;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class XlsxExporter
{
    public function stream(
        ReportProvider $provider,
        ReportFilters $filters,
        ReportResult $result,
        string $filename,
    ): StreamedResponse {
        return response()->streamDownload(function () use ($provider, $filters, $result): void {
            $tmp = tempnam(sys_get_temp_dir(), 'orbit-xlsx-');
            $this->write($provider, $filters, $result, $tmp);
            readfile($tmp);
            @unlink($tmp);
        }, $filename, ['Content-Type' => ReportFormat::Xlsx->contentType()]);
    }

    public function write(
        ReportProvider $provider,
        ReportFilters $filters,
        ReportResult $result,
        string $path,
    ): void {
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($result->columnLabels()));

        foreach ($provider->exportRows($filters) as $row) {
            $cells = [];
            foreach ($result->columnKeys() as $key) {
                $value = $row[$key] ?? '';
                $safe = SpreadsheetSafety::cell(is_scalar($value) || $value === null ? $value : (string) $value);
                $cells[] = $safe;
            }
            $writer->addRow(Row::fromValues($cells));
        }

        $writer->close();
    }
}
