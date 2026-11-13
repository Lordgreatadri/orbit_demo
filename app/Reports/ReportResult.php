<?php

namespace App\Reports;

use App\Analytics\AnalyticsScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class ReportResult
{
    /**
     * @param  list<ReportKpi>  $kpis
     * @param  list<ReportColumn>  $columns
     * @param  list<array<string, mixed>>|Collection<int, array<string, mixed>>|LengthAwarePaginator<int, array<string, mixed>>  $rows
     * @param  list<string>  $findings
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public ReportDefinition $definition,
        public AnalyticsScope $scope,
        public string $from,
        public string $to,
        public bool $rangeAdjusted,
        public array $kpis,
        public array $columns,
        public array|Collection|LengthAwarePaginator $rows,
        public string $emptyMessage,
        public array $findings = [],
        public array $meta = [],
    ) {}

    /**
     * @return list<string>
     */
    public function columnLabels(): array
    {
        return array_map(fn (ReportColumn $column): string => $column->label, $this->columns);
    }

    /**
     * @return list<string>
     */
    public function columnKeys(): array
    {
        return array_map(fn (ReportColumn $column): string => $column->key, $this->columns);
    }

    /**
     * Flatten paginated or collected rows for export serialization of small sets.
     *
     * @return list<array<string, mixed>>
     */
    public function rowArrays(): array
    {
        if ($this->rows instanceof LengthAwarePaginator) {
            return array_map(
                fn ($row): array => is_array($row) ? $row : (array) $row,
                $this->rows->items(),
            );
        }

        return collect($this->rows)
            ->map(fn ($row): array => is_array($row) ? $row : (array) $row)
            ->values()
            ->all();
    }
}
