<?php

namespace App\Reports;

/**
 * Spreadsheet tools treat a cell starting with =, +, -, or @ as a formula.
 * A leading apostrophe keeps the value as text.
 */
final class SpreadsheetSafety
{
    public static function cell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        $lead = ltrim($value, " \t\r\n");

        if ($lead !== '' && in_array($lead[0], ['=', '+', '-', '@'], true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * @param  list<mixed>  $cells
     * @return list<mixed>
     */
    public static function row(array $cells): array
    {
        return array_map(self::cell(...), $cells);
    }
}
