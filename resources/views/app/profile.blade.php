<x-layouts.app title="My account">
    <x-page-header title="My account" description="Your profile, roles, and recent sign-ins. Changes to your access are made by an administrator and recorded in the audit trail." />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Profile">
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-fg-muted">Name</dt><dd class="mt-0.5 font-medium text-fg">{{ $user->name }}</dd></div>
                    <div><dt class="text-fg-muted">E-mail</dt><dd class="mt-0.5 font-medium text-fg">{{ $user->email }} @if ($user->hasVerifiedEmail())<x-badge tone="success" class="ml-1">Verified</x-badge>@endif</dd></div>
                    <div><dt class="text-fg-muted">Phone</dt><dd class="mt-0.5 font-medium text-fg">{{ $user->phone ?? '—' }} @if ($user->hasVerifiedPhone())<x-badge tone="success" class="ml-1">Verified</x-badge>@endif</dd></div>
                    <div><dt class="text-fg-muted">Account type</dt><dd class="mt-0.5 font-medium text-fg">{{ $organization ? 'Organisation account · '.$organization->name : 'Platform account' }}</dd></div>
                    <div><dt class="text-fg-muted">Status</dt><dd class="mt-0.5"><x-badge :tone="$user->isActive() ? 'success' : 'danger'">{{ $user->status->label() }}</x-badge></dd></div>
                    <div><dt class="text-fg-muted">Member since</dt><dd class="mt-0.5 font-medium text-fg">{{ $user->created_at->timezone($timezone)->toFormattedDateString() }}</dd></div>
                    @if ($user->staffMember)
                        <div><dt class="text-fg-muted">Staff record</dt><dd class="mt-0.5 font-medium text-fg">{{ $user->staffMember->staff_number }} · {{ $user->staffMember->department?->name ?? 'No department' }}</dd></div>
                    @endif
                </dl>
            </x-card>

            <x-card title="Recent sign-ins" description="The last 10 events recorded for this account. Includes failed attempts against your e-mail address." :padding="false">
                @if ($recentSignIns->isEmpty())
                    <div class="p-5"><x-empty-state title="No sign-in events yet" /></div>
                @else
                    <ul class="divide-y divide-edge">
                        @foreach ($recentSignIns as $event)
                            <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 text-sm">
                                <div class="flex items-center gap-3">
                                    <x-badge :tone="$event->event->tone()">{{ $event->event->label() }}</x-badge>
                                    <span class="text-fg-muted">{{ $event->created_at->timezone($timezone)->toDayDateTimeString() }}</span>
                                </div>
                                <span class="font-mono text-xs text-fg-subtle">{{ $event->ip_address ?? '—' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Roles">
                @if ($user->roles->isEmpty())
                    <p class="text-sm text-fg-muted">No role assigned.</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($user->roles as $role)
                            <li>
                                <p class="text-sm font-medium text-fg">{{ $role->label() }}</p>
                                @if ($role->description)<p class="text-xs text-fg-muted">{{ $role->description }}</p>@endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
            <x-card title="Effective permissions" description="Granted through roles and any direct grants.">
                @if ($permissions->isEmpty())
                    <p class="text-sm text-fg-muted">No permissions. You can still use self-service features that do not require one.</p>
                @else
                    <ul class="flex flex-wrap gap-1.5">
                        @foreach ($permissions as $permission)
                            <li><x-badge tone="neutral" title="{{ $permission->description }}">{{ $permission->name }}</x-badge></li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    </div>
</x-layouts.app>
