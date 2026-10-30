<x-layouts.app title="Platform overview">
    <x-page-header title="Platform overview" :description="'Signed in as '.$user->name.'. Every cross-organisation action from this console is written to the audit trail.'" eyebrow="Platform console">
        @can('platform.organizations.view')
            <x-button :href="route('admin.organizations.index')" variant="secondary" size="sm"><x-icon name="building" class="size-4" /> Organisations</x-button>
        @endcan
        @can('createPlatformAccount', \App\Models\User::class)
            <x-button :href="route('admin.users.create')" size="sm"><x-icon name="user-plus" class="size-4" /> Platform account</x-button>
        @endcan
    </x-page-header>

    <section aria-labelledby="platform-kpis">
        <h2 id="platform-kpis" class="sr-only">Key figures</h2>
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-stat label="Organisations" :value="number_format($counts['organizations'])" :hint="$counts['organizations_active'].' active · '.$counts['organizations_pending'].' pending'" icon="building" :href="$user->can('platform.organizations.view') ? route('admin.organizations.index') : null" />
            <x-stat label="New in 30 days" :value="number_format($counts['new_organizations_30d'])" hint="Organisations registered" icon="sparkles" tone="accent" />
            <x-stat label="Tenant accounts" :value="number_format($counts['users_tenant'])" :hint="$counts['users_platform'].' platform accounts'" icon="users" tone="success" :href="$user->can('platform.users.view') ? route('admin.users.index') : null" />
            <x-stat label="Staff records" :value="number_format($counts['staff_records'])" hint="Across all organisations" icon="id-card" tone="neutral" />
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @can('platform.organizations.view')
                <x-card title="Recently registered organisations" :padding="false">
                    <x-slot:actions><x-button :href="route('admin.organizations.index')" variant="ghost" size="sm">All organisations <x-icon name="arrow-right" class="size-4" /></x-button></x-slot:actions>
                    @if ($recentOrganizations->isEmpty())
                        <div class="p-5"><x-empty-state title="No organisations yet" /></div>
                    @else
                        <ul class="divide-y divide-edge">
                            @foreach ($recentOrganizations as $org)
                                <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 text-sm">
                                    <div class="min-w-0">
                                        <a href="{{ route('admin.organizations.show', $org) }}" class="font-medium text-fg hover:text-primary hover:underline">{{ $org->name }}</a>
                                        <p class="text-xs text-fg-subtle">{{ $org->type->label() }} · {{ $org->users_count }} {{ $org->users_count === 1 ? 'account' : 'accounts' }} · {{ $org->created_at->diffForHumans() }}</p>
                                    </div>
                                    <x-badge :tone="$org->status->tone()">{{ $org->status->label() }}</x-badge>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            @endcan

            @can('platform.audit.view')
                <x-card title="Latest audit entries" description="Across all organisations and the platform." :padding="false">
                    <x-slot:actions><x-button :href="route('admin.audit.index')" variant="ghost" size="sm">Audit trail <x-icon name="arrow-right" class="size-4" /></x-button></x-slot:actions>
                    @if ($recentActivity->isEmpty())
                        <div class="p-5"><x-empty-state title="No activity recorded yet" /></div>
                    @else
                        <ul class="divide-y divide-edge">
                            @foreach ($recentActivity as $activity)
                                <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 text-sm">
                                    <div class="min-w-0">
                                        <p class="font-medium text-fg">{{ $activity->eventLabel() }}</p>
                                        <p class="truncate text-xs text-fg-muted">{{ $activity->causer?->name ?? 'System' }} · {{ $activity->organization?->name ?? 'Platform' }}</p>
                                    </div>
                                    <time datetime="{{ $activity->created_at->toIso8601String() }}" class="text-xs text-fg-subtle">{{ $activity->created_at->diffForHumans() }}</time>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            @endcan
        </div>

        <div class="space-y-6">
            <x-card title="Tenant health">
                <dl class="space-y-2 text-sm">
                    @foreach (['organizations_active' => ['Active', 'success'], 'organizations_pending' => ['Pending verification', 'warning'], 'organizations_suspended' => ['Suspended', 'danger'], 'organizations_closed' => ['Closed', 'neutral']] as $key => [$label, $tone])
                        <div class="flex items-center justify-between gap-2">
                            <dt class="flex items-center gap-2 text-fg-muted"><x-badge :tone="$tone" class="!px-1.5"> </x-badge>{{ $label }}</dt>
                            <dd class="font-semibold text-fg tabular-nums">{{ number_format($counts[$key]) }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-card>

            <x-card title="Security · last 24 h">
                <x-slot:actions>@can('platform.audit.view')<x-button :href="route('admin.audit.logins')" variant="ghost" size="sm">History</x-button>@endcan</x-slot:actions>
                <dl class="grid grid-cols-3 gap-3 text-center">
                    <div class="rounded-xl border border-edge bg-success-soft/60 p-3 shadow-float"><dt class="text-xs text-fg-muted">Sign-ins</dt><dd class="mt-1 text-xl font-semibold text-fg tabular-nums">{{ number_format($counts['logins_24h']) }}</dd></div>
                    <div class="rounded-xl border border-edge bg-warning-soft/60 p-3 shadow-float"><dt class="text-xs text-fg-muted">Failed</dt><dd class="mt-1 text-xl font-semibold text-fg tabular-nums">{{ number_format($counts['failed_24h']) }}</dd></div>
                    <div class="rounded-xl border border-edge bg-danger-soft/60 p-3 shadow-float"><dt class="text-xs text-fg-muted">Lockouts</dt><dd class="mt-1 text-xl font-semibold text-fg tabular-nums">{{ number_format($counts['lockouts_24h']) }}</dd></div>
                </dl>
            </x-card>

            <x-card title="Access control">
                <ul class="space-y-2 text-sm">
                    @can('platform.users.view')<li><a href="{{ route('admin.users.index') }}" class="flex items-center justify-between text-fg hover:text-primary"><span>All user accounts</span><x-icon name="chevron-right" class="size-4 text-fg-subtle" /></a></li>@endcan
                    @can('viewAny', \App\Models\Role::class)<li><a href="{{ route('admin.roles.index') }}" class="flex items-center justify-between text-fg hover:text-primary"><span>Roles</span><x-icon name="chevron-right" class="size-4 text-fg-subtle" /></a></li>@endcan
                    @can('viewAny', \App\Models\Permission::class)<li><a href="{{ route('admin.permissions.index') }}" class="flex items-center justify-between text-fg hover:text-primary"><span>Permission catalogue</span><x-icon name="chevron-right" class="size-4 text-fg-subtle" /></a></li>@endcan
                </ul>
            </x-card>
        </div>
    </div>
</x-layouts.app>
