@php
    $definition = $result->definition;
    $fromLabel = \Illuminate\Support\Carbon::parse($result->from)->format('j M Y');
    $toLabel = \Illuminate\Support\Carbon::parse($result->to)->format('j M Y');
    $rows = $result->rows;
    $isPaginator = $rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator;
    $rowList = $isPaginator ? $rows->items() : (is_array($rows) ? $rows : $rows->all());
@endphp

<x-layouts.app :title="$definition->title" wide>
    <x-page-header :title="$definition->title" :description="$fromLabel.' – '.$toLabel.' · '.$result->scope->label().'. '.$definition->description" eyebrow="Reports">
        <div class="flex flex-wrap gap-2">
            @foreach ($definition->formats as $format)
                <x-button :href="route('org.reports.export', ['report' => $definition->key, 'format' => $format->value] + $query)" variant="secondary" size="sm">
                    Export {{ $format->label() }}
                </x-button>
            @endforeach
            <x-button :href="route('org.reports.index')" variant="ghost" size="sm">All reports</x-button>
        </div>
    </x-page-header>

    @if (count($definition->filters) > 0)
        <x-filter-bar :action="route('org.reports.show', $definition->key)" :reset="route('org.reports.show', $definition->key)">
            <label class="flex min-w-[9rem] flex-1 flex-col gap-1 text-xs font-medium text-fg-muted"><span>From</span><input type="date" name="from" value="{{ $selected['from'] }}" class="min-h-11 w-full rounded-lg border border-edge bg-surface px-2.5 text-sm text-fg focus-visible:outline-2"></label>
            <label class="flex min-w-[9rem] flex-1 flex-col gap-1 text-xs font-medium text-fg-muted"><span>To</span><input type="date" name="to" value="{{ $selected['to'] }}" class="min-h-11 w-full rounded-lg border border-edge bg-surface px-2.5 text-sm text-fg focus-visible:outline-2"></label>
            @if (in_array('department', $definition->filters, true) && count($options['departments']) > 0)
                <label class="flex min-w-[9rem] flex-1 flex-col gap-1 text-xs font-medium text-fg-muted"><span>Department</span>
                    <select name="department" class="min-h-11 w-full rounded-lg border border-edge bg-surface px-2.5 text-sm text-fg focus-visible:outline-2">
                        <option value="">All in scope</option>
                        @foreach ($options['departments'] as $department)
                            <option value="{{ $department['uuid'] }}" @selected($selected['department'] === $department['uuid'])>{{ $department['name'] }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            @if (in_array('location', $definition->filters, true))
                <label class="flex min-w-[9rem] flex-1 flex-col gap-1 text-xs font-medium text-fg-muted"><span>Location</span>
                    <select name="location" class="min-h-11 w-full rounded-lg border border-edge bg-surface px-2.5 text-sm text-fg focus-visible:outline-2">
                        <option value="">All locations</option>
                        @foreach ($options['locations'] as $location)
                            <option value="{{ $location['uuid'] }}" @selected($selected['location'] === $location['uuid'])>{{ $location['name'] }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            @if (in_array('person', $definition->filters, true) && count($options['people']) > 0)
                <label class="flex min-w-[9rem] flex-1 flex-col gap-1 text-xs font-medium text-fg-muted"><span>Person</span>
                    <select name="person" class="min-h-11 w-full rounded-lg border border-edge bg-surface px-2.5 text-sm text-fg focus-visible:outline-2">
                        <option value="">All people in scope</option>
                        @foreach ($options['people'] as $person)
                            <option value="{{ $person['uuid'] }}" @selected($selected['person'] === $person['uuid'])>{{ $person['name'] }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            @if (in_array('status', $definition->filters, true))
                <label class="flex min-w-[9rem] flex-1 flex-col gap-1 text-xs font-medium text-fg-muted"><span>Status</span>
                    <select name="status" class="min-h-11 w-full rounded-lg border border-edge bg-surface px-2.5 text-sm text-fg focus-visible:outline-2">
                        <option value="">All statuses</option>
                        @foreach ($options['statuses'] as $status)
                            <option value="{{ $status['value'] }}" @selected($selected['status'] === $status['value'])>{{ $status['label'] }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            @if (in_array('leave_type', $definition->filters, true))
                <label class="flex min-w-[9rem] flex-1 flex-col gap-1 text-xs font-medium text-fg-muted"><span>Leave type</span>
                    <select name="leave_type" class="min-h-11 w-full rounded-lg border border-edge bg-surface px-2.5 text-sm text-fg focus-visible:outline-2">
                        <option value="">All types</option>
                        @foreach ($options['leave_types'] as $type)
                            <option value="{{ $type['uuid'] }}" @selected($selected['leave_type'] === $type['uuid'])>{{ $type['name'] }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            @if (in_array('leave_status', $definition->filters, true))
                <label class="flex min-w-[9rem] flex-1 flex-col gap-1 text-xs font-medium text-fg-muted"><span>Leave status</span>
                    <select name="leave_status" class="min-h-11 w-full rounded-lg border border-edge bg-surface px-2.5 text-sm text-fg focus-visible:outline-2">
                        <option value="">All statuses</option>
                        @foreach ($options['leave_statuses'] as $status)
                            <option value="{{ $status['value'] }}" @selected($selected['leave_status'] === $status['value'])>{{ $status['label'] }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            @if (in_array('method', $definition->filters, true))
                <label class="flex min-w-[9rem] flex-1 flex-col gap-1 text-xs font-medium text-fg-muted"><span>Method</span>
                    <select name="method" class="min-h-11 w-full rounded-lg border border-edge bg-surface px-2.5 text-sm text-fg focus-visible:outline-2">
                        <option value="">All methods</option>
                        @foreach ($options['methods'] as $method)
                            <option value="{{ $method['value'] }}" @selected($selected['method'] === $method['value'])>{{ $method['label'] }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            @if (in_array('arrangement', $definition->filters, true))
                <label class="flex min-w-[9rem] flex-1 flex-col gap-1 text-xs font-medium text-fg-muted"><span>Arrangement</span>
                    <select name="arrangement" class="min-h-11 w-full rounded-lg border border-edge bg-surface px-2.5 text-sm text-fg focus-visible:outline-2">
                        <option value="">All arrangements</option>
                        @foreach ($options['arrangements'] as $arrangement)
                            <option value="{{ $arrangement['value'] }}" @selected($selected['arrangement'] === $arrangement['value'])>{{ $arrangement['label'] }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
        </x-filter-bar>
    @endif

    @if ($result->rangeAdjusted)
        <x-alert type="info" title="Range shortened">
            Reports cover at most 62 days. A longer or reversed range is shown as the latest 30 days ending on the selected end date.
        </x-alert>
    @endif

    @if ($result->findings !== [])
        <section class="mb-6" aria-label="Key findings">
            <ul class="list-disc space-y-1 pl-5 text-sm text-fg-muted">
                @foreach ($result->findings as $finding)
                    <li>{{ $finding }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($result->kpis !== [])
        <section class="mb-6" aria-label="Totals">
            <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                @foreach ($result->kpis as $kpi)
                    <x-stat :label="$kpi->label" :value="$kpi->value" :hint="$kpi->hint" :icon="$kpi->icon" :tone="$kpi->tone" :emphasis="$kpi->emphasis" :meter="$kpi->meter" />
                @endforeach
            </div>
        </section>
    @endif

    <section aria-labelledby="report-table-heading">
        <h2 id="report-table-heading" class="section-title mb-3">Results</h2>
        @if ($rowList === [])
            <x-empty-state :title="$result->emptyMessage" description="Change the period or filters, or wait until records exist for this scope." />
        @else
            <div class="table-panel overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <caption class="sr-only">{{ $definition->title }} for {{ $fromLabel }} to {{ $toLabel }}</caption>
                    <thead class="border-b border-edge bg-surface-muted/40 text-xs uppercase tracking-wide text-fg-muted">
                        <tr>
                            @foreach ($result->columns as $column)
                                <th scope="col" class="px-4 py-3 font-medium">{{ $column->label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rowList as $row)
                            <tr class="border-b border-edge last:border-0">
                                @foreach ($result->columns as $column)
                                    <td class="px-4 py-3 text-fg">{{ $row[$column->key] ?? '' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($isPaginator)
                <div class="mt-4">{{ $rows->links() }}</div>
            @endif
        @endif
    </section>
</x-layouts.app>
