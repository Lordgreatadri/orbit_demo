<x-layouts.app title="Platform reports" wide>
    <x-page-header title="Platform reports" description="Organisation and account aggregates only. Tenant attendance, leave, schedules, and member names are never included." eyebrow="Platform" />

    <div class="table-panel overflow-hidden">
        <table class="min-w-full text-left text-sm">
            <caption class="sr-only">Platform reports</caption>
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
                            <x-button :href="route('admin.reports.show', $definition->key)" size="sm" variant="secondary">Open</x-button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.app>
