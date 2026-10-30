<x-layouts.app title="Organisations" wide>
    <x-page-header title="Organisations" description="Every tenant on the platform. Open an organisation to see its administrators, structure, and status controls." eyebrow="Tenants" />

    <x-filter-bar :action="route('admin.organizations.index')" :reset="route('admin.organizations.index')">
        <x-form.inline-search placeholder="Name, slug, or contact e-mail…" />
        <x-form.inline-select name="status" label="Status" :options="$statuses" />
        <x-form.inline-select name="type" label="Type" :options="$types" />
    </x-filter-bar>

    @if ($organizations->isEmpty())
        <x-empty-state title="No organisations match" />
    @else
        <x-data-table :paginator="$organizations" caption="Organisations">
            <thead>
                <tr>
                    <th scope="col">Organisation</th>
                    <th scope="col" class="hidden md:table-cell">Type</th>
                    <th scope="col" class="hidden lg:table-cell">Contact</th>
                    <th scope="col">Accounts</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="hidden sm:table-cell">Registered</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($organizations as $org)
                    <tr>
                        <td>
                            <a href="{{ route('admin.organizations.show', $org) }}" class="font-medium text-fg hover:text-primary hover:underline">{{ $org->name }}</a>
                            <p class="font-mono text-[0.7rem] text-fg-subtle">{{ $org->slug }}@if ($org->isDemo()) · <span class="text-warning">demo</span>@endif</p>
                        </td>
                        <td class="hidden md:table-cell">{{ $org->type->label() }}</td>
                        <td class="hidden truncate lg:table-cell">{{ $org->contact_email }}</td>
                        <td class="tabular-nums">{{ number_format($org->users_count) }}</td>
                        <td><x-badge :tone="$org->status->tone()">{{ $org->status->label() }}</x-badge></td>
                        <td class="hidden text-xs text-fg-muted sm:table-cell">{{ $org->created_at->toFormattedDateString() }}</td>
                        <td class="text-right"><x-button :href="route('admin.organizations.show', $org)" variant="ghost" size="sm" aria-label="Open {{ $org->name }}"><x-icon name="chevron-right" class="size-4" /></x-button></td>
                    </tr>
                @endforeach
            </tbody>
        </x-data-table>
        <p class="text-xs text-fg-subtle">Showing {{ $organizations->firstItem() }}–{{ $organizations->lastItem() }} of {{ number_format($organizations->total()) }}.</p>
    @endif
</x-layouts.app>
