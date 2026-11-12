<x-layouts.app title="Attendance records" wide>
    <x-page-header title="Attendance records" description="Stored check-ins for the selected date. The absent tile is derived: active people in scope with no covering record, unless a location filter is in use." eyebrow="Attendance">
        <x-button :href="route('org.attendance.mine')" variant="secondary" size="sm">My attendance</x-button>
    </x-page-header>

    <section aria-labelledby="attendance-summary-heading">
        <div class="mb-3 flex items-baseline justify-between gap-4">
            <h2 id="attendance-summary-heading" class="section-title">{{ \Illuminate\Support\Carbon::parse($date)->format('l j F Y') }}</h2>
            <p class="section-meta">Present, late, and on leave are stored statuses</p>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
            <x-stat label="Present" :value="number_format($summary['present'])" hint="Stored status" icon="check-circle" tone="success" />
            <x-stat label="Late" :value="number_format($summary['late'])" hint="Stored status" icon="clock" tone="warning" :emphasis="($summary['late'] ?? 0) > 0 ? 'warning' : null" />
            <x-stat label="On leave" :value="number_format($summary['on_leave'])" hint="Stored status" icon="calendar" tone="accent" />
            <x-stat label="{{ ($summary['absent_basis'] ?? 'derived') === 'derived' ? 'Uncovered' : 'Absent' }}" :value="number_format($summary['absent'])" :hint="($summary['absent_basis'] ?? 'derived') === 'derived' ? 'Active people with no covering record' : 'Stored absent status'" icon="alert" tone="danger" :emphasis="($summary['absent'] ?? 0) > 0 ? 'danger' : null" />
        </div>
    </section>

    <form method="GET" role="search" class="toolbar flex flex-wrap items-end gap-3 [&>div:not(:last-child)]:min-w-[10rem] [&>div:not(:last-child)]:flex-1">
        <x-form.input type="date" name="date" label="Date" :value="$date" />
        <x-form.select name="status" label="Status" :options="collect(\App\Enums\AttendanceStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])->prepend('Any status', '')->all()" :value="request('status')" />
        <x-form.select name="department" label="Department" :options="$departments->mapWithKeys(fn ($department) => [$department->uuid => $department->name])->prepend('Any department', '')->all()" :value="request('department')" />
        <div><x-button type="submit" variant="secondary" size="sm"><x-icon name="filter" class="size-4" /> Filter</x-button></div>
    </form>

    @if ($records->isEmpty())
        <x-empty-state title="No attendance records for this date" description="Check-ins, corrections, and approved leave appear here." />
    @else
        <x-data-table :paginator="$records" caption="Attendance records">
            <thead>
                <tr>
                    <th scope="col">Person</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="hidden md:table-cell">Check in</th>
                    <th scope="col" class="hidden lg:table-cell">Check out</th>
                    <th scope="col" class="hidden lg:table-cell">Place</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($records as $record)
                    <tr>
                        <td>
                            <span class="font-medium text-fg">{{ $record->staffMember?->name }}</span>
                            <span class="block text-xs text-fg-muted">{{ $record->staffMember?->staff_number }}@if ($record->session) · {{ $record->session->name }}@endif</span>
                        </td>
                        <td><x-badge :tone="$record->status->tone()">{{ $record->status->label() }}</x-badge></td>
                        <td class="hidden md:table-cell">{{ $record->check_in_at?->timezone($timezone)->format('H:i') ?? '—' }}@if($record->late_minutes) <span class="text-xs text-fg-muted">+{{ $record->late_minutes }}m</span>@endif</td>
                        <td class="hidden lg:table-cell">{{ $record->check_out_at?->timezone($timezone)->format('H:i') ?? '—' }}</td>
                        <td class="hidden lg:table-cell">{{ $record->location?->name ?? $record->department?->name ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </x-data-table>
    @endif

    @if ($canManage)
        <x-card title="Authorised correction" description="Manual attendance is recorded as a correction, with the actor in the audit trail.">
            <form method="POST" action="{{ route('org.attendance.corrections') }}" class="grid gap-4 sm:grid-cols-2">
                @csrf
                <x-form.select name="staff_uuid" label="Person" :options="$staff->mapWithKeys(fn ($person) => [$person->uuid => $person->name.' · '.$person->staff_number])->all()" required />
                <x-form.input type="date" name="work_date" label="Date" :value="$date" required />
                <x-form.select name="status" label="Status" :options="['present' => 'Present', 'late' => 'Late', 'absent' => 'Absent', 'excused' => 'Excused', 'on_leave' => 'On leave', 'incomplete' => 'Incomplete']" required />
                <x-form.select name="location_uuid" label="Location" :options="$locations->mapWithKeys(fn ($location) => [$location->uuid => $location->name])->prepend('Primary location', '')->all()" />
                <x-form.input type="datetime-local" name="check_in_at" label="Check in" />
                <x-form.input type="datetime-local" name="check_out_at" label="Check out" />
                <div class="sm:col-span-2"><x-form.input name="note" label="Note" maxlength="500" /></div>
                <div><x-button type="submit" size="sm">Save correction</x-button></div>
            </form>
        </x-card>
    @endif
</x-layouts.app>
