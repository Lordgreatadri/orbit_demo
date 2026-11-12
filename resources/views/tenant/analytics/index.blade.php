<x-layouts.app title="Analytics" wide>
    @php
        $rangePresent = (int) collect($trend)->sum('present');
        $rangeLate = (int) collect($trend)->sum('late');
        $rangeLeave = (int) collect($trend)->sum('on_leave');
        $rangeOther = (int) collect($trend)->sum('other');
        $rangeTotal = $rangePresent + $rangeLate + $rangeLeave + $rangeOther;
        $dayCount = count($trend);
        $hasTrend = $rangeTotal > 0;
        $recordPhrase = function (int $count, string $one, string $many): string {
            if ($count === 0) {
                return 'No '.$many;
            }

            return $count === 1 ? 'One '.$one : number_format($count).' '.$many;
        };
        if (! $hasTrend) {
            $trendConclusion = 'No attendance records in this range.';
        } elseif ($rangeLate > 0 && $rangePresent === 0) {
            $trendConclusion = $recordPhrase($rangeLate, 'late record', 'late records').' in this range. No present records.';
        } elseif ($rangePresent > 0 && $rangeLate === 0) {
            $trendConclusion = $recordPhrase($rangePresent, 'present record', 'present records').' in this range. No late records.';
        } elseif ($rangePresent === 0 && $rangeLate === 0) {
            $trendConclusion = 'No present or late records in this range.';
        } else {
            $trendConclusion = $recordPhrase($rangePresent, 'present record', 'present records').' and '.lcfirst($recordPhrase($rangeLate, 'late record', 'late records')).' in this range.';
        }
        $punctuality = ($rangePresent + $rangeLate) > 0 ? (int) round($rangePresent / ($rangePresent + $rangeLate) * 100) : null;
        $workTotal = max(1, (int) $workTypes->sum('value'));
        $departmentMax = max(1, (int) collect($departmentRows)->max('total'));
        $derived = ($today['absent_basis'] ?? 'derived') === 'derived';
        $active = (int) $today['active_staff'];
        $presentShare = $active > 0 && $derived ? (int) round($today['present'] / $active * 100) : null;
        $absentShare = $active > 0 && $derived ? (int) round($today['absent'] / $active * 100) : null;
        $trendSeries = [
            ['key' => 'present', 'label' => 'Present', 'ink' => \App\Analytics\ChartPalette::color('present')],
            ['key' => 'late', 'label' => 'Late', 'ink' => \App\Analytics\ChartPalette::color('late')],
            ['key' => 'on_leave', 'label' => 'On leave', 'ink' => \App\Analytics\ChartPalette::color('on_leave')],
            ['key' => 'other', 'label' => 'Other', 'ink' => \App\Analytics\ChartPalette::color('other')],
        ];
        $workInks = ['var(--primary)', 'var(--accent)', 'var(--brand-teal)', 'var(--fg-subtle)'];
        $workRows = $workTypes->values()->map(fn ($row, $index) => ['label' => $row['label'], 'total' => (int) $row['value'], 'ink' => $workInks[$index] ?? 'var(--fg-subtle)'])->all();
        $scheduled = (int) $adherence['scheduled'];
        $attended = (int) $adherence['attended'];
        $adherenceShare = $scheduled > 0 ? (int) round($attended / $scheduled * 100) : null;
        $methodTotal = (int) collect($methods)->sum('total');
        $fromLabel = \Illuminate\Support\Carbon::parse($from)->format('j M');
        $toLabel = \Illuminate\Support\Carbon::parse($to)->format('j M Y');
    @endphp

    <x-page-header title="Workforce analytics" :description="$fromLabel.' – '.$toLabel.' · '.$scopeLabel.'. Every chart uses this range. Counts come from stored records; a zero is a stored zero, not an estimate.'" eyebrow="Insights">
        <x-button :href="route('org.analytics.export', request()->query())" variant="secondary" size="sm"><x-icon name="document-check" class="size-4" /> Export CSV</x-button>
    </x-page-header>

    <x-filter-bar :action="route('org.analytics.index')" :reset="route('org.analytics.index')">
        <label class="flex min-w-[9rem] flex-1 flex-col gap-1 text-xs font-medium text-fg-muted"><span>From</span><input type="date" name="from" value="{{ $from }}" class="min-h-11 w-full rounded-lg border border-edge bg-surface px-2.5 text-sm text-fg focus-visible:outline-2"></label>
        <label class="flex min-w-[9rem] flex-1 flex-col gap-1 text-xs font-medium text-fg-muted"><span>To</span><input type="date" name="to" value="{{ $to }}" class="min-h-11 w-full rounded-lg border border-edge bg-surface px-2.5 text-sm text-fg focus-visible:outline-2"></label>
        @if (count($departments) > 0)
            <label class="flex min-w-[9rem] flex-1 flex-col gap-1 text-xs font-medium text-fg-muted"><span>Department</span>
                <select name="department" class="min-h-11 w-full rounded-lg border border-edge bg-surface px-2.5 text-sm text-fg focus-visible:outline-2">
                    <option value="">All in scope</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department['uuid'] }}" @selected($selectedDepartment === $department['uuid'])>{{ $department['name'] }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <label class="flex min-w-[9rem] flex-1 flex-col gap-1 text-xs font-medium text-fg-muted"><span>Location</span>
            <select name="location" class="min-h-11 w-full rounded-lg border border-edge bg-surface px-2.5 text-sm text-fg focus-visible:outline-2">
                <option value="">All locations</option>
                @foreach ($locations as $location)
                    <option value="{{ $location->uuid }}" @selected($selectedLocation === $location->uuid)>{{ $location->name }}</option>
                @endforeach
            </select>
        </label>
    </x-filter-bar>

    @if ($rangeAdjusted)
        <x-alert type="info" title="Range shortened">
            Charts cover at most 62 days. A longer or reversed range is shown as the latest 30 days, and the export uses those same dates.
        </x-alert>
    @endif

    {{-- Level 1 · What the figures say --}}
    <x-insight-list :insights="$insights" />

    {{-- Level 2 · Today --}}
    <section aria-labelledby="today-heading">
        <div class="mb-3 flex items-baseline justify-between gap-4">
            <h2 id="today-heading" class="section-title">Today</h2>
            <p class="section-meta">{{ $derived ? 'Absent is derived from active people with no covering record' : 'Location filter on: absent is the stored status' }}</p>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
            <x-stat label="Present today" :value="number_format($today['present'])" :hint="$presentShare !== null ? $presentShare.'% of '.number_format($active).' active staff' : 'No active staff'" icon="check-circle" tone="success" :meter="$presentShare" />
            <x-stat label="Late today" :value="number_format($today['late'])" hint="Checked in after the cutoff" icon="clock" tone="warning" :emphasis="$today['late'] > 0 ? 'warning' : null" />
            <x-stat label="Absent today" :value="number_format($today['absent'])" :hint="$derived ? 'Active people without a covering record' : 'Stored absent status at this location'" icon="alert" tone="danger" :meter="$absentShare" :emphasis="$today['absent'] > 0 ? 'danger' : null" />
            <x-stat label="On leave today" :value="number_format($today['on_leave'])" :hint="number_format($leavePending).' awaiting a decision'" icon="calendar" tone="accent" />
        </div>
    </section>

    {{-- Level 3 · Trend and share --}}
    <div class="grid min-w-0 gap-6 xl:grid-cols-3">
        <x-card class="xl:col-span-2" icon="chart" :title="$trendConclusion" :description="$dayCount.' '.($dayCount === 1 ? 'day' : 'days').', '.$fromLabel.' – '.$toLabel.'. Each column is one day of stored records.'" decision="where attendance dipped, so you can check the schedule or a location for that day">
            @if ($hasTrend)
                <div class="mb-5 grid grid-cols-3 gap-3 rounded-lg bg-surface-muted/70 p-3 text-center">
                    <div><p class="text-lg font-semibold tabular-nums text-fg">{{ number_format($rangeTotal) }}</p><p class="text-[0.6875rem] text-fg-subtle">records in range</p></div>
                    <div title="Present ÷ (present + late)"><p class="text-lg font-semibold tabular-nums text-fg">{{ $punctuality === null ? '—' : $punctuality.'%' }}</p><p class="text-[0.6875rem] text-fg-subtle">on-time share</p></div>
                    <div><p class="text-lg font-semibold tabular-nums text-fg">{{ $dayCount > 0 ? number_format($rangeTotal / $dayCount, 1) : '—' }}</p><p class="text-[0.6875rem] text-fg-subtle">records per day</p></div>
                </div>
                <x-charts.stacked :rows="$trend" :series="$trendSeries" height="16rem" caption="Daily attendance records from {{ $from }} to {{ $to }}" />
                <p class="mt-3 text-[0.6875rem] text-fg-subtle">On-time share is present ÷ (present + late). Two periods on one day count twice. A flat mark is a day with no record.</p>
            @else
                <x-empty-state title="No attendance in this range" description="Check-ins, leave, and corrections for these dates will stack on the chart." />
            @endif
        </x-card>

        <x-card icon="layers" title="Status share" :description="'Stored attendance records by status, '.$fromLabel.' – '.$toLabel.'. Every status is listed, including zeros.'" decision="whether lateness or incomplete records need a policy reminder">
            <x-charts.donut :segments="$statuses" size="9.5rem" legend-title="Status counts" caption="Attendance records by status from {{ $from }} to {{ $to }}" />
            <p class="mt-4 text-[0.6875rem] leading-relaxed text-fg-subtle">Absent here is a stored status. The Today tile also counts active staff with no covering record.</p>
        </x-card>
    </div>

    {{-- Level 3 · Drivers --}}
    <div class="grid gap-6 lg:grid-cols-3">
        <x-card icon="qr" title="Verification methods" :description="number_format($methodTotal).' accepted check-in checks in this range.'" decision="which check-in methods to keep enabled or promote">
            <x-charts.bars :rows="$methods" caption="Accepted check-in checks by method from {{ $from }} to {{ $to }}" />
            <p class="mt-4 text-[0.6875rem] leading-relaxed text-fg-subtle">Face, fingerprint, and NFC are not enabled, so they are not drawn. Wi-Fi supports another method and cannot confirm attendance by itself.</p>
        </x-card>

        <x-card icon="briefcase" title="By work type" description="Attendance rows in this range, by the person's work type." decision="whether field and remote teams are recording as often as office staff">
            <x-charts.bars :rows="$workRows" caption="Attendance rows by work type from {{ $from }} to {{ $to }}" />
        </x-card>

        <x-card icon="building" title="By department" description="Top departments in this range. Segments follow the trend colours." decision="which department to follow up first">
            @if ($departmentRows === [])
                <p class="text-sm text-fg-muted">No department totals in this range.</p>
            @else
                <ul class="space-y-3.5">
                    @foreach ($departmentRows as $row)
                        <li>
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <span class="min-w-0 truncate font-medium text-fg">{{ $row['name'] }}</span>
                                <span class="shrink-0 font-semibold tabular-nums text-fg">{{ number_format($row['total']) }}</span>
                            </div>
                            <div class="mt-1.5 flex h-2 gap-px overflow-hidden rounded-full bg-surface-muted" role="presentation" title="{{ $row['present'] }} present, {{ $row['late'] }} late, {{ $row['on_leave'] }} on leave, {{ $row['other'] }} other">
                                @if ($row['present'] > 0)<div class="h-full bg-primary" style="width: {{ round($row['present'] / $departmentMax * 100, 2) }}%"></div>@endif
                                @if ($row['late'] > 0)<div class="h-full bg-warning" style="width: {{ round($row['late'] / $departmentMax * 100, 2) }}%"></div>@endif
                                @if ($row['on_leave'] > 0)<div class="h-full bg-accent" style="width: {{ round($row['on_leave'] / $departmentMax * 100, 2) }}%"></div>@endif
                                @if ($row['other'] > 0)<div class="h-full bg-fg-subtle/50" style="width: {{ round($row['other'] / $departmentMax * 100, 2) }}%"></div>@endif
                            </div>
                            <p class="mt-1 text-[0.6875rem] text-fg-subtle">{{ number_format($row['present']) }} present · {{ number_format($row['late']) }} late · {{ number_format($row['on_leave']) }} on leave</p>
                        </li>
                    @endforeach
                </ul>
                <div class="sr-only">
                    <table>
                        <caption>Attendance records by department from {{ $from }} to {{ $to }}</caption>
                        <thead><tr><th>Department</th><th>Present</th><th>Late</th><th>On leave</th><th>Other</th><th>Total</th></tr></thead>
                        <tbody>
                            @foreach ($departmentRows as $row)
                                <tr><td>{{ $row['name'] }}</td><td>{{ $row['present'] }}</td><td>{{ $row['late'] }}</td><td>{{ $row['on_leave'] }}</td><td>{{ $row['other'] }}</td><td>{{ $row['total'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>

    {{-- Level 3 · Schedules and leave --}}
    <div class="grid gap-6 lg:grid-cols-3">
        <x-card icon="calendar" title="Schedule adherence" description="Published assignments in this range with a present or late record for the same person and date." decision="whether published schedules match who actually turns up">
            <div class="flex items-center gap-5">
                <x-charts.gauge :value="$adherenceShare" label="attended" :tone="$adherenceShare !== null && $adherenceShare < 80 ? 'warning' : 'primary'" />
                <p class="min-w-0 text-sm text-fg"><span class="text-2xl font-semibold tabular-nums">{{ number_format($attended) }}</span> <span class="text-fg-muted">of</span> <span class="font-semibold tabular-nums">{{ number_format($scheduled) }}</span><br><span class="text-fg-muted">scheduled periods attended</span></p>
            </div>
            @if ($schedule['unassigned_shifts'] > 0)
                <p class="mt-4 flex items-start gap-2 rounded-lg bg-warning-soft px-3 py-2 text-sm text-fg"><x-icon name="alert" class="mt-0.5 size-4 shrink-0 text-warning" /> {{ number_format($schedule['unassigned_shifts']) }} published {{ $schedule['unassigned_shifts'] === 1 ? 'shift has' : 'shifts have' }} no assignment in the next 7 days.</p>
            @else
                <p class="mt-4 flex items-start gap-2 rounded-lg bg-success-soft px-3 py-2 text-sm text-fg"><x-icon name="check" class="mt-0.5 size-4 shrink-0 text-success" /> Published shifts in the next 7 days are assigned.</p>
            @endif
        </x-card>

        <x-card icon="document-check" title="Leave decisions" description="Requests that start in this range, from the leave records." decision="whether approvals are keeping up and how much entitlement is left">
            @php
                $decisionRows = collect(['submitted' => ['Waiting', 'var(--warning)'], 'approved' => ['Approved', 'var(--success)'], 'rejected' => ['Rejected', 'var(--danger)'], 'cancelled' => ['Cancelled', 'var(--fg-subtle)'], 'withdrawn' => ['Withdrawn', 'var(--edge-strong)']])
                    ->map(fn ($meta, $key) => ['label' => $meta[0], 'ink' => $meta[1], 'total' => (int) ($leaveDecisions[$key] ?? 0)]);
                $decisionTotal = (int) $decisionRows->sum('total');
            @endphp
            <div class="flex h-2.5 gap-px overflow-hidden rounded-full bg-surface-muted" role="presentation">
                @foreach ($decisionRows as $row)
                    @if ($row['total'] > 0)
                        <span class="h-full" style="width: {{ round($row['total'] / max(1, $decisionTotal) * 100, 2) }}%; background: {{ $row['ink'] }}"></span>
                    @endif
                @endforeach
            </div>
            <dl class="mt-3 grid grid-cols-2 gap-x-6 gap-y-1.5 text-sm">
                @foreach ($decisionRows as $row)
                    <div class="flex items-center justify-between gap-2">
                        <dt class="inline-flex items-center gap-2 text-fg-muted"><span class="size-2 rounded-full" style="background: {{ $row['ink'] }}" aria-hidden="true"></span>{{ $row['label'] }}</dt>
                        <dd class="font-semibold tabular-nums text-fg">{{ number_format($row['total']) }}</dd>
                    </div>
                @endforeach
            </dl>
            <div class="mt-4 border-t border-edge pt-3">
                @if ($leaveSummary['utilisation'] === null)
                    <p class="text-sm text-fg-muted">No open leave-year entitlement to compare.</p>
                @else
                    <div class="flex items-baseline justify-between gap-3 text-sm">
                        <span class="text-fg-muted">Open entitlement used</span>
                        <span class="tabular-nums"><span class="font-semibold text-fg">{{ $leaveSummary['utilisation'] }}%</span> <span class="text-xs text-fg-subtle">{{ number_format($leaveSummary['used_days']) }} of {{ number_format($leaveSummary['entitled_days']) }} days</span></span>
                    </div>
                    <div class="mt-1.5 meter"><span class="bg-accent" style="width: {{ min(100, (int) $leaveSummary['utilisation']) }}%"></span></div>
                @endif
                <p class="mt-2 text-xs text-fg-subtle">{{ number_format($leavePending) }} waiting in this scope, including requests that start outside this range. {{ number_format($leaveSummary['upcoming_approved']) }} approved requests overlap the next 14 days.</p>
            </div>
        </x-card>

        <x-card icon="map-pin" title="Scheduled arrangements" description="How published assignments in this range are arranged." decision="whether on-site, remote, and field cover is balanced">
            @if ($arrangements === [])
                <div class="flex items-center gap-3 rounded-lg bg-surface-muted px-4 py-5">
                    <x-icon name="map-pin" class="size-5 shrink-0 text-fg-subtle" />
                    <p class="text-sm text-fg-muted">No arranged assignments in this range. An assignment without an arrangement is not counted.</p>
                </div>
            @else
                @php
                    $arrangementRows = collect($arrangements)->map(fn ($total, $kind) => ['label' => ucfirst(str_replace('_', ' ', (string) $kind)), 'total' => (int) $total, 'ink' => \App\Analytics\ChartPalette::color((string) $kind)])->values()->all();
                @endphp
                <x-charts.bars :rows="$arrangementRows" caption="Published assignments by arrangement from {{ $from }} to {{ $to }}" />
            @endif
        </x-card>
    </div>

    {{-- Level 4 · Notifications --}}
    <x-card icon="bell" title="Notifications" description="Delivery is whether a channel accepted the notice. Read is whether a person opened it. The two are not the same." decision="whether notices are reaching people and being read">
        @if ($notifications['deliveries'] === null)
            <p class="text-sm text-fg-muted">Organisation-wide delivery totals are shown to organisation administrators. You have {{ number_format($notifications['unread']) }} unread {{ $notifications['unread'] === 1 ? 'notice' : 'notices' }}.</p>
        @else
            @php
                $readTotal = (int) $notifications['unread'] + (int) $notifications['read'];
                $readShare = $readTotal > 0 ? (int) round((int) $notifications['read'] / $readTotal * 100) : null;
            @endphp
            <div class="grid gap-6 md:grid-cols-3">
                <div class="flex items-center gap-4">
                    <x-charts.gauge :value="$readShare" label="read" tone="info" size="6rem" />
                    <dl class="text-sm">
                        <div class="flex items-baseline gap-2"><dt class="text-fg-muted">Read</dt><dd class="font-semibold tabular-nums text-fg">{{ number_format((int) $notifications['read']) }}</dd></div>
                        <div class="flex items-baseline gap-2"><dt class="text-fg-muted">Unread</dt><dd class="font-semibold tabular-nums text-fg">{{ number_format($notifications['unread']) }}</dd></div>
                    </dl>
                </div>
                <div class="md:col-span-2">
                    @if ($notifications['deliveries'] === [])
                        <div class="flex h-full items-center gap-3 rounded-lg bg-surface-muted px-4 py-5">
                            <x-icon name="mail" class="size-5 shrink-0 text-fg-subtle" />
                            <p class="text-sm text-fg-muted">No delivery claims are stored for this organisation.</p>
                        </div>
                    @else
                        @php
                            $deliveryRows = collect($notifications['deliveries'])->map(fn ($total, $status) => ['label' => 'Delivery · '.str_replace('_', ' ', (string) $status), 'total' => (int) $total, 'ink' => \App\Analytics\ChartPalette::color((string) $status)])->values()->all();
                        @endphp
                        <x-charts.bars :rows="$deliveryRows" caption="Notification deliveries by status" />
                    @endif
                </div>
            </div>
        @endif
    </x-card>
</x-layouts.app>
