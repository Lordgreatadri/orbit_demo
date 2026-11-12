<x-layouts.app title="Workforce overview" wide>
    @php
        $coverage = $totals['active_staff'] > 0 ? (int) round($totals['covered'] / $totals['active_staff'] * 100) : null;
        $reportingShare = $totals['organizations'] > 0 ? (int) round($totals['reporting'] / $totals['organizations'] * 100) : null;
        $silent = $totals['organizations'] - $totals['reporting'];
    @endphp

    <x-page-header title="Workforce overview" description="Counts for today in each organisation's own time zone. Figures are distinct people with a stored status. Member names are not shown here." eyebrow="Product modules">
        @can('platform.organizations.view')
            <x-button :href="route('admin.organizations.index')" variant="secondary" size="sm"><x-icon name="building" class="size-4" /> Organisations</x-button>
        @endcan
        <x-button :href="route('admin.statistics')" size="sm"><x-icon name="chart" class="size-4" /> Platform statistics</x-button>
    </x-page-header>

    <section aria-labelledby="workforce-briefing" class="briefing overflow-hidden">
        <div class="grid lg:grid-cols-12">
            <div class="p-5 sm:p-6 lg:col-span-5 lg:border-r lg:border-edge">
                <p class="inline-flex items-center gap-2 text-xs font-semibold text-primary">
                    <span class="grid size-6 place-items-center rounded-md bg-primary text-primary-fg" aria-hidden="true"><x-icon name="sparkles" class="size-3.5" /></span>
                    Today across organisations
                </p>
                <h2 id="workforce-briefing" class="mt-3 text-[1.1875rem] leading-snug font-semibold tracking-tight text-balance text-fg sm:text-[1.3125rem]">
                    @if ($totals['organizations'] === 0)
                        No organisation is registered yet.
                    @else
                        {{ number_format($totals['reporting']) }} of {{ number_format($totals['organizations']) }} {{ $totals['organizations'] === 1 ? 'organisation has' : 'organisations have' }} recorded attendance today.
                    @endif
                </h2>
                <p class="mt-2 text-sm text-fg-muted">
                    @if ($silent > 0)
                        {{ number_format($silent) }} {{ $silent === 1 ? 'has' : 'have' }} no covering record yet. That can mean an early local hour, a non-working day, or a workspace that has not started using attendance.
                    @else
                        Every organisation has at least one covering record today.
                    @endif
                </p>
            </div>
            <div class="grid gap-5 border-t border-edge p-5 sm:grid-cols-2 sm:p-6 lg:col-span-7 lg:border-t-0">
                <div>
                    <div class="flex items-baseline justify-between gap-3 text-xs">
                        <span class="font-medium text-fg-muted">Organisations reporting</span>
                        <span class="tabular-nums text-fg-muted"><span class="font-semibold text-fg">{{ $reportingShare === null ? '—' : $reportingShare.'%' }}</span></span>
                    </div>
                    <div class="mt-2 meter h-2"><span class="bg-primary" style="width: {{ $reportingShare ?? 0 }}%"></span></div>
                    <p class="mt-1.5 text-[0.6875rem] text-fg-subtle">At least one present, late, on leave, incomplete, or excused record today.</p>
                </div>
                <div>
                    <div class="flex items-baseline justify-between gap-3 text-xs">
                        <span class="font-medium text-fg-muted">Active people covered</span>
                        <span class="tabular-nums text-fg-muted"><span class="font-semibold text-fg">{{ number_format($totals['covered']) }}</span> of {{ number_format($totals['active_staff']) }}{{ $coverage !== null ? ' · '.$coverage.'%' : '' }}</span>
                    </div>
                    <div class="mt-2 meter h-2"><span class="bg-success" style="width: {{ min(100, $coverage ?? 0) }}%"></span></div>
                    <p class="mt-1.5 text-[0.6875rem] text-fg-subtle">Across every organisation, using each one's local date.</p>
                </div>
            </div>
        </div>
    </section>

    <section aria-labelledby="workforce-kpis">
        <h2 id="workforce-kpis" class="sr-only">Today's totals</h2>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 xl:grid-cols-6">
            <x-stat label="Active people" :value="number_format($totals['active_staff'])" hint="Register status, all organisations" icon="users" tone="primary" />
            <x-stat label="Present today" :value="number_format($totals['present'])" hint="Distinct people, stored status" icon="check-circle" tone="success" />
            <x-stat label="Late today" :value="number_format($totals['late'])" hint="Distinct people, stored status" icon="clock" tone="warning" :emphasis="$totals['late'] > 0 ? 'warning' : null" />
            <x-stat label="On leave today" :value="number_format($totals['on_leave'])" hint="Distinct people, stored status" icon="calendar" tone="accent" />
            <x-stat label="Open field work" :value="number_format($totals['open_field'])" hint="Field activities not yet ended" icon="map-pin" tone="info" />
            <x-stat label="Leave pending" :value="number_format($totals['pending_leave'])" hint="Requests waiting for a decision" icon="document-check" tone="warning" />
        </div>
    </section>

    <section aria-labelledby="register-heading" class="space-y-3">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 id="register-heading" class="section-title">By organisation</h2>
                <p class="mt-0.5 text-sm text-fg-muted">The bar is today's covered share of active people: present, late, then on leave and other covering statuses.</p>
            </div>
            <form method="GET" action="{{ route('admin.workforce.overview') }}" role="search" class="flex items-end gap-2">
                <x-form.inline-search name="q" label="Find an organisation" placeholder="Organisation name" />
                <x-button type="submit" variant="secondary" size="sm">Search</x-button>
                @if ($search !== '')
                    <x-button :href="route('admin.workforce.overview')" variant="ghost" size="sm">Reset</x-button>
                @endif
            </form>
        </div>

        @if ($organizations->isEmpty())
            <x-empty-state title="No organisations match" :description="$search !== '' ? 'Try a shorter name.' : 'Organisations appear here once they register.'" />
        @else
            <x-data-table :paginator="$organizations" caption="Organisation workforce counts for today">
                <thead>
                    <tr>
                        <th scope="col">Organisation</th>
                        <th scope="col" class="text-right">Active</th>
                        <th scope="col" class="min-w-[14rem]">Covered today</th>
                        <th scope="col" class="hidden text-right md:table-cell">Present · late</th>
                        <th scope="col" class="hidden text-right lg:table-cell">Open field</th>
                        <th scope="col" class="text-right">Leave pending</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($organizations as $organization)
                        @php
                            $id = $organization->getKey();
                            $row = $attendance->get($id, []);
                            $bucket = $coverageBuckets->get($id, ['present' => 0, 'late' => 0, 'other' => 0, 'covered' => 0]);
                            $active = (int) ($activeStaff[$id] ?? 0);
                            $present = (int) ($row['present'] ?? 0);
                            $late = (int) ($row['late'] ?? 0);
                            $covered = (int) $bucket['covered'];
                            $barPresent = (int) $bucket['present'];
                            $barLate = (int) $bucket['late'];
                            $barOther = (int) $bucket['other'];
                            $denominator = max(1, $active, $covered);
                            $share = $active > 0 ? (int) round(min($covered, $active) / $active * 100) : null;
                            $pending = (int) ($pendingLeave[$id] ?? 0);
                            $monogram = \App\Support\Monogram::for($organization->name);
                        @endphp
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <span aria-hidden="true" class="grid size-9 shrink-0 place-items-center rounded-lg bg-surface-muted text-xs font-semibold text-fg-muted">{{ $monogram ?: 'O' }}</span>
                                    <div class="min-w-0">
                                        @can('platform.organizations.view')
                                            <a href="{{ route('admin.organizations.show', $organization) }}" class="font-medium text-fg hover:text-primary hover:underline">{{ $organization->name }}</a>
                                        @else
                                            <span class="font-medium text-fg">{{ $organization->name }}</span>
                                        @endcan
                                        <p class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs text-fg-subtle">
                                            <span>{{ $organization->type->label() }}</span>
                                            <span aria-hidden="true">·</span>
                                            <span>{{ \Illuminate\Support\Carbon::parse($todayById[$id] ?? now())->format('D j M') }}, {{ $organization->timezone ?: 'UTC' }}</span>
                                            @if (! $organization->isActive())
                                                <x-badge :tone="$organization->status->tone()" class="!py-0">{{ $organization->status->label() }}</x-badge>
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="text-right font-semibold tabular-nums text-fg">{{ number_format($active) }}</td>
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="flex h-2 min-w-0 flex-1 gap-px overflow-hidden rounded-full bg-surface-muted" role="presentation" title="{{ $barPresent }} present, {{ $barLate }} late, {{ $barOther }} on leave or other covering status, of {{ $active }} active">
                                        @if ($barPresent > 0)<span class="h-full bg-primary" style="width: {{ round($barPresent / $denominator * 100, 2) }}%"></span>@endif
                                        @if ($barLate > 0)<span class="h-full bg-warning" style="width: {{ round($barLate / $denominator * 100, 2) }}%"></span>@endif
                                        @if ($barOther > 0)<span class="h-full bg-accent" style="width: {{ round($barOther / $denominator * 100, 2) }}%"></span>@endif
                                    </div>
                                    <span @class(['w-11 shrink-0 text-right text-xs font-semibold tabular-nums', 'text-fg' => $share !== null && $share > 0, 'text-fg-subtle' => $share === null || $share === 0])>{{ $share === null ? '—' : $share.'%' }}</span>
                                </div>
                                <span class="sr-only">{{ $covered }} covered of {{ $active }} active</span>
                            </td>
                            <td class="hidden text-right tabular-nums md:table-cell"><span class="text-fg">{{ number_format($present) }}</span> <span class="text-fg-subtle">·</span> <span @class(['text-warning font-medium' => $late > 0, 'text-fg' => $late === 0])>{{ number_format($late) }}</span></td>
                            <td class="hidden text-right tabular-nums lg:table-cell">{{ number_format((int) ($openField[$id] ?? 0)) }}</td>
                            <td class="text-right">
                                @if ($pending > 0)
                                    <x-badge tone="warning">{{ number_format($pending) }}</x-badge>
                                @else
                                    <span class="tabular-nums text-fg-subtle">0</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-data-table>
            <ul class="flex flex-wrap gap-x-5 gap-y-1.5 text-xs text-fg-muted">
                <li class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-primary" aria-hidden="true"></span> Present</li>
                <li class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-warning" aria-hidden="true"></span> Late</li>
                <li class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-accent" aria-hidden="true"></span> On leave, incomplete, or excused</li>
                <li class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-surface-muted ring-1 ring-edge" aria-hidden="true"></span> No covering record</li>
            </ul>
        @endif
    </section>
</x-layouts.app>
