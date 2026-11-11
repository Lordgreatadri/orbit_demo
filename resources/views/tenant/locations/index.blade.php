<x-layouts.app title="Locations" wide>
    <x-page-header title="Locations" description="Offices, field areas, and remote sites where people are placed. An optional geofence is used when a check-in verifies position." eyebrow="Attendance">
        @if ($canManage)
            <x-button :href="route('org.locations.create')" size="sm"><x-icon name="plus" class="size-4" /> New location</x-button>
        @endif
    </x-page-header>
    @error('location')<x-alert type="danger">{{ $message }}</x-alert>@enderror

    @if ($locations->isEmpty())
        <x-empty-state title="No locations yet" description="Add your head office or first site to get started.">
            @if ($canManage)
                <x-button :href="route('org.locations.create')" size="sm">New location</x-button>
            @endif
        </x-empty-state>
    @else
        <x-data-table :paginator="$locations" caption="Locations">
            <thead>
                <tr>
                    <th scope="col">Location</th>
                    <th scope="col" class="hidden sm:table-cell">Type</th>
                    <th scope="col" class="hidden md:table-cell">Address</th>
                    <th scope="col" class="hidden lg:table-cell">Geofence</th>
                    <th scope="col">Staff</th>
                    <th scope="col">Status</th>
                    @if ($canManage)<th scope="col"><span class="sr-only">Actions</span></th>@endif
                </tr>
            </thead>
            <tbody>
                @foreach ($locations as $location)
                    <tr>
                        <td><span class="font-medium text-fg">{{ $location->name }}</span></td>
                        <td class="hidden sm:table-cell">{{ $location->type->label() }}</td>
                        <td class="hidden max-w-xs truncate md:table-cell">{{ collect([$location->address_line, $location->city, $location->region])->filter()->join(', ') ?: '—' }}</td>
                        <td class="hidden lg:table-cell">
                            @if ($location->hasGeofence())
                                <span class="font-mono text-xs text-fg-muted">{{ $location->latitude }}, {{ $location->longitude }} · {{ $location->geofence_radius_m }} m</span>
                            @else
                                <span class="text-xs text-fg-subtle">None</span>
                            @endif
                        </td>
                        <td class="tabular-nums">{{ number_format($location->staff_members_count) }}</td>
                        <td><x-badge :tone="$location->is_active ? 'success' : 'neutral'">{{ $location->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                        @if ($canManage)
                            <td class="text-right whitespace-nowrap">
                                @can('update', $location)
                                    <a href="{{ route('org.locations.edit', $location) }}" class="inline-flex size-9 items-center justify-center rounded-lg text-fg-muted hover:bg-surface-muted hover:text-primary focus-visible:outline-2" aria-label="Edit {{ $location->name }}">
                                        <x-icon name="pencil" class="size-4" />
                                    </a>
                                @endcan
                                @can('delete', $location)
                                    <button type="button" class="inline-flex size-9 items-center justify-center rounded-lg text-danger hover:bg-danger-soft focus-visible:outline-2" aria-label="Delete {{ $location->name }}" onclick="this.nextElementSibling.showModal()">
                                        <x-icon name="trash" class="size-4" />
                                    </button>
                                    <dialog class="w-[min(100%,24rem)] rounded-xl border border-edge bg-surface p-0 text-fg shadow-lift backdrop:bg-black/50">
                                        <form method="POST" action="{{ route('org.locations.destroy', $location) }}" class="space-y-4 p-5">
                                            @csrf
                                            @method('DELETE')
                                            <h2 class="text-base font-semibold text-fg">Delete {{ $location->name }}?</h2>
                                            <p class="text-sm text-fg-muted">This cannot be undone. If staff still use this as their primary location, the delete is refused and the location stays in the register.</p>
                                            <div class="flex justify-end gap-2">
                                                <x-button type="button" variant="ghost" size="sm" onclick="this.closest('dialog').close()">Cancel</x-button>
                                                <x-button type="submit" variant="danger" size="sm">Delete location</x-button>
                                            </div>
                                        </form>
                                    </dialog>
                                @endcan
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </x-data-table>
    @endif
</x-layouts.app>
