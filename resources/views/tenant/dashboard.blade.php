<x-layouts.app title="Dashboard">
    <x-page-header :title="'Good '.(now($organization->timezone)->hour < 12 ? 'morning' : (now($organization->timezone)->hour < 17 ? 'afternoon' : 'evening')).', '.$user->first_name" :description="$organization->name.' · '.$organization->type->label().' · '.str_replace('_', ' ', $organization->timezone)">
        @can('create', \App\Models\StaffMember::class)
            <x-button :href="route('org.staff.create')" variant="secondary" size="sm"><x-icon name="user-plus" class="size-4" /> Add staff</x-button>
        @endcan
        @can('create', \App\Models\Invitation::class)
            <x-button :href="route('org.users.index').'#invite'" size="sm"><x-icon name="plus" class="size-4" /> Invite user</x-button>
        @endcan
    </x-page-header>

    @if ($organization->status === \App\Enums\OrganizationStatus::PendingVerification)
        <x-alert type="warning" title="Organisation pending verification">
            Your organisation is operational while verification is completed. You can set up departments, locations, staff, and user accounts now.
        </x-alert>
    @endif
    {{-- KPIs --}}
    <section aria-labelledby="kpi-heading">
        <h2 id="kpi-heading" class="sr-only">Key figures</h2>
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-stat label="Active staff" :value="number_format($counts['staff_active'])" :hint="$counts['staff_total'].' on the register'" icon="users" :href="route('org.staff.index', ['status' => 'active'])" />
            <x-stat label="On leave" :value="number_format($counts['staff_on_leave'])" hint="Status: on leave" icon="calendar" tone="warning" :href="route('org.staff.index', ['status' => 'on_leave'])" />
            <x-stat label="User accounts" :value="number_format($counts['users'])" :hint="$counts['users_suspended'] > 0 ? $counts['users_suspended'].' suspended' : 'All active'" icon="key" tone="accent" :href="route('org.users.index')" />
            <x-stat label="Pending invitations" :value="number_format($counts['pending_invitations'])" hint="Awaiting acceptance" icon="mail" tone="neutral" :href="route('org.users.index').'#invitations'" />
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Workforce composition --}}
            <x-card title="Workforce by work type" description="Active staff records only.">
                @php $maxWork = max(1, $workTypes->max('value')); @endphp
                <ul class="space-y-3">
                    @foreach ($workTypes as $row)
                        <li>
                            <div class="flex items-center justify-between text-sm">
                                <span class="font-medium text-fg">{{ $row['label'] }}</span>
                                <span class="text-fg-muted tabular-nums">{{ number_format($row['value']) }}</span>
                            </div>
                            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-surface-muted" role="presentation">
                                <div class="h-full rounded-full bg-primary transition-[width] duration-500" style="width: {{ $counts['staff_active'] > 0 ? round($row['value'] / $maxWork * 100) : 0 }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
                @if ($counts['staff_active'] === 0)
                    <p class="mt-4 text-sm text-fg-subtle">No active staff yet. <a href="{{ route('org.staff.index') }}" class="text-primary hover:underline">Open the staff register</a> to add the first record.</p>
                @endif
            </x-card>

            @if ($attendanceToday)
                <x-card title="Today's attendance" description="Counted from attendance records for today in this organisation's time zone.">
                    <x-slot:actions><x-button :href="route('org.attendance.index')" variant="ghost" size="sm">Records</x-button></x-slot:actions>
                    <dl class="grid gap-4 sm:grid-cols-4">
                        <div><dt class="text-xs text-fg-muted">Present</dt><dd class="text-lg font-semibold tabular-nums">{{ number_format($attendanceToday['present']) }}</dd></div>
                        <div><dt class="text-xs text-fg-muted">Late</dt><dd class="text-lg font-semibold tabular-nums">{{ number_format($attendanceToday['late']) }}</dd></div>
                        <div><dt class="text-xs text-fg-muted">On leave</dt><dd class="text-lg font-semibold tabular-nums">{{ number_format($attendanceToday['on_leave']) }}</dd></div>
                        <div><dt class="text-xs text-fg-muted">Absent</dt><dd class="text-lg font-semibold tabular-nums">{{ number_format($attendanceToday['absent']) }}</dd></div>
                    </dl>
                </x-card>
            @endif

            @if ($demoMetrics)
                <x-card title="Sample attendance metrics" description="Illustrative demo data for this organisation. Today's attendance above is counted from stored records.">
                    <x-slot:actions><x-badge tone="accent">Demo data</x-badge></x-slot:actions>
                    <dl class="grid gap-4 sm:grid-cols-3">
                        @foreach ($demoMetrics as $key => $value)
                            <div class="rounded-xl border border-edge bg-surface p-3 shadow-float">
                                <dt class="text-xs font-medium text-fg-muted">{{ ucfirst(str_replace('_', ' ', $key)) }}</dt>
                                <dd class="mt-1 text-lg font-semibold text-fg tabular-nums">{{ is_numeric($value) ? number_format((float) $value, is_int($value) ? 0 : 1) : $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </x-card>
            @endif

            {{-- Recent activity --}}
            @if ($user->can('audit.view'))
                <x-card title="Recent activity" description="Latest audited changes in your organisation." :padding="false">
                    <x-slot:actions><x-button :href="route('org.audit.index')" variant="ghost" size="sm">Audit trail <x-icon name="arrow-right" class="size-4" /></x-button></x-slot:actions>
                    @if ($recentActivity->isEmpty())
                        <div class="p-5"><x-empty-state title="No activity recorded yet" description="Changes to staff, users, and settings will appear here." /></div>
                    @else
                        <ul class="divide-y divide-edge">
                            @foreach ($recentActivity as $activity)
                                <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 text-sm">
                                    <div class="min-w-0">
                                        <p class="font-medium text-fg">{{ $activity->eventLabel() }}</p>
                                        <p class="truncate text-xs text-fg-muted">{{ $activity->causer?->name ?? 'System' }}</p>
                                    </div>
                                    <time datetime="{{ $activity->created_at->toIso8601String() }}" class="text-xs text-fg-subtle">{{ $activity->created_at->timezone($organization->timezone)->diffForHumans() }}</time>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            @endif
        </div>

        <div class="space-y-6">
            <x-card title="Notifications" :description="$unreadNotifications === 1 ? '1 unread' : $unreadNotifications.' unread'" :padding="false">
                <x-slot:actions><x-button :href="route('org.notifications.index')" variant="ghost" size="sm">View all</x-button></x-slot:actions>
                @if ($recentNotifications->isEmpty())
                    <p class="px-5 py-4 text-sm text-fg-muted">No notifications yet.</p>
                @else
                    <ul class="divide-y divide-edge">
                        @foreach ($recentNotifications as $notification)
                            <li class="px-5 py-3 text-sm">
                                <p class="flex items-center gap-2 font-medium text-fg">
                                    <span class="min-w-0 truncate">{{ $notification->data['title'] ?? 'Notice' }}</span>
                                    @if ($notification->read_at === null)
                                        <x-badge tone="warning">Unread</x-badge>
                                    @else
                                        <x-badge>Read</x-badge>
                                    @endif
                                </p>
                                <p class="mt-0.5 line-clamp-2 text-xs text-fg-muted">{{ $notification->data['body'] ?? '' }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            @if ($user->can('schedules.view'))
                <x-card title="Upcoming assignments" description="Next five, earliest date first." :padding="false">
                    <x-slot:actions><x-button :href="route('org.schedules.index')" variant="ghost" size="sm">Schedules</x-button></x-slot:actions>
                    @if ($upcomingAssignments->isEmpty())
                        <p class="px-5 py-4 text-sm text-fg-muted">Nothing is scheduled from today onward.</p>
                    @else
                        <ul class="divide-y divide-edge">
                            @foreach ($upcomingAssignments as $assignment)
                                @php
                                    $starts = \Illuminate\Support\Str::of((string) $assignment->shift?->starts_at)->substr(0, 5);
                                    $ends = \Illuminate\Support\Str::of((string) $assignment->shift?->ends_at)->substr(0, 5);
                                    $isToday = $assignment->work_date->toDateString() === now($organization->timezone)->toDateString();
                                @endphp
                                <li class="px-5 py-3 text-sm">
                                    <p class="flex items-center gap-2 font-medium text-fg">
                                        <span class="min-w-0 truncate">{{ $assignment->shift?->name ?? 'Shift' }}</span>
                                        @if ($assignment->shift?->status === \App\Enums\ShiftStatus::Draft)
                                            <x-badge>Draft</x-badge>
                                        @endif
                                    </p>
                                    <p class="mt-0.5 text-xs text-fg-muted">
                                        {{ $assignment->staffMember?->name ?? 'Unassigned' }}
                                        · {{ $isToday ? 'Today' : $assignment->work_date->format('D j M') }}
                                        · {{ $starts }}–{{ $ends }}
                                        @if ($assignment->arrangement)
                                            · {{ $assignment->arrangement->name }}
                                        @endif
                                    </p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            @endif

            @if ($user->can('leave.approve'))
                <x-card title="Leave awaiting decision" description="Soonest start date first, up to five." :padding="false">
                    <x-slot:actions><x-button :href="route('org.leave.index')" variant="ghost" size="sm">Leave</x-button></x-slot:actions>
                    @if ($pendingLeave->isEmpty())
                        <p class="px-5 py-4 text-sm text-fg-muted">No requests are waiting.</p>
                    @else
                        <ul class="divide-y divide-edge">
                            @foreach ($pendingLeave as $leaveRequest)
                                <li class="px-5 py-3 text-sm">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="min-w-0 font-medium text-fg">{{ $leaveRequest->staffMember?->name ?? 'Staff member' }}</p>
                                        <x-badge :tone="$leaveRequest->status->tone()">{{ $leaveRequest->status->label() }}</x-badge>
                                    </div>
                                    <p class="mt-0.5 text-xs text-fg-muted">{{ $leaveRequest->leaveType?->name ?? 'Leave' }} · {{ $leaveRequest->starts_on->format('j M') }} – {{ $leaveRequest->ends_on->format('j M') }}</p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            @endif

            {{-- Structure --}}
            <x-card title="Structure">
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <a href="{{ route('org.departments.index') }}" class="card-link p-3"><dt class="text-xs text-fg-muted">Departments</dt><dd class="mt-1 text-xl font-semibold text-fg tabular-nums">{{ $counts['departments'] }}</dd></a>
                    <a href="{{ route('org.locations.index') }}" class="card-link p-3"><dt class="text-xs text-fg-muted">Locations</dt><dd class="mt-1 text-xl font-semibold text-fg tabular-nums">{{ $counts['locations'] }}</dd></a>
                </dl>
                @if ($counts['unlinked_staff'] > 0)
                    <p class="mt-4 flex items-start gap-2 text-xs text-fg-muted"><x-icon name="info" class="mt-0.5 size-3.5 shrink-0 text-info" /> {{ $counts['unlinked_staff'] }} active staff {{ $counts['unlinked_staff'] === 1 ? 'record has' : 'records have' }} no user account. They can be linked from the staff record or by inviting them.</p>
                @endif
            </x-card>

            {{-- Sign-ins --}}
            @if ($user->can('audit.logins.view'))
                <x-card title="Sign-ins" :description="$signInsToday !== null ? $signInsToday.' successful today' : null" :padding="false">
                    <x-slot:actions><x-button :href="route('org.audit.logins')" variant="ghost" size="sm">History</x-button></x-slot:actions>
                    @if ($recentSignIns->isEmpty())
                        <p class="p-5 text-sm text-fg-muted">No sign-in events recorded yet.</p>
                    @else
                        <ul class="divide-y divide-edge">
                            @foreach ($recentSignIns as $event)
                                <li class="flex items-center justify-between gap-2 px-5 py-2.5 text-sm">
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-fg">{{ $event->user?->name ?? $event->email }}</p>
                                        <p class="text-xs text-fg-subtle">{{ $event->created_at->timezone($organization->timezone)->format('D j M, H:i') }}</p>
                                    </div>
                                    <x-badge :tone="$event->event->tone()">{{ $event->event->label() }}</x-badge>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            @endif
        </div>
    </div>
</x-layouts.app>
