<x-layouts.app :title="$user->name">
    <x-page-header :title="$user->name" :description="$user->email" :eyebrow="$user->organization ? 'Organisation account' : 'Platform account'">
        <x-button :href="route('admin.users.index')" variant="ghost" size="sm"><x-icon name="chevron-left" class="size-4" /> All accounts</x-button>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Account">
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-fg-muted">Status</dt><dd class="mt-0.5"><x-badge :tone="$user->status->tone()">{{ $user->status->label() }}</x-badge></dd></div>
                    <div>
                        <dt class="text-fg-muted">Organisation</dt>
                        <dd class="mt-0.5 font-medium text-fg">
                            @if ($user->organization)
                                @can('platform.organizations.view')
                                    <a href="{{ route('admin.organizations.show', $user->organization) }}" class="text-primary hover:underline">{{ $user->organization->name }}</a>
                                @else
                                    {{ $user->organization->name }}
                                @endcan
                            @else
                                <x-badge tone="info">Platform</x-badge>
                            @endif
                        </dd>
                    </div>
                    <div><dt class="text-fg-muted">E-mail</dt><dd class="mt-0.5 font-medium text-fg">{{ $user->email }} @if ($user->hasVerifiedEmail())<x-badge tone="success" class="ml-1">Verified</x-badge>@endif</dd></div>
                    <div><dt class="text-fg-muted">Phone</dt><dd class="mt-0.5 font-medium text-fg">{{ $user->phone ?? '—' }}</dd></div>
                    <div><dt class="text-fg-muted">Last sign-in</dt><dd class="mt-0.5 font-medium text-fg">{{ $user->last_login_at?->timezone($timezone)->toDayDateTimeString() ?? 'Never' }}</dd></div>
                    <div><dt class="text-fg-muted">Created</dt><dd class="mt-0.5 font-medium text-fg">{{ $user->created_at->timezone($timezone)->toFormattedDateString() }}</dd></div>
                    @if ($user->staffMember)
                        <div><dt class="text-fg-muted">Staff record</dt><dd class="mt-0.5 font-medium text-fg">{{ $user->staffMember->staff_number }} <span class="text-fg-muted">(managed by the organisation)</span></dd></div>
                    @endif
                    <div><dt class="text-fg-muted">Reference</dt><dd class="mt-0.5 font-mono text-xs text-fg-subtle">{{ $user->uuid }}</dd></div>
                </dl>
            </x-card>

            <x-access.user-access-panel
                :user="$user" :actor="$actor" :can-manage="$canManage"
                :grantable-roles="$grantableRoles" :grantable-permissions="$grantablePermissions" :module-labels="$moduleLabels"
                :roles-action="route('admin.users.roles', $user)" :permissions-action="route('admin.users.permissions', $user)" :status-action="route('admin.users.status', $user)"
            />
        </div>

        <div class="space-y-6">
            <x-access.user-activity-panels :user="$user" :effective-permissions="$effectivePermissions" :recent-sign-ins="$recentSignIns" :recent-activity="$recentActivity" :timezone="$timezone" />
        </div>
    </div>
</x-layouts.app>
