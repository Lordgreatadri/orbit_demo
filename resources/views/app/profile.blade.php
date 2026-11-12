<x-layouts.app title="My account">
    <x-page-header title="{{ $user->name }}" description="Your profile, roles, and recent sign-ins. Changes to your access are made by an administrator and recorded in the audit trail." eyebrow="Account" />

    <section aria-labelledby="identity-heading" class="briefing overflow-hidden">
        <div class="flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:p-6">
            <span aria-hidden="true" class="grid size-16 shrink-0 place-items-center rounded-2xl bg-brand-deep text-xl font-semibold text-white">{{ $user->initials }}</span>
            <div class="min-w-0 flex-1">
                <h2 id="identity-heading" class="text-lg font-semibold text-fg">Role and verification</h2>
                @if ($user->roles->isEmpty())
                    <p class="mt-1 text-sm text-fg-muted">No role assigned.</p>
                @else
                    <ul class="mt-1 space-y-1">
                        @foreach ($user->roles as $role)
                            <li>
                                <p class="text-sm font-medium text-fg">{{ $role->label() }}</p>
                                @php $roleText = \App\Tenancy\RoleAudience::description($role, $organization); @endphp
                                @if ($roleText !== '')<p class="text-xs text-fg-muted">{{ $roleText }}</p>@endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <p class="flex flex-wrap items-center gap-2 text-sm">
                <x-badge :tone="$user->isActive() ? 'success' : 'danger'">{{ $user->status->label() }}</x-badge>
                @if ($user->hasVerifiedEmail())
                    <x-badge tone="success"><x-icon name="check" class="size-3" /> Email verified</x-badge>
                @endif
                @if ($user->hasVerifiedPhone())
                    <x-badge tone="success"><x-icon name="check" class="size-3" /> Phone verified</x-badge>
                @endif
            </p>
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-5">
        <x-card class="lg:col-span-3" icon="id-card" title="Account information">
            <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                <div><dt class="text-fg-muted">Name</dt><dd class="mt-0.5 font-medium text-fg">{{ $user->name }}</dd></div>
                <div class="min-w-0"><dt class="text-fg-muted">E-mail</dt><dd class="mt-0.5 font-medium break-words text-fg">{{ $user->email }}</dd></div>
                <div><dt class="text-fg-muted">Phone</dt><dd class="mt-0.5 font-medium text-fg">{{ $user->phone ?? '—' }}</dd></div>
                <div><dt class="text-fg-muted">Account type</dt><dd class="mt-0.5 font-medium text-fg">{{ $organization ? 'Organisation account · '.$organization->name : 'Platform account' }}</dd></div>
                <div><dt class="text-fg-muted">Member since</dt><dd class="mt-0.5 font-medium text-fg">{{ $user->created_at->timezone($timezone)->format('j M Y') }}</dd></div>
                @if ($user->staffMember)
                    <div><dt class="text-fg-muted">Staff record</dt><dd class="mt-0.5 font-medium text-fg">{{ $user->staffMember->staff_number }} · {{ $user->staffMember->department?->name ?? 'No department' }}</dd></div>
                @endif
            </dl>
        </x-card>

        <x-card class="lg:col-span-2" icon="key" title="Effective permissions" :description="'Granted through roles and any direct grants. '.$permissions->count().' in total.'">
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

    <x-card icon="login" title="Recent sign-ins" description="The last 10 events recorded for this account. Includes failed attempts against your e-mail address." :padding="false" decision="whether someone else may be trying your account">
        @if ($recentSignIns->isEmpty())
            <p class="px-5 py-4 text-sm text-fg-muted">No sign-in events yet.</p>
        @else
            <ul class="divide-y divide-edge">
                @foreach ($recentSignIns as $event)
                    <li class="flex min-h-11 flex-wrap items-center justify-between gap-2 px-5 py-3 text-sm">
                        <div class="flex items-center gap-3">
                            <x-badge :tone="$event->event->tone()">{{ $event->event->label() }}</x-badge>
                            <span class="text-fg-muted">{{ $event->created_at->timezone($timezone)->format('D j M Y, H:i') }}</span>
                        </div>
                        <span class="font-mono text-xs text-fg-subtle">{{ $event->ip_address ?? '—' }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</x-layouts.app>
