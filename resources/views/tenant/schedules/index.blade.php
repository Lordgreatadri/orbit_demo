<x-layouts.app title="{{ $lesson ? 'Lessons' : 'Schedules' }}" wide>
    <x-page-header :title="$lesson ? 'Lessons and schedules' : 'Schedules'" description="A person can have more than one work period on a date. Times use the organisation time zone. A shift may cross midnight. Draft shifts stay out of coverage figures until they are published." eyebrow="Workforce" />
    @error('schedule')<x-alert type="danger">{{ $message }}</x-alert>@enderror

    @if ($canManage)
        @php
            $arrangementOptions = $arrangements->mapWithKeys(fn ($arrangement) => [$arrangement->uuid => $arrangement->name])->all();
            $departmentOptions = $departments->mapWithKeys(fn ($department) => [$department->uuid => $department->name])->all();
            $locationOptions = $locations->mapWithKeys(fn ($location) => [$location->uuid => $location->name])->all();
        @endphp

        @if ($needsArrangements)
            <x-card title="Work arrangements" description="Office, remote, field, and flexible are the kinds ORBIT understands. The names belong to this organisation.">
                <form method="POST" action="{{ route('org.schedules.arrangements') }}">
                    @csrf
                    <x-button type="submit" size="sm">Add standard arrangements</x-button>
                </form>
            </x-card>
        @endif

        <x-card title="Find a department or location" description="The lists below show up to 60 matches. Search when the organisation has more.">
            <form method="GET" action="{{ route('org.schedules.index') }}" class="grid gap-4 sm:grid-cols-3">
                <x-form.input id="schedule-unit" name="unit" label="Department" :value="$unit" />
                <x-form.input id="schedule-place" name="place" label="Location" :value="$place" />
                <div class="flex items-end"><x-button type="submit" size="sm" variant="secondary">Search</x-button></div>
            </form>
        </x-card>

        <x-card title="New {{ $lesson ? 'lesson' : 'shift' }}">
            <form method="POST" action="{{ route('org.schedules.store') }}" class="grid gap-4 sm:grid-cols-2">
                @csrf
                <x-form.input id="shift-name" name="name" label="Name" required />
                <x-form.select id="shift-arrangement" name="arrangement_uuid" label="Work arrangement" :options="$arrangementOptions" placeholder="Organisation default" />
                <x-form.select id="shift-department" name="department_uuid" label="Department" :options="['' => 'Any'] + $departmentOptions" />
                <x-form.select id="shift-location" name="location_uuid" label="Expected location" :options="['' => 'Primary location'] + $locationOptions" />
                <x-form.input id="shift-starts" type="time" name="starts_at" label="Starts" required />
                <x-form.input id="shift-ends" type="time" name="ends_at" label="Ends" required />
                <x-form.input id="shift-from" type="date" name="effective_from" label="Effective from" required />
                <x-form.input id="shift-until" type="date" name="effective_until" label="Effective until" />
                <div><x-button type="submit" size="sm">Save draft</x-button></div>
            </form>
        </x-card>

        <x-card title="Working pattern" description="Weekdays, a department, and a work arrangement. Apply writes one assignment per person per matching day, and skips the non-working calendar.">
            <form method="POST" action="{{ route('org.schedules.patterns.store') }}" class="grid gap-4 sm:grid-cols-2">
                @csrf
                <x-form.input id="pattern-name" name="name" label="Name" required />
                <x-form.select id="pattern-arrangement" name="arrangement_uuid" label="Work arrangement" :options="$arrangementOptions" />
                <x-form.select id="pattern-department" name="department_uuid" label="Department" :options="['' => 'All departments'] + $departmentOptions" />
                <x-form.select id="pattern-location" name="location_uuid" label="Expected location" :options="['' => 'Primary location'] + $locationOptions" />
                <x-form.input id="pattern-starts" type="time" name="starts_at" label="Starts" required />
                <x-form.input id="pattern-ends" type="time" name="ends_at" label="Ends" required />
                <x-form.input id="pattern-from" type="date" name="effective_from" label="Effective from" required />
                <x-form.input id="pattern-until" type="date" name="effective_until" label="Effective until" />
                <fieldset class="sm:col-span-2">
                    <legend class="mb-2 text-sm font-medium text-fg">Weekdays</legend>
                    <div class="flex flex-wrap gap-3 text-sm">
                        @foreach ([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'] as $number => $label)
                            <label class="inline-flex items-center gap-2"><input type="checkbox" name="weekdays[]" value="{{ $number }}" @checked($number < 6)> {{ $label }}</label>
                        @endforeach
                    </div>
                </fieldset>
                <div><x-button type="submit" size="sm" variant="secondary">Save pattern</x-button></div>
            </form>
            @foreach ($patterns as $pattern)
                <form method="POST" action="{{ route('org.schedules.patterns.apply', $pattern) }}" class="mt-4 flex flex-wrap items-end gap-2 border-t border-edge pt-4">
                    @csrf
                    <p class="w-full text-sm font-medium text-fg">{{ $pattern->name }} <x-badge>{{ $pattern->status->label() }}</x-badge> @if($pattern->arrangement)<span class="text-fg-muted">{{ $pattern->arrangement->name }}</span>@endif</p>
                    <x-form.input :id="'pattern-'.$pattern->id.'-from'" type="date" name="from" label="From" required />
                    <x-form.input :id="'pattern-'.$pattern->id.'-until'" type="date" name="until" label="Until" required />
                    <x-form.select :id="'pattern-'.$pattern->id.'-department'" name="department_uuid" label="Limit to department" :options="['' => 'Pattern default'] + $departmentOptions" />
                    <x-button type="submit" size="sm">Apply</x-button>
                </form>
            @endforeach
            @if ($patterns->hasPages())
                <div class="mt-4 border-t border-edge pt-3">{{ $patterns->links() }}</div>
            @endif
        </x-card>

        <x-card title="Non-working days" description="Holidays, closures, and term breaks. Pattern apply skips them. Working-day leave skips them when the leave policy says so.">
            @if (\App\Analytics\AnalyticsScope::restrictingDepartmentIds(auth()->user()) === null)
            <form method="POST" action="{{ route('org.schedules.calendar.store') }}" class="grid gap-4 sm:grid-cols-4">
                @csrf
                <x-form.input id="calendar-date" type="date" name="observed_on" label="Date" required />
                <x-form.input id="calendar-name" name="name" label="Name" required />
                <x-form.select id="calendar-kind" name="kind" label="Kind" :options="['holiday' => 'Holiday', 'closure' => 'Closure', 'non_working' => 'Non-working day']" />
                <div class="flex items-end"><x-button type="submit" size="sm" variant="secondary">Save day</x-button></div>
            </form>
            @else
                <p class="text-sm text-fg-muted">This calendar belongs to the whole organisation. A department head can read it and cannot add a day.</p>
            @endif
            @if ($calendar->isNotEmpty())
                <ul class="mt-4 divide-y divide-edge text-sm">
                    @foreach ($calendar as $day)
                        <li class="flex items-center justify-between py-2"><span>{{ $day->observed_on->toFormattedDateString() }} · {{ $day->name }}</span><x-badge>{{ $day->kind->label() }}</x-badge></li>
                    @endforeach
                </ul>
            @endif
            @if ($calendar->hasPages())
                <div class="mt-4">{{ $calendar->links() }}</div>
            @endif
        </x-card>

        <form method="GET" action="{{ route('org.schedules.index') }}" class="flex flex-wrap items-end gap-2">
            <x-form.input id="schedule-find" name="find" label="Find a person" :value="$find" hint="Two or more letters. The same matches are used for every shift below." />
            <x-button type="submit" size="sm" variant="ghost">Find</x-button>
        </form>

        @foreach ($shifts as $shift)
            <x-card :title="$shift->name" :description="\Illuminate\Support\Str::of($shift->starts_at)->substr(0, 5).' – '.\Illuminate\Support\Str::of($shift->ends_at)->substr(0, 5).($shift->crosses_midnight ? ' · crosses midnight' : '').($shift->arrangement ? ' · '.$shift->arrangement->name : '')">
                <x-slot:actions><x-badge>{{ $shift->status->label() }}</x-badge></x-slot:actions>
                <div class="flex flex-wrap gap-3">
                    @if ($shift->status->value === 'draft')
                        <form method="POST" action="{{ route('org.schedules.publish', $shift) }}">@csrf<x-button type="submit" size="sm">Publish</x-button></form>
                    @endif
                    <form method="POST" action="{{ route('org.schedules.assign', $shift) }}" class="flex flex-wrap items-end gap-2">
                        @csrf
                        <x-form.select :id="'assign-'.$shift->id.'-person'" name="staff_uuid" label="Person" :options="$people->mapWithKeys(fn ($person) => [$person->uuid => $person->first_name.' '.$person->last_name])->all()" />
                        <x-form.input :id="'assign-'.$shift->id.'-date'" type="date" name="work_date" label="Date" />
                        <x-form.select :id="'assign-'.$shift->id.'-arrangement'" name="arrangement_uuid" label="Arrangement" :options="['' => 'Shift default'] + $arrangementOptions" />
                        <x-form.select :id="'assign-'.$shift->id.'-location'" name="location_uuid" label="Location" :options="['' => 'Shift default'] + $locationOptions" />
                        <x-button type="submit" size="sm" variant="secondary">Assign</x-button>
                    </form>
                </div>
            </x-card>
        @endforeach
        @if ($shifts->hasPages())
            <div>{{ $shifts->links() }}</div>
        @endif
    @endif

    <section aria-labelledby="schedule-assignments-heading">
        <h2 id="schedule-assignments-heading" class="text-base font-semibold text-fg">Upcoming assignments</h2>
        <ul class="mt-3 divide-y divide-edge">
            @forelse ($assignments as $assignment)
                <li class="flex min-h-11 flex-wrap items-center justify-between gap-3 py-3 text-sm">
                    <span>
                        <span class="font-medium text-fg">{{ $assignment->shift->name }}</span>
                        · {{ $assignment->work_date->toFormattedDateString() }}
                        · {{ $assignment->staffMember?->name }}
                        @if ($assignment->arrangement)
                            · {{ $assignment->arrangement->name }}
                        @endif
                        @if ($assignment->location)
                            · {{ $assignment->location->name }}
                        @endif
                    </span>
                    <span class="flex items-center gap-3 text-fg-muted">
                        <span>{{ \Illuminate\Support\Str::of($assignment->starts_at ?? $assignment->shift->starts_at)->substr(0, 5) }}–{{ \Illuminate\Support\Str::of($assignment->ends_at ?? $assignment->shift->ends_at)->substr(0, 5) }}</span>
                        @if ($canManage)
                            <form method="POST" action="{{ route('org.schedules.assignments.destroy', $assignment) }}" onsubmit="return confirm('Remove this assignment? A past date, or a period that already has a check-in, stays on record.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm text-danger underline-offset-2 hover:underline">Remove</button>
                            </form>
                        @endif
                    </span>
                </li>
            @empty
                <li class="py-3 text-sm text-fg-muted">Nothing scheduled.</li>
            @endforelse
        </ul>
        @if ($assignments->hasPages())
            <div class="flex flex-col gap-3 border-t border-edge py-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-fg-subtle">Showing {{ $assignments->firstItem() }}–{{ $assignments->lastItem() }} of {{ $assignments->total() }}</p>
                <div>{{ $assignments->links() }}</div>
            </div>
        @endif
    </section>
</x-layouts.app>
