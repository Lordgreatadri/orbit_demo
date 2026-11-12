@php
    $definition = $result->definition;
    $rowList = $result->rowArrays();
@endphp

<x-layouts.app :title="$definition->title" wide>
    <x-page-header :title="$definition->title" :description="$definition->description" eyebrow="Platform reports">
        <div class="flex flex-wrap gap-2">
            @foreach ($definition->formats as $format)
                <x-button :href="route('admin.reports.export', ['report' => $definition->key, 'format' => $format->value])" variant="secondary" size="sm">
                    Export {{ $format->label() }}
                </x-button>
            @endforeach
            <x-button :href="route('admin.reports.index')" variant="ghost" size="sm">All reports</x-button>
        </div>
    </x-page-header>

    @if ($result->findings !== [])
        <ul class="mb-6 list-disc space-y-1 pl-5 text-sm text-fg-muted">
            @foreach ($result->findings as $finding)
                <li>{{ $finding }}</li>
            @endforeach
        </ul>
    @endif

    @if ($result->kpis !== [])
        <div class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
            @foreach ($result->kpis as $kpi)
                <x-stat :label="$kpi->label" :value="$kpi->value" :hint="$kpi->hint" :icon="$kpi->icon" :tone="$kpi->tone" />
            @endforeach
        </div>
    @endif

    <div class="table-panel overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <caption class="sr-only">{{ $definition->title }}</caption>
            <thead class="border-b border-edge bg-surface-muted/40 text-xs uppercase tracking-wide text-fg-muted">
                <tr>
                    @foreach ($result->columns as $column)
                        <th scope="col" class="px-4 py-3 font-medium">{{ $column->label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($rowList as $row)
                    <tr class="border-b border-edge last:border-0">
                        @foreach ($result->columns as $column)
                            <td class="px-4 py-3 text-fg">{{ $row[$column->key] ?? '' }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td class="px-4 py-6 text-fg-muted" colspan="{{ count($result->columns) }}">{{ $result->emptyMessage }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
