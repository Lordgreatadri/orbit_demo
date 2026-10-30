<x-layouts.app :title="$user->name">
    <x-page-header :title="$user->name" :description="$user->email" eyebrow="User account">
        <x-button :href="route('org.users.index')" variant="ghost" size="sm"><x-icon name="chevron-left" class="size-4" /> All accounts</x-button>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Account">
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-fg-muted">Status</dt><dd class="mt-0.5"><x-badge :tone="$user->status->tone()">{{ $user->status->label() }}</x-badge></dd></div>
                    <div><dt class="text-fg-muted">E-mail</dt><dd class="mt-0.5 font-medium text-fg">{{ $user->email }} @if ($user->hasVerifiedEmail())<x-badge tone="success" class="ml-1">Verified</x-badge>@endif</dd></div>
                    <div><dt class="text-fg-muted">Phone</dt><dd class="mt-0.5 font-medium text-fg">{{ $user->phone ?? '—' }}</dd></div>
                    <div><dt class="text-fg-muted">Last sign-in</dt><dd class="mt-0.5 font-medium text-fg">{{ $user->last_login_at?->timezone($timezone)->toDayDateTimeString() ?? 'Never' }}</dd></div>
                    <div><dt class="text-fg-muted">Joined</dt><dd class="mt-0.5 font-medium text-fg">{{ $user->created_at->timezone($timezone)->toFormattedDateString() }}</dd></div>
                    <div>
                        <dt class="text-fg-muted">Staff record</dt>
                        <dd class="mt-0.5 font-medium text-fg">
                            @if ($user->staffMember)
                                <a href="{{ route('org.staff.show', $user->staffMember) }}" class="text-primary hover:underline">{{ $user->staffMember->staff_number }}</a>
                                <span class="text-fg-muted">· {{ $user->staffMember->department?->name ?? 'No department' }}</span>
                            @else
                                <span class="text-fg-muted">Not linked</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </x-card>

            <x-access.user-access-panel
                :user="$user" :actor="$actor" :can-manage="$canManage"
                :grantable-roles="$grantableRoles" :grantable-permissions="$grantablePermissions" :module-labels="$moduleLabels"
                :roles-action="route('org.users.roles', $user)" :permissions-action="route('org.users.permissions', $user)" :status-action="route('org.users.status', $user)"
            />
        </div>

        <div class="space-y-6">
            <x-access.user-activity-panels :user="$user" :effective-permissions="$effectivePermissions" :recent-sign-ins="$recentSignIns" :recent-activity="$recentActivity" :timezone="$timezone" />
        </div>
    </div>
</x-layouts.app>
