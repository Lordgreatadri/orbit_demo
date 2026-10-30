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

            @if ($demoMetrics)
                <x-card title="Sample attendance metrics" description="Illustrative demo data for this organisation. Live attendance arrives with the attendance module.">
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
            {{-- Setup checklist --}}
            @php $doneCount = collect($setupSteps)->where('done', true)->count(); @endphp
            <x-card title="Setup checklist" :description="$doneCount.' of '.count($setupSteps).' complete'">
                <ol class="space-y-3">
                    @foreach ($setupSteps as $step)
                        <li class="flex items-start gap-3">
                            <span aria-hidden="true" @class(['mt-0.5 grid size-5 shrink-0 place-items-center rounded-full border text-[0.65rem] font-bold', 'border-success bg-success text-white' => $step['done'], 'border-edge-strong text-fg-subtle' => ! $step['done']])>
                                @if ($step['done'])<x-icon name="check" class="size-3" />@else{{ $loop->iteration }}@endif
                            </span>
                            <div class="min-w-0 text-sm">
                                <a href="{{ $step['route'] }}" @class(['font-medium hover:underline', 'text-fg-muted line-through decoration-fg-subtle' => $step['done'], 'text-fg' => ! $step['done']])>{{ $step['label'] }}</a>
                                <p class="text-xs text-fg-subtle">{{ $step['hint'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </x-card>

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
