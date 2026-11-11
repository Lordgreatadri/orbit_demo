<x-layouts.app title="My account">
    <x-page-header title="{{ $user->name }}" description="Your profile, roles, and recent sign-ins. Changes to your access are made by an administrator and recorded in the audit trail." eyebrow="Account" />

    <section aria-labelledby="identity-heading" class="max-w-3xl">
        <h2 id="identity-heading" class="text-base font-semibold text-fg">Role and verification</h2>
        @if ($user->roles->isEmpty())
            <p class="mt-2 text-sm text-fg-muted">No role assigned.</p>
        @else
            <ul class="mt-3 space-y-2">
                @foreach ($user->roles as $role)
                    <li>
                        <p class="text-sm font-medium text-fg">{{ $role->label() }}</p>
                        @php $roleText = \App\Tenancy\RoleAudience::description($role, $organization); @endphp
                        @if ($roleText !== '')<p class="text-xs text-fg-muted">{{ $roleText }}</p>@endif
                    </li>
                @endforeach
            </ul>
        @endif
        <p class="mt-3 flex flex-wrap items-center gap-2 text-sm">
            <x-badge :tone="$user->isActive() ? 'success' : 'danger'">{{ $user->status->label() }}</x-badge>
            @if ($user->hasVerifiedEmail())
                <x-badge tone="success">Email verified</x-badge>
            @endif
            @if ($user->hasVerifiedPhone())
                <x-badge tone="success">Phone verified</x-badge>
            @endif
        </p>
    </section>

    <section aria-labelledby="account-heading" class="max-w-3xl">
        <h2 id="account-heading" class="text-base font-semibold text-fg">Account information</h2>
        <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
            <div><dt class="text-fg-muted">Name</dt><dd class="mt-0.5 font-medium text-fg">{{ $user->name }}</dd></div>
            <div><dt class="text-fg-muted">E-mail</dt><dd class="mt-0.5 font-medium text-fg">{{ $user->email }}</dd></div>
            <div><dt class="text-fg-muted">Phone</dt><dd class="mt-0.5 font-medium text-fg">{{ $user->phone ?? '—' }}</dd></div>
            <div><dt class="text-fg-muted">Account type</dt><dd class="mt-0.5 font-medium text-fg">{{ $organization ? 'Organisation account · '.$organization->name : 'Platform account' }}</dd></div>
            <div><dt class="text-fg-muted">Member since</dt><dd class="mt-0.5 font-medium text-fg">{{ $user->created_at->timezone($timezone)->toFormattedDateString() }}</dd></div>
            @if ($user->staffMember)
                <div><dt class="text-fg-muted">Staff record</dt><dd class="mt-0.5 font-medium text-fg">{{ $user->staffMember->staff_number }} · {{ $user->staffMember->department?->name ?? 'No department' }}</dd></div>
            @endif
        </dl>
    </section>

    <section aria-labelledby="permissions-heading" class="max-w-3xl">
        <h2 id="permissions-heading" class="text-base font-semibold text-fg">Effective permissions</h2>
        <p class="mt-1 text-sm text-fg-muted">Granted through roles and any direct grants.</p>
        @if ($permissions->isEmpty())
            <p class="mt-3 text-sm text-fg-muted">No permissions. You can still use self-service features that do not require one.</p>
        @else
            <ul class="mt-3 flex flex-wrap gap-1.5">
                @foreach ($permissions as $permission)
                    <li><x-badge tone="neutral" title="{{ $permission->description }}">{{ $permission->name }}</x-badge></li>
                @endforeach
            </ul>
        @endif
    </section>

    <section aria-labelledby="signins-heading" class="max-w-3xl">
        <h2 id="signins-heading" class="text-base font-semibold text-fg">Recent sign-ins</h2>
        <p class="mt-1 text-sm text-fg-muted">The last 10 events recorded for this account. Includes failed attempts against your e-mail address.</p>
        @if ($recentSignIns->isEmpty())
            <p class="mt-3 text-sm text-fg-muted">No sign-in events yet.</p>
        @else
            <ul class="mt-3 divide-y divide-edge">
                @foreach ($recentSignIns as $event)
                    <li class="flex min-h-11 flex-wrap items-center justify-between gap-2 py-3 text-sm">
                        <div class="flex items-center gap-3">
                            <x-badge :tone="$event->event->tone()">{{ $event->event->label() }}</x-badge>
                            <span class="text-fg-muted">{{ $event->created_at->timezone($timezone)->toDayDateTimeString() }}</span>
                        </div>
                        <span class="font-mono text-xs text-fg-subtle">{{ $event->ip_address ?? '—' }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layouts.app>
