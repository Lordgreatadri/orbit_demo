@php
    $cacheSeconds = (int) config('orbit.analytics_cache_seconds');
    $needs = [];
    $decisions = [];

    if ($attendanceToday && $attendanceToday['absent'] > 0) {
        $count = (int) $attendanceToday['absent'];
        $people = $count === 1 ? 'person has' : 'people have';
        $needs[] = [
            'statement' => $attendanceToday['absent_basis'] === 'derived'
                ? number_format($count).' active '.$people.' no covering record today.'
                : number_format($count).' '.$people.' a stored absent record at this location today.',
            'period' => 'Today',
            'href' => route('org.attendance.index'),
            'action' => 'Open the attendance register',
        ];
    }

    if ($schedule && $schedule['unassigned_shifts'] > 0) {
        $count = (int) $schedule['unassigned_shifts'];
        $decisions[] = [
            'statement' => number_format($count).' published '.($count === 1 ? 'shift has' : 'shifts have').' no assignment.',
            'period' => 'Next 7 days',
            'href' => route('org.schedules.index'),
            'action' => 'Open schedules',
        ];
    }

    if ($user->can('leave.approve') && (int) ($leaveSummary['pending'] ?? 0) > 0) {
        $count = (int) $leaveSummary['pending'];
        $decisions[] = [
            'statement' => number_format($count).' leave '.($count === 1 ? 'request is' : 'requests are').' waiting for a decision.',
            'period' => 'Open requests',
            'href' => route('org.leave.index'),
            'action' => 'Review leave',
        ];
    }

    $recordPhrase = function (int $count, string $one, string $many): string {
        if ($count === 0) {
            return 'No '.$many;
        }

        return $count === 1 ? 'One '.$one : number_format($count).' '.$many;
    };
@endphp

