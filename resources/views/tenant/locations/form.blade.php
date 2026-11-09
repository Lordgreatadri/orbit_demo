@php $editing = $location->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit '.$location->name : 'New location'" wide>
    <x-page-header
        :title="$editing ? 'Edit location' : 'New location'"
        :description="$editing ? $location->name : 'Add an office, field area, or remote site. A geofence is optional.'"
        eyebrow="Locations"
    >
        <x-button :href="route('org.locations.index')" variant="ghost" size="sm"><x-icon name="chevron-left" class="size-4" /> Back</x-button>
    </x-page-header>

    @error('location')
        <x-alert type="danger">{{ $message }}</x-alert>
    @enderror

    <form method="POST" action="{{ $editing ? route('org.locations.update', $location) : route('org.locations.store') }}" class="space-y-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-card title="Location">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="name" label="Name" :value="$location->name" required />
                <x-form.select name="type" label="Type" :options="$types" :value="$location->type?->value ?? 'office'" required />
                <div class="sm:col-span-2">
                    <x-form.input name="address_line" label="Address" :value="$location->address_line" />
                </div>
                <x-form.input name="city" label="City / town" :value="$location->city" />
                <x-form.input name="region" label="Region" :value="$location->region" />
            </div>
        </x-card>

        <x-card title="Geofence" description="Optional. Enter latitude, longitude, and radius together, or leave all three empty. Attendance uses these values only when a check-in asks for a location check.">
            <div class="grid gap-5 sm:grid-cols-3">
                <x-form.input name="latitude" type="number" step="0.0000001" label="Latitude" :value="$location->latitude" placeholder="5.6037" />
                <x-form.input name="longitude" type="number" step="0.0000001" label="Longitude" :value="$location->longitude" placeholder="-0.1870" />
                <x-form.input name="geofence_radius_m" type="number" step="1" min="10" max="5000" label="Radius (m)" :value="$location->geofence_radius_m" placeholder="150" />
            </div>
        </x-card>

        <x-card title="Availability">
            <input type="hidden" name="is_active" value="0">
            <label class="flex items-center gap-3 text-sm text-fg">
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    @checked((string) old('is_active', $editing ? (int) $location->is_active : 1) === '1')
                    class="size-4 rounded border-edge-strong text-primary"
                >
                Active (available when placing staff)
            </label>
        </x-card>

        <div class="flex flex-wrap items-center justify-end gap-3">
            <x-button :href="route('org.locations.index')" variant="ghost">Cancel</x-button>
            <x-button type="submit">{{ $editing ? 'Save changes' : 'Create location' }}</x-button>
        </div>
    </form>

    @if ($editing)
        @can('delete', $location)
            <x-card id="delete-location" title="Delete location" description="Deletion is permanent. It is refused while any staff record still uses this location as its primary site.">
                @if (($location->staff_members_count ?? 0) > 0)
                    <x-alert type="warning" title="Staff are still placed here">
                        Reassign the {{ number_format($location->staff_members_count) }} {{ $location->staff_members_count === 1 ? 'person' : 'people' }} who use this as a primary location, or mark the location inactive. The locations permission is required, and it does not override staff who are still placed here.
                    </x-alert>
                @else
                    <form method="POST" action="{{ route('org.locations.destroy', $location) }}">
                        @csrf
                        @method('DELETE')
                        <x-button type="submit" variant="danger" size="sm"><x-icon name="trash" class="size-4" /> Delete location</x-button>
                    </form>
                @endif
            </x-card>
        @endcan
    @endif
</x-layouts.app>
