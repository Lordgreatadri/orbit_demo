<section aria-labelledby="personal-heading" class="space-y-10">
    <h2 id="personal-heading" class="sr-only">Your attendance and leave</h2>

    @if (! $personal['linked'])
        <x-alert type="info" title="No person record is linked to this account">
            An administrator links your account to the staff register before attendance and leave can be shown here.
        </x-alert>
    @endif

    <div class="inset">
        <div class="grid grid-cols-2 gap-x-6 gap-y-5 lg:grid-cols-4">
            <x-stat label="Today" :value="$personal['today']['present'] + $personal['today']['late'] > 0 ? 'Recorded' : ($personal['today']['on_leave'] > 0 ? 'On leave' : 'No record')" hint="Your attendance for today" :href="route('org.attendance.mine')" />
            <x-stat label="Status" :value="$personal['today']['late'] > 0 ? 'Late' : ($personal['today']['incomplete'] > 0 ? 'Incomplete' : ($personal['today']['present'] > 0 ? 'Present' : '—'))" hint="From your attendance record" />
            <x-stat label="Unread notices" :value="number_format($unreadNotifications)" hint="Your inbox only" :href="route('org.notifications.index')" />
            <x-stat label="Open requests" :value="number_format($personal['requests']->filter(fn ($row) => $row->status === \App\Enums\LeaveStatus::Submitted)->count())" hint="Leave you have submitted" :href="route('org.leave.index')" />
        </div>
    </div>

    <section aria-labelledby="next-heading">
        <h2 id="next-heading" class="text-base font-semibold text-fg">Next scheduled period</h2>
        <p class="mt-1 text-sm text-fg-muted">Published assignments only.</p>
        @if ($personal['next'] === null)
            <p class="mt-3 text-sm text-fg-muted">Nothing is published for you from today onward.</p>
        @else
            <p class="mt-3 text-lg font-semibold text-fg">{{ $personal['next']->shift?->name ?? 'Shift' }}</p>
            <p class="mt-1 text-sm text-fg-muted">
                {{ $personal['next']->work_date->format('l j M Y') }}
                · {{ \Illuminate\Support\Str::of((string) $personal['next']->shift?->starts_at)->substr(0, 5) }}–{{ \Illuminate\Support\Str::of((string) $personal['next']->shift?->ends_at)->substr(0, 5) }}
                @if ($personal['next']->arrangement)
                    · {{ $personal['next']->arrangement->name }}
                @endif
            </p>
        @endif
    </section>

    <section aria-labelledby="history-heading">
        <h2 id="history-heading" class="text-base font-semibold text-fg">Recent attendance</h2>
        <p class="mt-1 text-sm text-fg-muted">Your latest records. Each row is one attendance record, not one calendar day when a day has more than one period.</p>
        @if ($personal['history']->isEmpty())
            <p class="mt-3 text-sm text-fg-muted">No attendance recorded yet. A check-in on My attendance will appear here.</p>
        @else
            <ul class="mt-3 divide-y divide-edge">
                @foreach ($personal['history'] as $record)
                    <li class="flex min-h-11 items-center justify-between gap-3 py-3 text-sm">
                        <time datetime="{{ $record->work_date->toDateString() }}">{{ $record->work_date->format('D j M Y') }}</time>
                        <x-badge :tone="$record->status->tone()">{{ $record->status->label() }}</x-badge>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section aria-labelledby="balances-heading">
        <h2 id="balances-heading" class="text-base font-semibold text-fg">Leave balances</h2>
        <p class="mt-1 text-sm text-fg-muted">Open leave year. Available days match the leave page: a stored balance when one exists, otherwise the current entitlement.</p>
        @if ($personal['balances'] === [])
            <p class="mt-3 text-sm text-fg-muted">No active leave type is configured.</p>
        @else
            <ul class="mt-3 divide-y divide-edge">
                @foreach ($personal['balances'] as $balance)
                    <li class="flex min-h-11 items-center justify-between gap-3 py-3 text-sm">
                        <span class="text-fg">{{ $balance['name'] }}</span>
                        <span class="tabular-nums text-fg-muted">{{ number_format($balance['available']) }} available</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section aria-labelledby="requests-heading">
        <h2 id="requests-heading" class="text-base font-semibold text-fg">Your leave requests</h2>
        @if ($personal['requests']->isEmpty())
            <p class="mt-3 text-sm text-fg-muted">You have not submitted a request.</p>
        @else
            <ul class="mt-3 divide-y divide-edge">
                @foreach ($personal['requests'] as $leaveRequest)
                    <li class="py-3 text-sm">
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-medium text-fg">{{ $leaveRequest->leaveType?->name ?? 'Leave' }}</p>
                            <x-badge :tone="$leaveRequest->status->tone()">{{ $leaveRequest->status->label() }}</x-badge>
                        </div>
                        <p class="mt-0.5 text-xs text-fg-muted">{{ $leaveRequest->starts_on->format('j M') }} – {{ $leaveRequest->ends_on->format('j M') }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @include('tenant.dashboard.notices')
</section>