<section aria-labelledby="attention-heading" class="inset">
    <h2 id="attention-heading" class="sr-only">Attention</h2>
    @if ($needs === [] && $decisions === [])
        <p class="text-sm text-fg-muted">Nothing in this scope needs a decision right now.</p>
    @else
        <div class="grid gap-6 md:grid-cols-2">
            <div>
                <h3 class="text-base font-semibold text-fg">Needs attention</h3>
                @if ($needs === [])
                    <p class="mt-2 text-sm text-fg-muted">Nothing immediate in this scope.</p>
                @else
                    <ul class="mt-3 space-y-4">
                        @foreach ($needs as $item)
                            <li>
                                <p class="text-[1.0625rem] font-medium text-fg">{{ $item['statement'] }}</p>
                                <p class="mt-1 text-xs text-fg-subtle">{{ $item['period'] }}</p>
                                <a href="{{ $item['href'] }}" class="mt-1 inline-flex min-h-11 items-center text-sm font-medium text-primary hover:underline">{{ $item['action'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div>
                <h3 class="text-base font-semibold text-fg">Upcoming decisions</h3>
                @if ($decisions === [])
                    <p class="mt-2 text-sm text-fg-muted">No decisions are waiting in this scope.</p>
                @else
                    <ul class="mt-3 space-y-4">
                        @foreach ($decisions as $item)
                            <li>
                                <p class="text-[1.0625rem] font-medium text-fg">{{ $item['statement'] }}</p>
                                <p class="mt-1 text-xs text-fg-subtle">{{ $item['period'] }}</p>
                                <a href="{{ $item['href'] }}" class="mt-1 inline-flex min-h-11 items-center text-sm font-medium text-primary hover:underline">{{ $item['action'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    @endif
</section>

<section aria-labelledby="position-heading">
    <div class="mb-3 flex items-baseline justify-between gap-4">
        <h2 id="position-heading" class="text-base font-semibold text-fg">Position</h2>
        <p class="text-xs text-fg-subtle">Today</p>
    </div>
    <div class="inset">
        <div class="grid grid-cols-2 gap-x-6 gap-y-5 lg:grid-cols-5">
            <x-stat label="Active staff" :value="number_format($counts['staff_active'])" :hint="$counts['staff_total'].' on the register'" :href="$user->can('staff.view') ? route('org.staff.index', ['status' => 'active']) : null" />
            <x-stat label="On leave" :value="number_format($counts['staff_on_leave'])" hint="Register status, not today's attendance" :href="$user->can('staff.view') ? route('org.staff.index', ['status' => 'on_leave']) : null" />
            @if ($attendanceToday)
                <x-stat label="Present today" :value="number_format($attendanceToday['present'])" hint="Stored status" :href="route('org.attendance.index')" />
                <x-stat label="Late today" :value="number_format($attendanceToday['late'])" hint="Stored status" :href="route('org.attendance.index')" />
                <div @class(['col-span-2 lg:col-span-1' => $attendanceToday['absent'] > 0])>
                    <x-stat label="Uncovered today" :value="number_format($attendanceToday['absent'])" :hint="$attendanceToday['absent_basis'] === 'derived' ? 'No covering record' : 'Stored absent status'" :href="route('org.attendance.index')" :emphasis="$attendanceToday['absent'] > 0 ? 'danger' : null" />
                </div>
            @endif
        </div>
    </div>
    @if ($cacheSeconds > 0)
        <p class="mt-2 text-xs text-fg-subtle">These position figures can be up to {{ $cacheSeconds }} seconds old.</p>
    @endif
</section>

@if ($population && ($population['students'] + $population['teaching']) > 0)
    <p class="text-sm text-fg-muted">
        <span class="font-medium text-fg">{{ number_format($population['students']) }} Students</span>
        <span class="text-fg-subtle"> · Register title or type</span>
        <span class="mx-2 text-edge" aria-hidden="true">/</span>
        <span class="font-medium text-fg">{{ number_format($population['teaching']) }} Lecturers</span>
        <span class="text-fg-subtle"> · Register job title</span>
        <span class="mx-2 text-edge" aria-hidden="true">/</span>
        <span class="font-medium text-fg">{{ number_format($population['other']) }} other active people</span>
        <span class="text-fg-subtle"> · Not a class attendance roll</span>
    </p>
@endif

@if ($trend !== [])
    @php
        $rangePresent = (int) collect($trend)->sum('present');
        $rangeLate = (int) collect($trend)->sum('late');
        $hasTrend = collect($trend)->sum(fn ($row) => $row['present'] + $row['late'] + $row['on_leave'] + $row['other']) > 0;
        $fromLabel = \Illuminate\Support\Carbon::parse($trendFrom)->format('j M');
        $toLabel = \Illuminate\Support\Carbon::parse($trendTo)->format('j M Y');

        if (! $hasTrend) {
            $conclusion = 'No attendance records in the last 14 days.';
        } elseif ($rangeLate > 0 && $rangePresent === 0) {
            $conclusion = $recordPhrase($rangeLate, 'late record', 'late records').' in the last 14 days. No present records.';
        } elseif ($rangePresent > 0 && $rangeLate === 0) {
            $conclusion = $recordPhrase($rangePresent, 'present record', 'present records').' in the last 14 days. No late records.';
        } elseif ($rangePresent === 0 && $rangeLate === 0) {
            $conclusion = 'No present or late records in the last 14 days.';
        } else {
            $conclusion = $recordPhrase($rangePresent, 'present record', 'present records').' and '.lcfirst($recordPhrase($rangeLate, 'late record', 'late records')).' in the last 14 days.';
        }
    @endphp
    <section aria-labelledby="trend-heading">
        <h2 id="trend-heading" class="text-[1.125rem] font-semibold text-fg">{{ $conclusion }}</h2>
        <p class="mt-1 max-w-3xl text-sm text-fg-muted">Last 14 days, {{ $fromLabel }} to {{ $toLabel }}. Each column is stored attendance records. Two periods on one day count twice. A flat mark is a day with no record.</p>
        @if ($hasTrend)
            <div class="mt-4">
                <x-card title="Daily records" description="Dates on the axis are day/month.">
                    <x-slot:actions>
                        @can('analytics.view')
                            <x-button :href="route('org.analytics.index')" variant="ghost" size="sm">Analytics</x-button>
                        @endcan
                    </x-slot:actions>
                    <x-charts.stacked :rows="$trend" :series="$trendSeries" caption="Daily attendance records from {{ $trendFrom }} to {{ $trendTo }}" />
                </x-card>
            </div>
        @endif
    </section>
@endif

<div class="divide-y divide-edge">
    @if ($workTypes)
        @php
            $workTotal = max(1, (int) collect($workTypes)->sum('value'));
            $workInks = ['var(--primary)', 'var(--accent)', 'var(--brand-teal)', 'var(--fg-subtle)'];
        @endphp
        <section class="py-8 first:pt-0" aria-labelledby="composition-heading">
            <h2 id="composition-heading" class="text-base font-semibold text-fg">Workforce composition</h2>
            <p class="mt-1 text-sm text-fg-muted">Active people in this scope, by work type.</p>
            <div class="mt-4 flex h-2 overflow-hidden rounded-full bg-surface-muted" role="img" aria-label="Active people by work type">
                @foreach ($workTypes as $row)
                    @if ($row['value'] > 0)
                        <div style="width: {{ round($row['value'] / $workTotal * 100, 2) }}%; background: {{ $workInks[$loop->index] ?? 'var(--fg-subtle)' }}"></div>
                    @endif
                @endforeach
            </div>
            <ul class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm">
                @foreach ($workTypes as $row)
                    <li class="inline-flex items-center gap-2">
                        <span class="size-2.5 rounded-sm" style="background: {{ $workInks[$loop->index] ?? 'var(--fg-subtle)' }}" aria-hidden="true"></span>
                        <span class="font-medium text-fg">{{ $row['label'] }}</span>
                        <span class="tabular-nums text-fg-muted">{{ number_format($row['value']) }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($schedule)
        <section class="py-8" aria-labelledby="coverage-heading">
            <div class="flex flex-wrap items-baseline justify-between gap-3">
                <h2 id="coverage-heading" class="text-base font-semibold text-fg">Schedule coverage</h2>
                <a href="{{ route('org.schedules.index') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-primary hover:underline">Open schedules</a>
            </div>
            <p class="mt-2 max-w-3xl text-sm text-fg-muted">
                @if ($schedule['rate'] === null)
                    No published periods in the last 14 days. Draft shifts are excluded.
                @else
                    {{ $schedule['rate'] }}% of published periods were attended in the last 14 days — {{ number_format($schedule['attended']) }} of {{ number_format($schedule['scheduled']) }}. Draft shifts are excluded.
                @endif
                @if ($schedule['unassigned_shifts'] === 0)
                    Published shifts in the next 7 days have an assignment.
                @endif
            </p>
        </section>
    @endif

    @if ($user->can('schedules.view'))
        <section class="py-8" aria-labelledby="assignments-heading">
            <h2 id="assignments-heading" class="text-base font-semibold text-fg">Upcoming assignments</h2>
            <p class="mt-1 text-sm text-fg-muted">From today. Published assignments only.</p>
            <ul class="mt-3 divide-y divide-edge">
                @forelse ($upcomingAssignments->take(3) as $assignment)
                    @php
                        $starts = \Illuminate\Support\Str::of((string) $assignment->shift?->starts_at)->substr(0, 5);
                        $ends = \Illuminate\Support\Str::of((string) $assignment->shift?->ends_at)->substr(0, 5);
                        $isToday = $assignment->work_date->toDateString() === now($organization->timezone)->toDateString();
                    @endphp
                    <li class="flex min-h-11 flex-wrap items-baseline justify-between gap-2 py-3 text-sm">
                        <p class="font-medium text-fg">{{ $assignment->staffMember?->name ?? 'Unassigned' }}</p>
                        <p class="text-fg-muted">{{ $assignment->shift?->name ?? 'Shift' }} · {{ $isToday ? 'Today' : $assignment->work_date->format('D j M') }} · {{ $starts }}–{{ $ends }}</p>
                    </li>
                @empty
                    <li class="py-3 text-sm text-fg-muted">Nothing is scheduled from today onward.</li>
                @endforelse
            </ul>
        </section>
    @endif

    @if ($user->can('leave.approve'))
        <section class="py-8" aria-labelledby="leave-heading">
            <div class="flex flex-wrap items-baseline justify-between gap-3">
                <h2 id="leave-heading" class="text-base font-semibold text-fg">Leave waiting</h2>
                <a href="{{ route('org.leave.index') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-primary hover:underline">Review leave</a>
            </div>
            <p class="mt-1 text-sm text-fg-muted">
                Requests in this scope.
                @if (($leaveSummary['utilisation'] ?? null) !== null)
                    Open-year utilisation is {{ $leaveSummary['utilisation'] }}% of entitled days.
                @endif
            </p>
            <ul class="mt-3 divide-y divide-edge">
                @forelse ($pendingLeave->take(3) as $leaveRequest)
                    <li class="flex min-h-11 flex-wrap items-center justify-between gap-2 py-3 text-sm">
                        <div class="min-w-0">
                            <p class="font-medium text-fg">{{ $leaveRequest->staffMember?->name ?? 'Staff member' }}</p>
                            <p class="text-xs text-fg-muted">{{ $leaveRequest->leaveType?->name ?? 'Leave' }} · {{ $leaveRequest->starts_on->format('j M') }} – {{ $leaveRequest->ends_on->format('j M') }}</p>
                        </div>
                        <x-badge :tone="$leaveRequest->status->tone()">{{ $leaveRequest->status->label() }}</x-badge>
                    </li>
                @empty
                    <li class="py-3 text-sm text-fg-muted">No requests are waiting.</li>
                @endforelse
            </ul>
        </section>
    @endif

    <section class="py-8" aria-labelledby="notices-heading">
        @include('tenant.dashboard.notices')
    </section>
</div>

@if ($counts['show_structure'] || $counts['show_accounts'] || $signInsToday !== null || $user->can('audit.view'))
    <section aria-labelledby="admin-heading" class="border-t border-edge pt-6">
        <h2 id="admin-heading" class="text-xs font-medium text-fg-subtle">Administration</h2>
        <nav class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-fg-muted" aria-label="Administration">
            @if ($counts['show_structure'])
                <a href="{{ route('org.departments.index') }}" class="inline-flex min-h-11 items-center hover:text-primary hover:underline">{{ number_format($counts['departments']) }} departments</a>
                <a href="{{ route('org.locations.index') }}" class="inline-flex min-h-11 items-center hover:text-primary hover:underline">{{ number_format($counts['locations']) }} locations</a>
            @endif
            @if ($counts['show_accounts'])
                <a href="{{ route('org.users.index') }}" class="inline-flex min-h-11 items-center hover:text-primary hover:underline">{{ number_format($counts['users']) }} accounts</a>
                <a href="{{ route('org.users.index') }}#invitations" class="inline-flex min-h-11 items-center hover:text-primary hover:underline">{{ number_format($counts['pending_invitations']) }} invitations</a>
            @endif
            @if ($signInsToday !== null)
                <a href="{{ route('org.audit.logins') }}" class="inline-flex min-h-11 items-center hover:text-primary hover:underline">{{ number_format($signInsToday) }} sign-ins today</a>
            @endif
            @if ($user->can('audit.view'))
                <a href="{{ route('org.audit.index') }}" class="inline-flex min-h-11 items-center hover:text-primary hover:underline">Audit trail</a>
            @endif
        </nav>
        @if ($counts['unlinked_staff'] > 0 && $user->can('staff.view'))
            <p class="mt-1 text-sm text-fg-muted">{{ number_format($counts['unlinked_staff']) }} active {{ $counts['unlinked_staff'] === 1 ? 'person has' : 'people have' }} no user account.</p>
        @endif
    </section>
@endif
