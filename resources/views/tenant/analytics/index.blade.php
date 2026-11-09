<x-layouts.app title="Analytics" wide>
    @php
        $rangePresent = (int) collect($trend)->sum('present');
        $rangeLate = (int) collect($trend)->sum('late');
        $rangeLeave = (int) collect($trend)->sum('on_leave');
        $rangeOther = (int) collect($trend)->sum('other');
        $rangeMax = max(1, (int) collect($trend)->max(fn ($row) => $row['present'] + $row['late'] + $row['on_leave'] + $row['other']));
        $dayCount = count($trend);
        $labelEvery = max(1, (int) ceil($dayCount / 8));
        $hasTrend = ($rangePresent + $rangeLate + $rangeLeave + $rangeOther) > 0;
        $workMax = max(1, (int) $workTypes->max('value'));
        $workTotal = max(1, (int) $workTypes->sum('value'));
        $departmentMax = max(1, (int) $departments->max('total'));
        $presentShare = $today['active_staff'] > 0 ? (int) round($today['present'] / $today['active_staff'] * 100) : null;
        $swatches = [
            'present' => ['swatch' => 'bg-primary', 'ink' => 'var(--primary)'],
            'late' => ['swatch' => 'bg-warning', 'ink' => 'var(--warning)'],
            'absent' => ['swatch' => 'bg-danger', 'ink' => 'var(--danger)'],
            'on_leave' => ['swatch' => 'bg-accent', 'ink' => 'var(--accent)'],
            'pending_verification' => ['swatch' => 'bg-info', 'ink' => 'var(--info)'],
            'rejected' => ['swatch' => 'bg-brand-deep', 'ink' => 'var(--brand-deep)'],
            'incomplete' => ['swatch' => 'bg-fg-subtle', 'ink' => 'var(--fg-subtle)'],
            'excused' => ['swatch' => 'bg-success', 'ink' => 'var(--success)'],
            'manual' => ['swatch' => 'bg-fg-muted', 'ink' => 'var(--fg-muted)'],
            'qr' => ['swatch' => 'bg-primary', 'ink' => 'var(--primary)'],
            'gps' => ['swatch' => 'bg-success', 'ink' => 'var(--success)'],
            'ble' => ['swatch' => 'bg-accent', 'ink' => 'var(--accent)'],
            'wifi' => ['swatch' => 'bg-info', 'ink' => 'var(--info)'],
            'otp' => ['swatch' => 'bg-warning', 'ink' => 'var(--warning)'],
            'device' => ['swatch' => 'bg-brand-deep', 'ink' => 'var(--brand-deep)'],
        ];
        $paint = function (array $rows) use ($swatches): array {
            return array_map(function (array $row) use ($swatches): array {
                $tone = $swatches[$row['key']] ?? ['swatch' => 'bg-fg-subtle', 'ink' => 'var(--fg-subtle)'];

                return $row + $tone;
            }, $rows);
        };
        $statusRows = $paint($statuses);
        $methodRows = $paint($methods);
        $stackOrder = array_reverse($statusRows);
    @endphp

    <x-page-header title="Workforce analytics" description="Every figure is a count of stored attendance records for the dates below. Nothing on this page is estimated.">
        <x-button :href="route('org.analytics.export', ['from' => $from, 'to' => $to])" variant="secondary" size="sm"><x-icon name="document-check" class="size-4" /> Export CSV</x-button>
    </x-page-header>

    <x-filter-bar :action="route('org.analytics.index')" :reset="route('org.analytics.index')">
        <label class="flex flex-col gap-1 text-xs font-medium text-fg-muted"><span>From</span><input type="date" name="from" value="{{ $from }}" class="h-9 rounded-lg border border-edge bg-surface px-2.5 text-sm text-fg"></label>
        <label class="flex flex-col gap-1 text-xs font-medium text-fg-muted"><span>To</span><input type="date" name="to" value="{{ $to }}" class="h-9 rounded-lg border border-edge bg-surface px-2.5 text-sm text-fg"></label>
    </x-filter-bar>

    @if ($rangeAdjusted)
        <x-alert type="info" title="Range shortened">
            Charts cover at most 62 days. A longer or reversed range is shown as the latest 30 days, and the export uses those same dates.
        </x-alert>
    @endif

    <section aria-labelledby="today-heading">
        <h2 id="today-heading" class="mb-3 text-sm font-semibold text-fg">Today</h2>
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-stat label="Present today" :value="number_format($today['present'])" :hint="$presentShare !== null ? $presentShare.'% of '.number_format($today['active_staff']).' active staff' : 'No active staff'" icon="check" tone="success" />
            <x-stat label="Late today" :value="number_format($today['late'])" hint="Checked in after the cutoff" icon="clock" tone="warning" />
            <x-stat label="Absent today" :value="number_format($today['absent'])" hint="Active staff without a covering record" icon="users" tone="danger" />
            <x-stat label="On leave today" :value="number_format($today['on_leave'])" :hint="number_format($leavePending).' awaiting a decision'" icon="calendar" tone="accent" />
        </div>
    </section>

    <div class="grid gap-8 lg:grid-cols-2">
        <x-card title="Status share" description="Pie of stored attendance records from {{ $from }} to {{ $to }}. Every status is listed. A zero is a status with no row in this range, not an estimate. Absent here is a stored status. The Today tile above also counts active staff with no covering record.">
            <x-charts.pie :segments="$statusRows" caption="Attendance records by status from {{ $from }} to {{ $to }}" />
        </x-card>
        <x-card title="Status counts" description="Bar for each stored status in the same range, including statuses at zero.">
            <x-charts.columns :rows="$statusRows" caption="Attendance counts by status from {{ $from }} to {{ $to }}" />
        </x-card>
    </div>

    <x-card title="Verification methods" description="Accepted check-in checks in this range. Each bar is one method the engine can run. Face, fingerprint, and NFC are not enabled, so they are not drawn. Wi-Fi can support another method. It cannot confirm attendance by itself.">
        <x-charts.columns :rows="$methodRows" caption="Accepted check-in checks by method from {{ $from }} to {{ $to }}" />
    </x-card>

    <x-card title="Attendance trend" :description="$dayCount.' '.($dayCount === 1 ? 'day' : 'days').' from '.$from.' to '.$to.'. Each bar is one day, counted in the database, so a long history stays this size.'">
        <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-edge bg-surface-muted/40 px-3 py-2">
                <p class="text-xs text-fg-muted">Present</p>
                <p class="text-lg font-semibold tabular-nums text-fg">{{ number_format($rangePresent) }}</p>
            </div>
            <div class="rounded-xl border border-edge bg-surface-muted/40 px-3 py-2">
                <p class="text-xs text-fg-muted">Late</p>
                <p class="text-lg font-semibold tabular-nums text-fg">{{ number_format($rangeLate) }}</p>
            </div>
            <div class="rounded-xl border border-edge bg-surface-muted/40 px-3 py-2">
                <p class="text-xs text-fg-muted">On leave</p>
                <p class="text-lg font-semibold tabular-nums text-fg">{{ number_format($rangeLeave) }}</p>
            </div>
            <div class="rounded-xl border border-edge bg-surface-muted/40 px-3 py-2">
                <p class="text-xs text-fg-muted">Other</p>
                <p class="text-lg font-semibold tabular-nums text-fg">{{ number_format($rangeOther) }}</p>
            </div>
        </div>

        <div class="mb-4 flex flex-wrap gap-x-4 gap-y-2 text-xs text-fg-muted">
            @foreach ($statusRows as $status)
                <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm" style="background: {{ $status['ink'] }}" aria-hidden="true"></span> {{ $status['label'] }}</span>
            @endforeach
        </div>

        @if (! $hasTrend)
            <x-empty-state title="No attendance in this range" description="Check-ins, leave, and corrections for these dates will stack on the chart." />
        @else
            <div class="flex gap-3">
                <div class="flex shrink-0 flex-col justify-between pb-6 text-right text-[0.65rem] tabular-nums text-fg-subtle" style="height: 13rem" aria-hidden="true">
                    <span>{{ number_format($rangeMax) }}</span>
                    <span>{{ $rangeMax > 2 ? number_format((int) round($rangeMax / 2)) : '' }}</span>
                    <span>0</span>
                </div>
                <div class="min-w-0 flex-1 overflow-x-auto">
                    @php $chartMin = $dayCount > 14 ? max(28, $dayCount * 1.35) : null; @endphp
                    <div class="flex items-end gap-1 border-b border-edge" style="height: 13rem; @if ($chartMin) min-width: {{ $chartMin }}rem; @endif" role="img" aria-label="Daily attendance from {{ $from }} to {{ $to }}">
                        @foreach ($trend as $row)
                            @php
                                $segments = [];
                                foreach ($stackOrder as $status) {
                                    $count = (int) ($row['counts'][$status['key']] ?? 0);
                                    if ($count > 0) {
                                        $segments[] = ['height' => ($count / $rangeMax) * 100, 'ink' => $status['ink'], 'label' => $status['label'], 'count' => $count];
                                    }
                                }
                                $title = $row['date'].': '.collect($segments)->map(fn ($segment) => $segment['count'].' '.$segment['label'])->join(', ');
                            @endphp
                            <div class="flex min-w-3 flex-1 items-end justify-center" style="height: 100%" title="{{ $title !== $row['date'].': ' ? $title : $row['date'].': none' }}">
                                <div class="flex w-3 flex-col justify-end sm:w-4" style="height: 100%">
                                    @foreach ($segments as $segment)
                                        <div @class(['rounded-t-sm' => $loop->first]) style="height: {{ $segment['height'] }}%; background: {{ $segment['ink'] }}"></div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-2 flex gap-1 text-[0.65rem] text-fg-subtle" @if ($chartMin) style="min-width: {{ $chartMin }}rem" @endif aria-hidden="true">
                        @foreach ($trend as $row)
                            <span class="min-w-3 flex-1 truncate text-center">{{ $loop->first || $loop->last || $loop->iteration % $labelEvery === 0 ? \Illuminate\Support\Carbon::parse($row['date'])->format('j M') : '' }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
            <table class="sr-only">
                <caption>Daily attendance counts</caption>
                <thead>
                    <tr>
                        <th>Date</th>
                        @foreach ($statusRows as $status)
                            <th>{{ $status['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($trend as $row)
                        <tr>
                            <td>{{ $row['date'] }}</td>
                            @foreach ($statusRows as $status)
                                <td>{{ $row['counts'][$status['key']] ?? 0 }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-card>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-card title="By work type" description="Attendance rows in this range, grouped by the person's work type.">
            <ul class="space-y-4">
                @foreach ($workTypes as $row)
                    <li>
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="font-medium text-fg">{{ $row['label'] }}</span>
                            <span class="tabular-nums text-fg-muted">{{ number_format($row['value']) }} · {{ (int) round($row['value'] / $workTotal * 100) }}%</span>
                        </div>
                        <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-surface-muted" role="presentation">
                            <div class="h-full rounded-full bg-primary" style="width: {{ $row['value'] > 0 ? round($row['value'] / $workMax * 100) : 0 }}%"></div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-card>

        <x-card title="By department" description="Top departments in this range. Segments match the trend: present, late, on leave, then other.">
            @if ($departments->isEmpty())
                <p class="text-sm text-fg-muted">No department totals in this range.</p>
            @else
                <ul class="space-y-4">
                    @foreach ($departments as $row)
                        <li>
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="min-w-0 truncate font-medium text-fg">{{ $row['name'] }}</span>
                                <span class="shrink-0 tabular-nums text-fg-muted">{{ number_format($row['total']) }}</span>
                            </div>
                            <div class="mt-1.5 flex h-2 overflow-hidden rounded-full bg-surface-muted" role="presentation" title="{{ $row['present'] }} present, {{ $row['late'] }} late, {{ $row['on_leave'] }} on leave, {{ $row['other'] }} other">
                                @if ($row['present'] > 0)<div class="h-full bg-primary" style="width: {{ round($row['present'] / $departmentMax * 100, 2) }}%"></div>@endif
                                @if ($row['late'] > 0)<div class="h-full bg-warning" style="width: {{ round($row['late'] / $departmentMax * 100, 2) }}%"></div>@endif
                                @if ($row['on_leave'] > 0)<div class="h-full bg-accent" style="width: {{ round($row['on_leave'] / $departmentMax * 100, 2) }}%"></div>@endif
                                @if ($row['other'] > 0)<div class="h-full bg-fg-subtle/50" style="width: {{ round($row['other'] / $departmentMax * 100, 2) }}%"></div>@endif
                            </div>
                            <p class="mt-1 text-[0.7rem] text-fg-subtle">{{ number_format($row['present']) }} present · {{ number_format($row['late']) }} late · {{ number_format($row['on_leave']) }} on leave</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <x-card title="Leave decisions" description="Requests whose start date is in this range. Counts come from the leave records, not from attendance.">
            <dl class="grid grid-cols-2 gap-4">
                @foreach (['submitted' => 'Waiting', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled', 'withdrawn' => 'Withdrawn'] as $key => $label)
                    <div>
                        <dt class="text-xs text-fg-muted">{{ $label }}</dt>
                        <dd class="text-lg font-semibold tabular-nums">{{ number_format((int) ($leaveDecisions[$key] ?? 0)) }}</dd>
                    </div>
                @endforeach
            </dl>
            <p class="mt-3 text-xs text-fg-subtle">{{ number_format($leavePending) }} waiting in the organisation, including requests that start outside this range.</p>
        </x-card>
        <x-card title="Schedule adherence" description="Published assignments in this range compared with a present or late attendance record on the same person and date.">
            @php
                $scheduled = (int) $adherence['scheduled'];
                $attended = (int) $adherence['attended'];
                $share = $scheduled > 0 ? (int) round($attended / $scheduled * 100) : null;
            @endphp
            <p class="text-3xl font-semibold tabular-nums text-fg">{{ $share === null ? '—' : $share.'%' }}</p>
            <p class="mt-1 text-sm text-fg-muted">{{ number_format($attended) }} attended of {{ number_format($scheduled) }} scheduled periods.</p>
        </x-card>
        <x-card title="Scheduled arrangements" description="How published assignments in this range are arranged. A person with no arrangement on the assignment is not in this count.">
            @if ($arrangements === [])
                <p class="text-sm text-fg-muted">No arranged assignments in this range.</p>
            @else
                <ul class="space-y-2 text-sm">
                    @foreach ($arrangements as $kind => $total)
                        <li class="flex items-center justify-between"><span class="capitalize text-fg">{{ str_replace('_', ' ', $kind) }}</span><span class="tabular-nums text-fg-muted">{{ number_format($total) }}</span></li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</x-layouts.app>
