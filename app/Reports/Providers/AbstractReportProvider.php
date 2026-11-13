<?php

namespace App\Reports\Providers;

use App\Models\User;
use App\Reports\Contracts\ReportProvider;
use App\Reports\ReportAudience;
use App\Reports\ReportColumn;
use App\Reports\ReportFilters;
use App\Reports\ReportKpi;
use App\Reports\ReportResult;
use Generator;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class AbstractReportProvider implements ReportProvider
{
    public function authorize(User $user): bool
    {
        $definition = $this->definition();

        if ($definition->audience === ReportAudience::Platform) {
            return $user->can('platform.access');
        }

        if (! $user->can('analytics.view')) {
            return false;
        }

        if ($definition->organisationPerspectiveOnly !== null) {
            $scope = ReportFilters::fromInput($user, [], $definition->filters)->scope;

            return $scope->perspective === $definition->organisationPerspectiveOnly;
        }

        return true;
    }

    public function estimateRows(ReportFilters $filters): int
    {
        $count = 0;
        foreach ($this->exportRows($filters) as $row) {
            $count++;
            if ($count > 5000) {
                return $count;
            }
        }

        return $count;
    }

    /**
     * @param  list<ReportKpi>  $kpis
     * @param  list<ReportColumn>  $columns
     * @param  list<array<string, mixed>>|LengthAwarePaginator<int, array<string, mixed>>  $rows
     * @param  list<string>  $findings
     * @param  array<string, mixed>  $meta
     */
    protected function result(
        ReportFilters $filters,
        array $kpis,
        array $columns,
        array|LengthAwarePaginator $rows,
        string $emptyMessage,
        array $findings = [],
        array $meta = [],
        ?int $page = null,
        int $perPage = 50,
    ): ReportResult {
        $definition = $this->definition();
        $payload = $rows;

        if (is_array($rows) && $definition->paginated && $page !== null) {
            $total = count($rows);
            $slice = array_slice($rows, ($page - 1) * $perPage, $perPage);
            $payload = new LengthAwarePaginator(
                $slice,
                $total,
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => request()->query()],
            );
        }

        return new ReportResult(
            definition: $definition,
            scope: $filters->scope,
            from: $filters->scope->from->toDateString(),
            to: $filters->scope->to->toDateString(),
            rangeAdjusted: $filters->rangeAdjusted,
            kpis: $kpis,
            columns: $columns,
            rows: $payload,
            emptyMessage: $emptyMessage,
            findings: $findings,
            meta: $meta,
        );
    }

    /**
     * @param  list<array{key: string, label: string}>  $columns
     * @return list<ReportColumn>
     */
    protected function columns(array $columns): array
    {
        return array_map(
            fn (array $column): ReportColumn => new ReportColumn($column['key'], $column['label']),
            $columns,
        );
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    protected function yieldRows(array $rows): Generator
    {
        foreach ($rows as $row) {
            yield $row;
        }
    }
}
