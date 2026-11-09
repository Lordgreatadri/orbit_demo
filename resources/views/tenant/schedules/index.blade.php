<x-layouts.app title="{{ $lesson ? 'Lessons' : 'Schedules' }}" wide>
    <x-page-header :title="$lesson ? 'Lessons and schedules' : 'Schedules'" description="Times use the organisation time zone. A shift may cross midnight." />
    @error('schedule')<x-alert type="danger">{{ $message }}</x-alert>@enderror

    @if ($canManage)
        <x-card title="New {{ $lesson ? 'lesson' : 'shift' }}">
            <form method="POST" action="{{ route('org.schedules.store') }}" class="grid gap-4 sm:grid-cols-2">
                @csrf
                <x-form.input name="name" label="Name" required />
                <x-form.select name="department_uuid" label="Department" :options="$departments->mapWithKeys(fn ($department) => [$department->uuid => $department->name])->prepend('Any', '')->all()" />
                <x-form.select name="location_uuid" label="Location" :options="$locations->mapWithKeys(fn ($location) => [$location->uuid => $location->name])->prepend('Any', '')->all()" />
                <x-form.input type="time" name="starts_at" label="Starts" required />
                <x-form.input type="time" name="ends_at" label="Ends" required />
                <x-form.input type="date" name="effective_from" label="Effective from" required />
                <x-form.input type="date" name="effective_until" label="Effective until" />
                <div><x-button type="submit" size="sm">Save draft</x-button></div>
            </form>
        </x-card>

        @foreach ($shifts as $shift)
            <x-card :title="$shift->name" :description="$shift->starts_at.' – '.$shift->ends_at.($shift->crosses_midnight ? ' · crosses midnight' : '')">
                <x-slot:actions><x-badge>{{ $shift->status->label() }}</x-badge></x-slot:actions>
                <div class="flex flex-wrap gap-3">
                    @if ($shift->status->value === 'draft')
                        <form method="POST" action="{{ route('org.schedules.publish', $shift) }}">@csrf<x-button type="submit" size="sm">Publish</x-button></form>
                    @endif
                    <form method="POST" action="{{ route('org.schedules.assign', $shift) }}" class="flex flex-wrap items-end gap-2">
                        @csrf
                        <x-form.select name="staff_uuid" label="Person" :options="$staff->mapWithKeys(fn ($person) => [$person->uuid => $person->first_name.' '.$person->last_name])->all()" />
                        <x-form.input type="date" name="work_date" label="Date" />
                        <x-button type="submit" size="sm" variant="secondary">Assign</x-button>
                    </form>
                </div>
            </x-card>
        @endforeach
    @endif

    <x-card title="Upcoming assignments" :padding="false">
        <ul class="divide-y divide-edge">
            @forelse ($assignments as $assignment)
                <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                    <span><span class="font-medium text-fg">{{ $assignment->shift->name }}</span> · {{ $assignment->work_date->toFormattedDateString() }} · {{ $assignment->staffMember?->name }}</span>
                    <span class="text-fg-muted">{{ \Illuminate\Support\Str::of($assignment->shift->starts_at)->substr(0, 5) }}–{{ \Illuminate\Support\Str::of($assignment->shift->ends_at)->substr(0, 5) }}</span>
                </li>
            @empty
                <li class="px-5 py-4 text-sm text-fg-muted">Nothing scheduled.</li>
            @endforelse
        </ul>
    </x-card>
</x-layouts.app>
