<?php

namespace App\Reports;

final class ReportColumn
{
    public function __construct(
        public string $key,
        public string $label,
    ) {}
}
