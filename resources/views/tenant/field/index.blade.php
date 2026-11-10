<x-layouts.app title="Field monitoring" wide>
    <x-page-header title="Field monitoring" description="Location is captured when an activity starts or ends. ORBIT does not track people in the background." />

    @error('attendance')<x-alert type="danger">{{ $message }}</x-alert>@enderror

    <x-card title="Active activities" description="Delayed and offline are calculated from the last report, not from a live stream.">
        @if ($activities->isEmpty())
            <p class="text-sm text-fg-muted">No open field activity.</p>
        @else
            <ul class="space-y-3">
                @foreach ($activities as $activity)
                    @php $display = $activity->displayStatus($delayMinutes); @endphp
                    <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-edge p-3">
                        <div>
                            <p class="font-medium text-fg">{{ $activity->staffMember?->name }}</p>
                            <p class="text-xs text-fg-muted">{{ $activity->assignment?->title }} · {{ $activity->assignment?->location?->name }}</p>
                            @if ($activity->last_latitude)
                                <p class="font-mono text-xs text-fg-subtle">{{ $activity->last_latitude }}, {{ $activity->last_longitude }} · {{ $activity->last_reported_at?->timezone($timezone)->diffForHumans() }}</p>
                                <a class="text-xs text-primary hover:underline" target="_blank" rel="noopener noreferrer" href="https://www.openstreetmap.org/?mlat={{ $activity->last_latitude }}&mlon={{ $activity->last_longitude }}#map=16/{{ $activity->last_latitude }}/{{ $activity->last_longitude }}">Open map</a>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-badge :tone="$display->tone()">{{ $display->label() }}</x-badge>
                            @if ($activity->ended_at === null && (auth()->user()->can('attendance.manage') || auth()->user()->staffMember?->is($activity->staffMember)))
                                <form method="POST" action="{{ route('org.field.finish', $activity) }}" class="flex flex-wrap gap-2" x-data="{ lat: '', lng: '', accuracy: '', captured: '' }">
                                    @csrf
                                    <input type="hidden" name="latitude" x-model="lat">
                                    <input type="hidden" name="longitude" x-model="lng">
                                    <input type="hidden" name="accuracy_m" x-model="accuracy">
                                    <input type="hidden" name="captured_at" x-model="captured">
                                    <x-button type="button" size="sm" variant="secondary" @click="navigator.geolocation.getCurrentPosition((p) => { lat = p.coords.latitude; lng = p.coords.longitude; accuracy = Math.round(p.coords.accuracy); captured = new Date().toISOString(); })">Capture location</x-button>
                                    <x-button type="submit" size="sm" variant="secondary">Finish</x-button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    @if ($canManage)
        <x-card title="Find a person or work area" description="People appear after a search. Work areas show the first 60, or the matches for a search.">
            <form method="GET" action="{{ route('org.field.index') }}" class="grid gap-4 sm:grid-cols-3">
                <x-form.input id="field-person-find" name="person" label="Person" :value="$personFind" hint="At least two letters." />
                <x-form.input id="field-place-find" name="place" label="Work area" :value="$placeFind" />
                <div class="flex items-end"><x-button type="submit" variant="secondary" size="sm">Search</x-button></div>
            </form>
        </x-card>

        <x-card title="New assignment">
            <form method="POST" action="{{ route('org.field.store') }}" class="grid gap-4 sm:grid-cols-2">
                @csrf
                <x-form.select id="field-person" name="staff_uuid" label="Person" :options="$staff->mapWithKeys(fn ($person) => [$person->uuid => $person->first_name.' '.$person->last_name])->all()" required />
                <x-form.select id="field-place" name="location_uuid" label="Work area" :options="$locations->mapWithKeys(fn ($location) => [$location->uuid => $location->name])->all()" required />
                <x-form.input name="title" label="Task" required />
                <x-form.input name="instructions" label="Instructions" />
                <x-form.input type="date" name="starts_on" label="Starts" required />
                <x-form.input type="date" name="ends_on" label="Ends" required />
                <div><x-button type="submit" size="sm">Assign</x-button></div>
            </form>
        </x-card>
    @endif

    <x-card title="Assignments" :padding="false">
        <ul class="divide-y divide-edge">
            @forelse ($assignments as $assignment)
                <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 text-sm">
                    <div>
                        <p class="font-medium text-fg">{{ $assignment->title }}</p>
                        <p class="text-xs text-fg-muted">{{ $assignment->staffMember?->name }} · {{ $assignment->location?->name }} · {{ $assignment->starts_on->toDateString() }} – {{ $assignment->ends_on->toDateString() }}</p>
                    </div>
                    <x-badge :tone="$assignment->status->tone()">{{ $assignment->status->label() }}</x-badge>
                    @if ($assignment->status->value !== 'completed' && (auth()->user()->can('attendance.manage') || auth()->user()->staffMember?->is($assignment->staffMember)))
                        <form method="POST" action="{{ route('org.field.start', $assignment) }}" class="flex flex-wrap gap-2" x-data="{ lat: '', lng: '', accuracy: '', captured: '' }">
                            @csrf
                            <input type="hidden" name="latitude" x-model="lat">
                            <input type="hidden" name="longitude" x-model="lng">
                            <input type="hidden" name="accuracy_m" x-model="accuracy">
                            <input type="hidden" name="captured_at" x-model="captured">
                            <x-button type="button" size="sm" variant="secondary" @click="navigator.geolocation.getCurrentPosition((p) => { lat = p.coords.latitude; lng = p.coords.longitude; accuracy = Math.round(p.coords.accuracy); captured = new Date().toISOString(); })">Capture location</x-button>
                            <x-button type="submit" size="sm">Start</x-button>
                        </form>
                    @endif
                </li>
            @empty
                <li class="px-5 py-4 text-sm text-fg-muted">No assignments.</li>
            @endforelse
        </ul>
        @if ($assignments->hasPages())
            <div class="flex flex-col gap-3 border-t border-edge px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-fg-subtle">Showing {{ $assignments->firstItem() }}–{{ $assignments->lastItem() }} of {{ $assignments->total() }}</p>
                <div>{{ $assignments->links() }}</div>
            </div>
        @endif
    </x-card>
</x-layouts.app>
