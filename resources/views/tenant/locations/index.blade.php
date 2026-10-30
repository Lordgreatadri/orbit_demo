<x-layouts.app title="Locations" wide>
    <x-page-header title="Locations" description="Offices, field areas, and remote sites where staff are placed. A geofence (centre point and radius) is optional and will be used by the attendance module to validate check-ins.">
        @if ($canManage)
            <x-button type="button" size="sm" x-data @click="$dispatch('open-location-form', null)"><x-icon name="plus" class="size-4" /> New location</x-button>
        @endif
    </x-page-header>
    @error('location')<x-alert type="danger">{{ $message }}</x-alert>@enderror

    @if ($locations->isEmpty())
        <x-empty-state title="No locations yet" description="Add your head office or first site to get started.">
            @if ($canManage)
                <x-button type="button" size="sm" x-data @click="$dispatch('open-location-form', null)">New location</x-button>
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
                                <x-button type="button" variant="ghost" size="sm" x-data aria-label="Edit {{ $location->name }}" @click="$dispatch('open-location-form', @js($location->only(['uuid', 'name', 'address_line', 'city', 'region', 'latitude', 'longitude', 'geofence_radius_m', 'is_active']) + ['type' => $location->type->value]))"><x-icon name="pencil" class="size-4" /></x-button>
                                <x-confirm-form :action="route('org.locations.destroy', $location)" method="DELETE" :confirm="'Delete location '.$location->name.'?'">
                                    <x-button type="submit" variant="ghost" size="sm" class="text-danger hover:bg-danger-soft" aria-label="Delete {{ $location->name }}"><x-icon name="trash" class="size-4" /></x-button>
                                </x-confirm-form>
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </x-data-table>
    @endif

    @if ($canManage)
        <div
            x-data="locationForm(@js(old()), @js($errors->any()), @js(route('org.locations.store')), @js(route('org.locations.update', ['location' => '__UUID__'])))"
            x-on:open-location-form.window="open($event.detail)"
            x-show="visible" x-cloak
            class="fixed inset-0 z-50 flex items-end justify-center overflow-y-auto bg-black/50 p-4 backdrop-blur-[2px] sm:items-center"
            role="dialog" aria-modal="true" aria-labelledby="location-form-title"
            @keydown.escape.window="visible = false"
        >
            <form method="POST" :action="action" @click.outside="visible = false" class="card my-auto w-full max-w-2xl space-y-5 p-6">
                @csrf
                <template x-if="uuid"><input type="hidden" name="_method" value="PUT"></template>
                <input type="hidden" name="_uuid" :value="uuid ?? ''">
                <div class="flex items-start justify-between gap-3">
                    <h2 id="location-form-title" class="text-lg font-semibold text-fg" x-text="uuid ? 'Edit location' : 'New location'"></h2>
                    <button type="button" class="rounded-lg p-1.5 text-fg-muted hover:bg-surface-muted hover:text-fg" @click="visible = false" aria-label="Close"><x-icon name="x" class="size-5" /></button>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.input name="name" label="Name" required x-model="form.name" />
                    <x-form.select name="type" label="Type" :options="$types" required x-model="form.type" />
                    <div class="sm:col-span-2"><x-form.input name="address_line" label="Address" x-model="form.address_line" /></div>
                    <x-form.input name="city" label="City / town" x-model="form.city" />
                    <x-form.input name="region" label="Region" x-model="form.region" />
                </div>
                <fieldset class="rounded-lg border border-edge p-4">
                    <legend class="px-1 text-sm font-medium text-fg">Geofence <span class="font-normal text-fg-muted">(optional — set all three or none)</span></legend>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-form.input name="latitude" type="number" step="0.0000001" label="Latitude" placeholder="5.6037" x-model="form.latitude" />
                        <x-form.input name="longitude" type="number" step="0.0000001" label="Longitude" placeholder="-0.1870" x-model="form.longitude" />
                        <x-form.input name="geofence_radius_m" type="number" step="1" min="10" max="5000" label="Radius (m)" placeholder="150" x-model="form.geofence_radius_m" />
                    </div>
                </fieldset>
                <input type="hidden" name="is_active" value="0">
                <label class="flex items-center gap-3 text-sm text-fg">
                    <input type="checkbox" name="is_active" value="1" x-model="form.is_active" class="size-4 rounded border-edge-strong text-primary">
                    Active (available when placing staff)
                </label>
                <div class="flex justify-end gap-3 pt-1">
                    <x-button type="button" variant="ghost" @click="visible = false">Cancel</x-button>
                    <x-button type="submit" x-text="uuid ? 'Save changes' : 'Create location'"></x-button>
                </div>
            </form>
        </div>

        @push('scripts')
            <script>
                document.addEventListener('alpine:init', () => {
                    Alpine.data('locationForm', (old, hasErrors, storeUrl, updateTemplate) => ({
                        visible: hasErrors,
                        uuid: old._uuid ?? null,
                        form: {
                            name: old.name ?? '', type: old.type ?? 'office', address_line: old.address_line ?? '', city: old.city ?? '', region: old.region ?? '',
                            latitude: old.latitude ?? '', longitude: old.longitude ?? '', geofence_radius_m: old.geofence_radius_m ?? '',
                            is_active: old.is_active === undefined ? true : old.is_active == 1,
                        },
                        get action() { return this.uuid ? updateTemplate.replace('__UUID__', this.uuid) : storeUrl; },
                        open(detail) {
                            this.uuid = detail?.uuid ?? null;
                            this.form = {
                                name: detail?.name ?? '', type: detail?.type ?? 'office', address_line: detail?.address_line ?? '', city: detail?.city ?? '', region: detail?.region ?? '',
                                latitude: detail?.latitude ?? '', longitude: detail?.longitude ?? '', geofence_radius_m: detail?.geofence_radius_m ?? '',
                                is_active: detail ? !!detail.is_active : true,
                            };
                            this.visible = true;
                            this.$nextTick(() => this.$root.querySelector('input[name=name]')?.focus());
                        },
                    }));
                });
            </script>
        @endpush
    @endif
</x-layouts.app>
