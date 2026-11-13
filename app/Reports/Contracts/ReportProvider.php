<?php

namespace App\Reports\Contracts;

use App\Models\User;
use App\Reports\ReportDefinition;
use App\Reports\ReportFilters;
use App\Reports\ReportResult;
use Generator;

interface ReportProvider
{
    public function definition(): ReportDefinition;

    public function authorize(User $user): bool;

    public function build(ReportFilters $filters, int $page = 1): ReportResult;

    /**
     * Stream export rows without loading the full result set.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function exportRows(ReportFilters $filters): Generator;

    public function estimateRows(ReportFilters $filters): int;
}
