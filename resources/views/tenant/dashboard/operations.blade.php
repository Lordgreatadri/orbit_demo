@php
    $cacheSeconds = (int) config('orbit.analytics_cache_seconds');
    $timezone = $organization->timezone ?: 'UTC';
    $now = now($timezone);
    $items = [];

    if ($attendanceToday && $attendanceToday['absent'] > 0) {
        $count = (int) $attendanceToday['absent'];
        $people = $count === 1 ? 'person has' : 'people have';
        $items[] = [
            'group' => 'Needs attention',
            'tone' => 'danger',
            'icon' => 'alert',
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
        $items[] = [
            'group' => 'Upcoming decision',
            'tone' => 'warning',
            'icon' => 'calendar',
            'statement' => number_format($count).' published '.($count === 1 ? 'shift has' : 'shifts have').' no assignment.',
            'period' => 'Next 7 days',
            'href' => route('org.schedules.index'),
            'action' => 'Open schedules',
        ];
    }

    $canDecideLeave = $user->can('leave.approve');
    $pendingCount = (int) ($leaveSummary['pending'] ?? 0);

    if ($canDecideLeave && $pendingCount > 0) {
        $items[] = [
            'group' => 'Upcoming decision',
            'tone' => 'warning',
            'icon' => 'document-check',
            'statement' => number_format($pendingCount).' leave '.($pendingCount === 1 ? 'request is' : 'requests are').' waiting for a decision.',
            'period' => 'Open requests',
            'href' => route('org.leave.index'),
            'action' => 'Review leave',
        ];
    }

    $active = (int) ($attendanceToday['active_staff'] ?? $counts['staff_active']);
    $uncovered = (int) ($attendanceToday['absent'] ?? 0);
    $covered = $attendanceToday ? max(0, $active - $uncovered) : null;
    $coverageShare = $attendanceToday && $active > 0 && $attendanceToday['absent_basis'] === 'derived'
        ? (int) round($covered / $active * 100)
        : null;
    $decisionCount = collect($items)->where('group', 'Upcoming decision')->count();

    if ($attendanceToday && $uncovered > 0 && $attendanceToday['absent_basis'] === 'derived') {
        $headline = number_format($uncovered).' of '.number_format($active).' active '.($active === 1 ? 'person is' : 'people are').' not covered by a record today';
        $headline .= $decisionCount > 0 ? ', and '.$decisionCount.' '.($decisionCount === 1 ? 'decision is' : 'decisions are').' waiting.' : '.';
    } elseif ($items !== []) {
        $headline = count($items).' '.(count($items) === 1 ? 'item needs' : 'items need').' a decision in this scope.';
    } elseif ($attendanceToday) {
        $headline = 'Everyone active is covered today, and nothing is waiting for a decision.';
    } else {
        $headline = 'Nothing in this scope needs a decision right now.';
    }

    $recordPhrase = function (int $count, string $one, string $many): string {
        if ($count === 0) {
            return 'No '.$many;
        }

        return $count === 1 ? 'One '.$one : number_format($count).' '.$many;
    };

    $share = fn (int $part, int $whole): ?int => $whole > 0 ? (int) round($part / $whole * 100) : null;
    $presentShare = $attendanceToday ? $share((int) $attendanceToday['present'], $active) : null;
@endphp

{{-- Level 1 · Operational briefing --}}
<section aria-labelledby="briefing-heading" class="briefing overflow-hidden">
    <div class="grid lg:grid-cols-12">
        <div class="flex flex-col justify-between gap-5 p-5 sm:p-6 lg:col-span-5 lg:border-r lg:border-edge">
            <div>
                <p class="inline-flex items-center gap-2 text-xs font-semibold text-primary">
                    <span class="grid size-6 place-items-center rounded-md bg-primary text-primary-fg" aria-hidden="true"><x-icon name="sparkles" class="size-3.5" /></span>
                    Operational briefing
                </p>
                <h2 id="briefing-heading" class="mt-3 text-[1.1875rem] leading-snug font-semibold tracking-tight text-balance text-fg sm:text-[1.3125rem]">{{ $headline }}</h2>
                <p class="mt-2 text-xs text-fg-subtle">Calculated from stored records at {{ $now->format('H:i') }}. No figure on this page is estimated.</p>
            </div>
            @if ($coverageShare !== null)
                <div>
                    <div class="flex items-baseline justify-between gap-3 text-xs">
                        <span class="font-medium text-fg-muted">Covered today</span>
                        <span class="tabular-nums text-fg-muted"><span class="font-semibold text-fg">{{ number_format($covered) }}</span> of {{ number_format($active) }} · {{ $coverageShare }}%</span>
                    </div>
                    <div class="mt-2 flex h-2 overflow-hidden rounded-full bg-danger-soft" role="presentation">
                        <span class="h-full rounded-full bg-primary" style="width: {{ $coverageShare }}%"></span>
                    </div>
                    <p class="mt-1.5 text-[0.6875rem] text-fg-subtle">Covering means present, late, on leave, incomplete, or excused.</p>
                </div>
            @endif
        </div>

        <div class="border-t border-edge p-5 sm:p-6 lg:col-span-7 lg:border-t-0">
            @if ($items === [])
                <div class="flex h-full items-center gap-3">
                    <span class="icon-well tone-success" aria-hidden="true"><x-icon name="check-circle" class="size-4.5" /></span>
                    <div>
                        <p class="font-medium text-fg">Nothing in this scope needs a decision right now.</p>
                        <p class="mt-0.5 text-sm text-fg-muted">New uncovered people, unassigned shifts, and leave requests will appear here.</p>
                    </div>
                </div>
            @else
                <h3 class="text-sm font-semibold text-fg">Needs attention and upcoming decisions</h3>
                <ul class="mt-3 divide-y divide-edge">
                    @foreach ($items as $item)
                        <li class="flex flex-col gap-3 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center">
                            <div class="rail rail-{{ $item['tone'] }} flex min-w-0 flex-1 items-start gap-3">
                                <span class="icon-well tone-{{ $item['tone'] }}" aria-hidden="true"><x-icon :name="$item['icon']" class="size-4" /></span>
                                <div class="min-w-0">
                                    <p class="text-[0.9375rem] leading-snug font-medium text-fg">{{ $item['statement'] }}</p>
                                    <p class="mt-0.5 text-xs text-fg-subtle"><span class="font-medium {{ $item['tone'] === 'danger' ? 'text-danger' : 'text-warning' }}">{{ $item['group'] }}</span> · {{ $item['period'] }}</p>
                                </div>
                            </div>
                            <a href="{{ $item['href'] }}" class="inline-flex min-h-11 shrink-0 items-center gap-1.5 self-start rounded-lg border border-edge bg-surface px-3 text-sm font-medium text-fg transition-colors hover:border-edge-strong hover:bg-surface-muted focus-visible:outline-2 sm:self-center">{{ $item['action'] }} <x-icon name="arrow-right" class="size-3.5 text-primary" /></a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</section>

{{-- Level 2 · Current position --}}
<section aria-labelledby="position-heading">
    <div class="mb-3 flex items-baseline justify-between gap-4">
        <h2 id="position-heading" class="section-title">Today's position</h2>
        <p class="section-meta">{{ $now->format('D j M') }} · {{ $scopeLabel }}</p>
    </div>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-5">
        <x-stat :label="$organization->isEducational() ? 'Active people' : 'Active staff'" :value="number_format($counts['staff_active'])" :hint="$counts['staff_total'].' on the register'.($organization->isEducational() ? ', staff and students' : '')" icon="users" tone="primary" :href="$user->can('staff.view') ? route('org.staff.index', ['status' => 'active']) : null" />
        @if ($attendanceToday)
            <x-stat label="Present today" :value="number_format($attendanceToday['present'])" :hint="'Stored status'.($presentShare !== null ? ' · '.$presentShare.'% of active' : '')" icon="check-circle" tone="success" :meter="$presentShare" :href="route('org.attendance.index')" />
            <x-stat label="Late today" :value="number_format($attendanceToday['late'])" hint="Stored status · after the cutoff" icon="clock" tone="warning" :emphasis="$attendanceToday['late'] > 0 ? 'warning' : null" :href="route('org.attendance.index')" />
        @endif
        <x-stat label="On leave" :value="number_format($counts['staff_on_leave'])" hint="Register status, not today's attendance" icon="calendar" tone="neutral" :href="$user->can('staff.view') ? route('org.staff.index', ['status' => 'on_leave']) : null" />
        @if ($attendanceToday)
            <div class="col-span-2 sm:col-span-2 lg:col-span-1">
                <x-stat class="h-full" label="Uncovered today" :value="number_format($attendanceToday['absent'])" :hint="$attendanceToday['absent_basis'] === 'derived' ? 'No covering record yet' : 'Stored absent status'" icon="alert" tone="danger" :meter="$share($uncovered, $active)" :href="route('org.attendance.index')" :emphasis="$attendanceToday['absent'] > 0 ? 'danger' : null" />
            </div>
        @endif
    </div>
    @if ($cacheSeconds > 0)
        <p class="mt-2 text-xs text-fg-subtle">These position figures can be up to {{ $cacheSeconds }} seconds old.</p>
    @endif
</section>

@if ($population && ($population['students'] + $population['teaching']) > 0)
    <section aria-label="Register population" class="card grid gap-4 p-4 sm:grid-cols-3 sm:p-5">
        <div class="flex items-center gap-3">
            <span class="icon-well tone-primary" aria-hidden="true"><x-icon name="academic-cap" class="size-4" /></span>
            <div><p class="text-lg leading-none font-semibold tabular-nums text-fg">{{ number_format($population['students']) }} <span class="text-sm font-medium">Students</span></p><p class="mt-1 text-xs text-fg-subtle">Register title or type</p></div>
        </div>
        <div class="flex items-center gap-3">
            <span class="icon-well tone-accent" aria-hidden="true"><x-icon name="book" class="size-4" /></span>
            <div><p class="text-lg leading-none font-semibold tabular-nums text-fg">{{ number_format($population['teaching']) }} <span class="text-sm font-medium">Lecturers</span></p><p class="mt-1 text-xs text-fg-subtle">Register job title</p></div>
        </div>
        <div class="flex items-center gap-3">
            <span class="icon-well tone-neutral" aria-hidden="true"><x-icon name="users" class="size-4" /></span>
            <div><p class="text-lg leading-none font-semibold tabular-nums text-fg">{{ number_format($population['other']) }} <span class="text-sm font-medium">other active people</span></p><p class="mt-1 text-xs text-fg-subtle">Not a class attendance roll</p></div>
        </div>
    </section>
@endif

{{-- Level 3 · Trends and analysis --}}
@php
    $hasTrend = $trend !== [] && collect($trend)->sum(fn ($row) => $row['present'] + $row['late'] + $row['on_leave'] + $row['other']) > 0;
    $todayMix = $attendanceToday ? [
        ['key' => 'present', 'label' => 'Present', 'total' => (int) $attendanceToday['present'], 'ink' => \App\Analytics\ChartPalette::color('present')],
        ['key' => 'late', 'label' => 'Late', 'total' => (int) $attendanceToday['late'], 'ink' => \App\Analytics\ChartPalette::color('late')],
        ['key' => 'on_leave', 'label' => 'On leave', 'total' => (int) $attendanceToday['on_leave'], 'ink' => \App\Analytics\ChartPalette::color('on_leave')],
        ['key' => 'other', 'label' => 'Incomplete or excused', 'total' => (int) $attendanceToday['incomplete'] + (int) $attendanceToday['excused'], 'ink' => 'var(--fg-subtle)'],
        ['key' => 'absent', 'label' => $attendanceToday['absent_basis'] === 'derived' ? 'Uncovered' : 'Absent (stored)', 'total' => $uncovered, 'ink' => \App\Analytics\ChartPalette::color('absent')],
    ] : null;
@endphp

@if ($trend !== [] || $todayMix)
    <div class="grid gap-6 lg:grid-cols-3">
        @if ($trend !== [])
            @php
                $rangePresent = (int) collect($trend)->sum('present');
                $rangeLate = (int) collect($trend)->sum('late');
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
            <x-card :class="$todayMix ? 'lg:col-span-2' : 'lg:col-span-3'" icon="chart" :title="$conclusion" :description="'Last 14 days, '.$fromLabel.' – '.$toLabel.'. Stored records per day.'" decision="whether attendance is recovering or slipping, and on which days">
                <x-slot:actions>
                    @can('analytics.view')
                        <x-button :href="route('org.analytics.index')" variant="secondary" size="sm">Analytics <x-icon name="arrow-right" class="size-3.5" /></x-button>
                    @endcan
                </x-slot:actions>
                @if ($hasTrend)
                    <x-charts.stacked :rows="$trend" :series="$trendSeries" height="13rem" caption="Daily attendance records from {{ $trendFrom }} to {{ $trendTo }}" />
                    <p class="mt-3 text-[0.6875rem] text-fg-subtle">Each column counts attendance records, so two periods on one day count twice. A flat mark is a day with no record.</p>
                @else
                    <div class="flex items-center gap-3 rounded-lg bg-surface-muted px-4 py-6">
                        <x-icon name="chart" class="size-5 shrink-0 text-fg-subtle" />
                        <p class="text-sm text-fg-muted">Nothing is stored for these 14 days, so no plot is drawn. Check-ins and approved leave will appear here.</p>
                    </div>
                @endif
            </x-card>
        @endif

        @if ($todayMix)
            <x-card icon="users" title="Today's coverage" description="Each active person in this scope, by today's record." decision="who to follow up before the day closes" :class="$trend === [] ? 'lg:col-span-3' : ''">
                <x-charts.donut :segments="$todayMix" :center="number_format($active)" center-label="active people" size="9rem" caption="Active people today by covering status" empty="No active people are in this scope." />
                <a href="{{ route('org.attendance.index') }}" class="mt-4 inline-flex min-h-11 items-center gap-1.5 text-sm font-medium text-primary hover:underline">Open the attendance register <x-icon name="arrow-right" class="size-3.5" /></a>
            </x-card>
        @endif
    </div>
@endif

@php
    $showSchedule = $user->can('schedules.view');
    $showLeave = $canDecideLeave;
    $showComposition = (bool) $workTypes;
@endphp

@if ($showSchedule || $showLeave || $showComposition)
    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        @if ($showSchedule)
            <x-card icon="calendar" title="Schedule coverage" description="Published periods in the last 14 days. Drafts are excluded." decision="which shifts still need people">
                <x-slot:actions><a href="{{ route('org.schedules.index') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-primary hover:underline">Schedules</a></x-slot:actions>
                @if ($schedule)
                    <div class="flex items-center gap-4">
                        <x-charts.gauge :value="$schedule['rate']" label="attended" :tone="$schedule['rate'] !== null && $schedule['rate'] < 80 ? 'warning' : 'primary'" size="6.5rem" />
                        <div class="min-w-0 text-sm">
                            @if ($schedule['rate'] === null)
                                <p class="text-fg-muted">No published periods in the last 14 days.</p>
                            @else
                                <p class="text-fg"><span class="text-2xl font-semibold tabular-nums">{{ number_format($schedule['attended']) }}</span> <span class="text-fg-muted">of</span> <span class="font-semibold tabular-nums">{{ number_format($schedule['scheduled']) }}</span><br><span class="text-fg-muted">published periods attended</span></p>
                            @endif
                        </div>
                    </div>
                    @if ($schedule['unassigned_shifts'] > 0)
                        <p class="mt-4 flex items-start gap-2 rounded-lg bg-warning-soft px-3 py-2 text-sm text-fg"><x-icon name="alert" class="mt-0.5 size-4 shrink-0 text-warning" /> {{ number_format($schedule['unassigned_shifts']) }} published {{ $schedule['unassigned_shifts'] === 1 ? 'shift' : 'shifts' }} unassigned in the next 7 days.</p>
                    @else
                        <p class="mt-4 flex items-start gap-2 rounded-lg bg-success-soft px-3 py-2 text-sm text-fg"><x-icon name="check" class="mt-0.5 size-4 shrink-0 text-success" /> Published shifts in the next 7 days have an assignment.</p>
                    @endif
                @endif
                <div @class(['mt-5 border-t border-edge pt-4' => (bool) $schedule])>
                    <p class="text-xs font-medium text-fg-muted">Upcoming assignments</p>
                    <ul class="mt-2 space-y-2">
                        @forelse ($upcomingAssignments->take(3) as $assignment)
                            @php
                                $starts = \Illuminate\Support\Str::of((string) $assignment->shift?->starts_at)->substr(0, 5);
                                $ends = \Illuminate\Support\Str::of((string) $assignment->shift?->ends_at)->substr(0, 5);
                                $isToday = $assignment->work_date->toDateString() === $now->toDateString();
                            @endphp
                            <li class="flex items-center gap-3 text-sm">
                                <span class="grid w-11 shrink-0 place-items-center rounded-md border border-edge bg-surface-muted py-1 text-center leading-none" aria-hidden="true">
                                    <span class="text-[0.625rem] font-medium text-fg-subtle uppercase">{{ $assignment->work_date->format('M') }}</span>
                                    <span class="text-sm font-semibold tabular-nums text-fg">{{ $assignment->work_date->format('j') }}</span>
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-fg">{{ $assignment->staffMember?->name ?? 'Unassigned' }}</p>
                                    <p class="truncate text-xs text-fg-muted">{{ $assignment->shift?->name ?? 'Shift' }} · {{ $isToday ? 'Today' : $assignment->work_date->format('D') }} · {{ $starts }}–{{ $ends }}</p>
                                </div>
                            </li>
                        @empty
                            <li class="text-sm text-fg-muted">Nothing is scheduled from today onward.</li>
                        @endforelse
                    </ul>
                </div>
            </x-card>
        @endif

        @if ($showLeave)
            <x-card icon="document-check" title="Leave decisions" description="Requests in this scope." decision="approve or reject before the start date">
                <x-slot:actions><a href="{{ route('org.leave.index') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-primary hover:underline">Review leave</a></x-slot:actions>
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p @class(['text-[1.875rem] leading-none font-semibold tabular-nums', 'text-warning' => $pendingCount > 0, 'text-fg' => $pendingCount === 0])>{{ number_format($pendingCount) }}</p>
                        <p class="mt-1 text-xs text-fg-subtle">waiting for a decision</p>
                    </div>
                    @if (($leaveSummary['utilisation'] ?? null) !== null)
                        <div class="w-36 text-right">
                            <p class="text-xs text-fg-muted">Open-year utilisation <span class="font-semibold tabular-nums text-fg">{{ $leaveSummary['utilisation'] }}%</span></p>
                            <div class="mt-1.5 meter"><span class="bg-accent" style="width: {{ min(100, (int) $leaveSummary['utilisation']) }}%"></span></div>
                        </div>
                    @endif
                </div>
                <ul class="mt-4 divide-y divide-edge border-t border-edge">
                    @forelse ($pendingLeave->take(3) as $leaveRequest)
                        <li class="flex items-center justify-between gap-3 py-2.5 text-sm">
                            <div class="flex min-w-0 items-center gap-3">
                                <span aria-hidden="true" class="grid size-8 shrink-0 place-items-center rounded-full bg-accent-soft text-[0.6875rem] font-semibold text-accent">{{ \Illuminate\Support\Str::of($leaveRequest->staffMember?->name ?? '?')->explode(' ')->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->join('') }}</span>
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-fg">{{ $leaveRequest->staffMember?->name ?? 'Staff member' }}</p>
                                    <p class="text-xs text-fg-muted">{{ $leaveRequest->leaveType?->name ?? 'Leave' }} · {{ $leaveRequest->starts_on->format('j M') }} – {{ $leaveRequest->ends_on->format('j M') }}</p>
                                </div>
                            </div>
                            <x-badge :tone="$leaveRequest->status->tone()">{{ $leaveRequest->status->label() }}</x-badge>
                        </li>
                    @empty
                        <li class="py-3 text-sm text-fg-muted">No requests are waiting.</li>
                    @endforelse
                </ul>
            </x-card>
        @endif

        @if ($showComposition)
            @php
                $workTotal = (int) collect($workTypes)->sum('value');
                $workInks = ['var(--primary)', 'var(--accent)', 'var(--brand-teal)', 'var(--fg-subtle)'];
            @endphp
            <x-card icon="layers" title="Workforce composition" description="Active people in this scope, by work type." decision="which check-in methods each group relies on">
                @if ($workTotal === 0)
                    <p class="text-sm text-fg-muted">No active people in this scope.</p>
                @else
                    <p class="text-[1.875rem] leading-none font-semibold tabular-nums text-fg">{{ number_format($workTotal) }}</p>
                    <p class="mt-1 text-xs text-fg-subtle">active people</p>
                    <div class="mt-4 flex h-2.5 gap-0.5 overflow-hidden rounded-full bg-surface-muted" role="img" aria-label="Active people by work type">
                        @foreach ($workTypes as $row)
                            @if ($row['value'] > 0)
                                <div class="h-full first:rounded-l-full last:rounded-r-full" style="width: {{ round($row['value'] / $workTotal * 100, 2) }}%; background: {{ $workInks[$loop->index] ?? 'var(--fg-subtle)' }}"></div>
                            @endif
                        @endforeach
                    </div>
                @endif
                <ul class="mt-4 space-y-2 text-sm">
                    @foreach ($workTypes as $row)
                        <li class="flex items-center justify-between gap-3">
                            <span class="inline-flex min-w-0 items-center gap-2">
                                <span class="size-2.5 shrink-0 rounded-full" style="background: {{ $workInks[$loop->index] ?? 'var(--fg-subtle)' }}" aria-hidden="true"></span>
                                <span class="text-fg">{{ $row['label'] }}</span>
                            </span>
                            <span class="shrink-0 tabular-nums"><span class="font-semibold text-fg">{{ number_format($row['value']) }}</span>@if ($workTotal > 0)<span class="ml-1.5 inline-block w-9 text-right text-xs text-fg-subtle">{{ (int) round($row['value'] / $workTotal * 100) }}%</span>@endif</span>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif
    </div>
@endif

{{-- Level 4 · Supporting information --}}
@php
    $adminLinks = [];
    if ($counts['show_structure']) {
        $adminLinks[] = ['icon' => 'building', 'value' => $counts['departments'], 'label' => 'departments', 'href' => route('org.departments.index')];
        $adminLinks[] = ['icon' => 'map-pin', 'value' => $counts['locations'], 'label' => 'locations', 'href' => route('org.locations.index')];
    }
    if ($counts['show_accounts']) {
        $adminLinks[] = ['icon' => 'user', 'value' => $counts['users'], 'label' => 'accounts', 'href' => route('org.users.index')];
        $adminLinks[] = ['icon' => 'mail', 'value' => $counts['pending_invitations'], 'label' => 'invitations', 'href' => route('org.users.index').'#invitations'];
    }
    if ($signInsToday !== null) {
        $adminLinks[] = ['icon' => 'login', 'value' => $signInsToday, 'label' => 'sign-ins today', 'href' => route('org.audit.logins')];
    }
    if ($user->can('audit.view')) {
        $adminLinks[] = ['icon' => 'shield-check', 'value' => null, 'label' => 'Audit trail', 'href' => route('org.audit.index')];
    }
@endphp

<div class="grid gap-6 lg:grid-cols-5">
    <div @class(['lg:col-span-2' => $adminLinks !== [], 'lg:col-span-5' => $adminLinks === []])>
        @include('tenant.dashboard.notices')
    </div>

    @if ($adminLinks !== [])
        <x-card class="lg:col-span-3" icon="settings" title="Administration" description="Structure, access, and accountability for this workspace.">
            <nav aria-label="Administration" class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                @foreach ($adminLinks as $link)
                    <a href="{{ $link['href'] }}" class="group flex min-h-11 items-center gap-2.5 rounded-lg border border-edge px-3 py-2.5 transition-colors hover:border-edge-strong hover:bg-surface-muted focus-visible:outline-2">
                        <x-icon :name="$link['icon']" class="size-4 shrink-0 text-fg-subtle group-hover:text-primary" />
                        <span class="min-w-0 text-sm leading-tight">
                            @if ($link['value'] !== null)
                                <span class="font-semibold tabular-nums text-fg">{{ number_format($link['value']) }}</span>
                            @endif
                            <span class="text-fg-muted">{{ $link['label'] }}</span>
                        </span>
                    </a>
                @endforeach
            </nav>
            @if ($counts['unlinked_staff'] > 0 && $user->can('staff.view'))
                <p class="mt-4 flex items-start gap-2 rounded-lg bg-info-soft px-3 py-2 text-sm text-fg">
                    <x-icon name="info" class="mt-0.5 size-4 shrink-0 text-info" />
                    <span>{{ number_format($counts['unlinked_staff']) }} active {{ $counts['unlinked_staff'] === 1 ? 'person has' : 'people have' }} no user account. <a href="{{ route('org.staff.index') }}" class="font-medium text-primary hover:underline">Staff register</a></span>
                </p>
            @endif
        </x-card>
    @endif
</div>
