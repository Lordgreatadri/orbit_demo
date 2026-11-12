@php
    $timezone = $organization->timezone ?: 'UTC';
    $today = $personal['today'];
    $todayStatus = match (true) {
        $today['late'] > 0 => ['Late', 'warning', 'clock', 'Your check-in today was after the cutoff.'],
        $today['incomplete'] > 0 => ['Incomplete', 'warning', 'alert', 'Your record today is missing a step. Open My attendance to finish it.'],
        $today['present'] > 0 => ['Present', 'success', 'check-circle', 'Your attendance is recorded for today.'],
        $today['on_leave'] > 0 => ['On leave', 'accent', 'calendar', 'Approved leave covers today.'],
        $today['excused'] > 0 => ['Excused', 'info', 'info', 'Today is recorded as excused.'],
        default => ['No record yet', 'neutral', 'clock', 'Nothing is stored for you today. Check in when you arrive.'],
    };
    [$statusLabel, $statusTone, $statusIcon, $statusLine] = $todayStatus;
    $openRequests = (int) $personal['open_requests'];
    $next = $personal['next'];
@endphp

<section aria-labelledby="personal-heading" class="space-y-8">
    <h2 id="personal-heading" class="sr-only">Your attendance and leave</h2>

    @if (! $personal['linked'])
        <x-alert type="info" title="No person record is linked to this account">
            An administrator links your account to the staff register before attendance and leave can be shown here.
        </x-alert>
    @endif

    {{-- Today and what comes next --}}
    <div class="briefing grid overflow-hidden lg:grid-cols-12">
        <div class="flex flex-col gap-4 p-5 sm:p-6 lg:col-span-7 lg:border-r lg:border-edge">
            <p class="text-xs font-semibold text-primary">Today · {{ now($timezone)->format('l j M') }}</p>
            <div class="flex items-center gap-4">
                <span class="grid size-12 shrink-0 place-items-center rounded-xl tone-{{ $statusTone }}" aria-hidden="true"><x-icon :name="$statusIcon" class="size-6" /></span>
                <div class="min-w-0">
                    <p class="text-[1.5rem] leading-tight font-semibold tracking-tight text-fg">{{ $statusLabel }}</p>
                    <p class="mt-0.5 text-sm text-fg-muted">{{ $statusLine }}</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('attendance.record')
                    <x-button :href="route('org.attendance.mine')" size="sm"><x-icon name="qr" class="size-4" /> {{ $today['present'] + $today['late'] > 0 ? 'View my attendance' : 'Check in on My attendance' }}</x-button>
                @endcan
                @can('leave.request')
                    <x-button :href="route('org.leave.index')" variant="secondary" size="sm"><x-icon name="document-check" class="size-4" /> Request leave</x-button>
                @endcan
            </div>
        </div>
        <div class="border-t border-edge p-5 sm:p-6 lg:col-span-5 lg:border-t-0">
            <p class="text-xs font-medium text-fg-muted">Next scheduled period</p>
            @if ($next === null)
                <p class="mt-3 text-sm text-fg-muted">Nothing is published for you from today onward.</p>
            @else
                <div class="mt-3 flex items-center gap-4">
                    <span class="grid w-14 shrink-0 place-items-center rounded-lg border border-edge bg-surface py-1.5 text-center leading-none shadow-panel" aria-hidden="true">
                        <span class="text-[0.6875rem] font-semibold text-primary uppercase">{{ $next->work_date->format('M') }}</span>
                        <span class="mt-0.5 text-xl font-semibold tabular-nums text-fg">{{ $next->work_date->format('j') }}</span>
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-base font-semibold text-fg">{{ $next->shift?->name ?? 'Shift' }}</p>
                        <p class="mt-0.5 text-sm text-fg-muted">
                            {{ $next->work_date->format('l') }}
                            · {{ \Illuminate\Support\Str::of((string) $next->shift?->starts_at)->substr(0, 5) }}–{{ \Illuminate\Support\Str::of((string) $next->shift?->ends_at)->substr(0, 5) }}
                        </p>
                        @if ($next->arrangement)
                            <p class="mt-1"><x-badge tone="info">{{ $next->arrangement->name }}</x-badge></p>
                        @endif
                    </div>
                </div>
            @endif
            <p class="mt-3 text-[0.6875rem] text-fg-subtle">Published assignments only.</p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3">
        <x-stat label="Unread notices" :value="number_format($unreadNotifications)" hint="Your inbox only" icon="bell" tone="info" :href="route('org.notifications.index')" />
        <x-stat label="Open requests" :value="number_format($openRequests)" hint="Leave you have submitted" icon="document-check" tone="warning" :href="route('org.leave.index')" />
        @php
            $headline = collect($personal['balances'])->first(fn ($row) => str_contains(strtolower($row['name']), 'annual')) ?? ($personal['balances'][0] ?? null);
            $headlineUnlimited = $headline !== null && ! $headline['requires_balance'];
        @endphp
        <x-stat class="col-span-2 lg:col-span-1" :label="$headline ? ($headlineUnlimited ? $headline['name'] : $headline['name'].' available') : 'Leave available'" :value="$headline ? ($headlineUnlimited ? 'No day limit' : number_format($headline['available'])) : '—'" :hint="$headline ? ($headlineUnlimited ? 'This leave type has no day limit' : 'Days in the open leave year') : 'No active leave type'" icon="calendar" tone="accent" :href="route('org.leave.index')" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2" icon="clock" title="Recent attendance" description="Your latest records. A day with two periods shows two rows." decision="whether anything needs correcting while it is recent">
            <x-slot:actions><a href="{{ route('org.attendance.mine') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-primary hover:underline">My attendance</a></x-slot:actions>
            @if ($personal['history']->isEmpty())
                <div class="flex items-center gap-3 rounded-lg bg-surface-muted px-4 py-5">
                    <x-icon name="qr" class="size-5 shrink-0 text-fg-subtle" />
                    <p class="text-sm text-fg-muted">No attendance recorded yet. A check-in on My attendance will appear here.</p>
                </div>
            @else
                <ol class="-my-1 divide-y divide-edge">
                    @foreach ($personal['history'] as $record)
                        <li class="flex min-h-11 items-center gap-3 py-2.5 text-sm">
                            <span class="grid w-11 shrink-0 place-items-center rounded-md border border-edge bg-surface-muted py-1 text-center leading-none" aria-hidden="true">
                                <span class="text-[0.625rem] font-medium text-fg-subtle uppercase">{{ $record->work_date->format('M') }}</span>
                                <span class="text-sm font-semibold tabular-nums text-fg">{{ $record->work_date->format('j') }}</span>
                            </span>
                            <div class="min-w-0 flex-1">
                                <time class="font-medium text-fg" datetime="{{ $record->work_date->toDateString() }}">{{ $record->work_date->format('l j M Y') }}</time>
                                <p class="text-xs text-fg-subtle">
                                    {{ $record->check_in_at ? 'In '.$record->check_in_at->timezone($timezone)->format('H:i') : 'No check-in time' }}
                                    @if ($record->check_out_at) · Out {{ $record->check_out_at->timezone($timezone)->format('H:i') }} @endif
                                </p>
                            </div>
                            <x-badge :tone="$record->status->tone()">{{ $record->status->label() }}</x-badge>
                        </li>
                    @endforeach
                </ol>
            @endif
        </x-card>

        <x-card icon="calendar" title="Leave balances" description="Open leave year. Matches the leave page." decision="whether you can plan time away">
            @if ($personal['balances'] === [])
                <p class="text-sm text-fg-muted">No active leave type is configured.</p>
            @else
                <ul class="grid gap-2">
                    @foreach ($personal['balances'] as $balance)
                        <li class="flex items-center justify-between gap-3 rounded-lg border border-edge px-3.5 py-2.5">
                            <span class="text-sm font-medium text-fg">{{ $balance['name'] }}</span>
                            @if ($balance['requires_balance'])
                                <span class="text-right leading-none"><span class="text-lg font-semibold tabular-nums text-fg">{{ number_format($balance['available']) }}</span> <span class="text-xs text-fg-subtle">days available</span></span>
                            @else
                                <span class="text-sm font-semibold text-fg">No day limit</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-card icon="document-check" title="Your leave requests" description="Your five most recent requests.">
            <x-slot:actions><a href="{{ route('org.leave.index') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-primary hover:underline">Leave</a></x-slot:actions>
            @if ($personal['requests']->isEmpty())
                <p class="text-sm text-fg-muted">You have not submitted a request.</p>
            @else
                <ul class="-my-1 divide-y divide-edge">
                    @foreach ($personal['requests'] as $leaveRequest)
                        <li class="flex items-center justify-between gap-3 py-2.5 text-sm">
                            <div class="min-w-0">
                                <p class="font-medium text-fg">{{ $leaveRequest->leaveType?->name ?? 'Leave' }}</p>
                                <p class="mt-0.5 text-xs text-fg-muted">{{ $leaveRequest->starts_on->format('j M') }} – {{ $leaveRequest->ends_on->format('j M Y') }}</p>
                            </div>
                            <x-badge :tone="$leaveRequest->status->tone()">{{ $leaveRequest->status->label() }}</x-badge>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        @include('tenant.dashboard.notices')
    </div>
</section>
