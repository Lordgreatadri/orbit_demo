<x-layouts.app title="Analytics" wide>
    @php
        $rangePresent = (int) collect($trend)->sum('present');
        $rangeLate = (int) collect($trend)->sum('late');
        $rangeLeave = (int) collect($trend)->sum('on_leave');
        $rangeOther = (int) collect($trend)->sum('other');
        $dayCount = count($trend);
        $hasTrend = ($rangePresent + $rangeLate + $rangeLeave + $rangeOther) > 0;
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
        $workMax = max(1, (int) $workTypes->max('value'));
        $workTotal = max(1, (int) $workTypes->sum('value'));
        $departmentMax = max(1, (int) collect($departmentRows)->max('total'));
        $presentShare = $today['active_staff'] > 0 && ($today['absent_basis'] ?? 'derived') === 'derived'
            ? (int) round($today['present'] / $today['active_staff'] * 100)
            : null;
        $statusRows = $statuses;
        $methodRows = $methods;
        $trendSeries = [
            ['key' => 'present', 'label' => 'Present', 'ink' => \App\Analytics\ChartPalette::color('present')],
            ['key' => 'late', 'label' => 'Late', 'ink' => \App\Analytics\ChartPalette::color('late')],
            ['key' => 'on_leave', 'label' => 'On leave', 'ink' => \App\Analytics\ChartPalette::color('on_leave')],
            ['key' => 'other', 'label' => 'Other', 'ink' => \App\Analytics\ChartPalette::color('other')],
        ];
    @endphp

    <x-page-header title="Workforce analytics" :description="'Every chart on this page uses '.$from.' to '.$to.' · '.$scopeLabel.'. Counts come from stored records. A zero is a stored zero, not an estimate.'" eyebrow="Insights">
        <x-button :href="route('org.analytics.export', request()->query())" variant="secondary" size="sm"><x-icon name="document-check" class="size-4" /> Export CSV</x-button>
    </x-page-header>

    <x-filter-bar :action="route('org.analytics.index')" :reset="route('org.analytics.index')">
        <label class="flex flex-col gap-1 text-xs font-medium text-fg-muted"><span>From</span><input type="date" name="from" value="{{ $from }}" class="min-h-11 rounded-lg border border-edge bg-surface px-2.5 text-sm text-fg focus-visible:outline-2"></label>
        <label class="flex flex-col gap-1 text-xs font-medium text-fg-muted"><span>To</span><input type="date" name="to" value="{{ $to }}" class="min-h-11 rounded-lg border border-edge bg-surface px-2.5 text-sm text-fg focus-visible:outline-2"></label>
        @if (count($departments) > 0)
            <label class="flex flex-col gap-1 text-xs font-medium text-fg-muted"><span>Department</span>
                <select name="department" class="min-h-11 rounded-lg border border-edge bg-surface px-2.5 text-sm text-fg focus-visible:outline-2">
                    <option value="">All in scope</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department['uuid'] }}" @selected($selectedDepartment === $department['uuid'])>{{ $department['name'] }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <label class="flex flex-col gap-1 text-xs font-medium text-fg-muted"><span>Location</span>
            <select name="location" class="min-h-11 rounded-lg border border-edge bg-surface px-2.5 text-sm text-fg focus-visible:outline-2">
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

    <section aria-labelledby="today-heading">
        <h2 id="today-heading" class="mb-3 text-base font-semibold text-fg">Today</h2>
        <div class="inset">
        <div class="grid grid-cols-2 gap-x-6 gap-y-5 lg:grid-cols-4">
            <x-stat label="Present today" :value="number_format($today['present'])" :hint="$presentShare !== null ? $presentShare.'% of '.number_format($today['active_staff']).' active staff' : 'No active staff'" icon="check" tone="success" />
            <x-stat label="Late today" :value="number_format($today['late'])" hint="Checked in after the cutoff" icon="clock" tone="warning" />
            <x-stat label="Absent today" :value="number_format($today['absent'])" :hint="($today['absent_basis'] ?? 'derived') === 'derived' ? 'Active people without a covering record' : 'Stored absent status at this location'" icon="users" tone="danger" />
            <x-stat label="On leave today" :value="number_format($today['on_leave'])" :hint="number_format($leavePending).' awaiting a decision'" icon="calendar" tone="accent" />
        </div>
        </div>
    </section>

    <x-insight-list :insights="$insights" />

    <div class="grid min-w-0 gap-8 lg:grid-cols-2">
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

    <x-card :title="$trendConclusion" :description="$dayCount.' '.($dayCount === 1 ? 'day' : 'days').' from '.$from.' to '.$to.'. Each column is one day of stored records. A flat mark is a day with no record.'">

        @if (! $hasTrend)
            <x-empty-state title="No attendance in this range" description="Check-ins, leave, and corrections for these dates will stack on the chart." />
        @else
            <x-charts.stacked :rows="$trend" :series="$trendSeries" caption="Daily attendance records from {{ $from }} to {{ $to }}" />
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
            @if ($departmentRows === [])
                <p class="text-sm text-fg-muted">No department totals in this range.</p>
            @else
                <ul class="space-y-4">
                    @foreach ($departmentRows as $row)
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
            <p class="mt-3 text-xs text-fg-subtle">{{ number_format($leavePending) }} waiting in this scope, including requests that start outside this range.</p>
            <p class="mt-2 text-sm text-fg">
                @if ($leaveSummary['utilisation'] === null)
                    No open leave-year entitlement to compare.
                @else
                    {{ $leaveSummary['utilisation'] }}% of open entitlement used ({{ number_format($leaveSummary['used_days']) }} of {{ number_format($leaveSummary['entitled_days']) }} days).
                @endif
            </p>
            <p class="mt-1 text-xs text-fg-subtle">{{ number_format($leaveSummary['upcoming_approved']) }} approved requests overlap the next 14 days.</p>
        </x-card>
        <x-card title="Schedule adherence" description="Published assignments in this range compared with a present or late attendance record on the same person and date.">
            @php
                $scheduled = (int) $adherence['scheduled'];
                $attended = (int) $adherence['attended'];
                $share = $scheduled > 0 ? (int) round($attended / $scheduled * 100) : null;
            @endphp
            <p class="text-3xl font-semibold tabular-nums text-fg">{{ $share === null ? '—' : $share.'%' }}</p>
            <p class="mt-1 text-sm text-fg-muted">{{ number_format($attended) }} attended of {{ number_format($scheduled) }} scheduled periods. {{ number_format($schedule['unassigned_shifts']) }} published shifts have no assignment in the next 7 days.</p>
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

    <x-card title="Notifications" description="Delivery is whether a channel accepted the notice. Read is whether a person opened it. The two are not the same.">
        @if ($notifications['deliveries'] === null)
            <p class="text-sm text-fg-muted">Organisation-wide delivery totals are shown to organisation administrators. You have {{ number_format($notifications['unread']) }} unread {{ $notifications['unread'] === 1 ? 'notice' : 'notices' }}.</p>
        @else
            <dl class="grid gap-4 sm:grid-cols-4">
                <div><dt class="text-xs text-fg-muted">Unread</dt><dd class="text-lg font-semibold tabular-nums">{{ number_format($notifications['unread']) }}</dd></div>
                <div><dt class="text-xs text-fg-muted">Read</dt><dd class="text-lg font-semibold tabular-nums">{{ number_format((int) $notifications['read']) }}</dd></div>
                @foreach ($notifications['deliveries'] as $status => $total)
                    <div><dt class="text-xs text-fg-muted">Delivery · {{ str_replace('_', ' ', (string) $status) }}</dt><dd class="text-lg font-semibold tabular-nums">{{ number_format($total) }}</dd></div>
                @endforeach
            </dl>
            @if ($notifications['deliveries'] === [])
                <p class="mt-3 text-sm text-fg-muted">No delivery claims are stored for this organisation.</p>
            @endif
        @endif
    </x-card>
</x-layouts.app>
