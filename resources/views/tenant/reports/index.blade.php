<x-layouts.app title="Reports" wide>
    <x-page-header title="Reports & exports" description="Operational reports reuse the same scope and formulas as analytics. Filters can narrow what you already may see; they cannot widen it." eyebrow="Insights" />

    @foreach ($grouped as $category => $definitions)
        <section class="mb-8" aria-labelledby="report-cat-{{ \Illuminate\Support\Str::slug($category) }}">
            <h2 id="report-cat-{{ \Illuminate\Support\Str::slug($category) }}" class="section-title mb-3">{{ $category }}</h2>
            <div class="table-panel overflow-hidden">
                <table class="min-w-full text-left text-sm">
                    <caption class="sr-only">{{ $category }} reports</caption>
                    <thead class="border-b border-edge bg-surface-muted/40 text-xs uppercase tracking-wide text-fg-muted">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-medium">Report</th>
                            <th scope="col" class="px-4 py-3 font-medium">Exports</th>
                            <th scope="col" class="px-4 py-3 font-medium"><span class="sr-only">Open</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($definitions as $definition)
                            <tr class="border-b border-edge last:border-0">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-fg">{{ $definition->title }}</p>
                                    <p class="mt-1 text-fg-muted">{{ $definition->description }}</p>
                                </td>
                                <td class="px-4 py-3 text-fg-muted">
                                    {{ collect($definition->formats)->map->label()->join(', ') }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <x-button :href="route('org.reports.show', $definition->key)" size="sm" variant="secondary">Open</x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach

    @if ($exports->isNotEmpty())
        <section aria-labelledby="ready-exports-heading">
            <h2 id="ready-exports-heading" class="section-title mb-3">Ready exports</h2>
            <ul class="space-y-2">
                @foreach ($exports as $export)
                    <li class="flex items-center justify-between gap-3 border-b border-edge py-2 text-sm">
                        <span>{{ $export->report_key }} · {{ strtoupper($export->format) }}</span>
                        <x-button :href="route('org.reports.downloads.show', $export->uuid)" size="sm" variant="secondary">Download</x-button>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.app>
