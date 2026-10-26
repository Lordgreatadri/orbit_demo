<x-layouts.app title="Members">
    <x-page-header
        title="Members"
        :description="$platformDirectory
            ? 'People across every organisation. Opening this directory is recorded in the audit log.'
            : 'People who belong to '.$organization->name.'. New members join only through an invitation.'"
    />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Directory" :padding="false">
                @if ($members->isEmpty())
                    <div class="p-5"><x-empty-state title="No members yet" description="Invite your first colleague using the form." /></div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-surface-muted text-xs tracking-wide text-fg-muted uppercase">
                                <tr>
                                    <th scope="col" class="px-5 py-3 font-medium">Name</th>
                                    @if ($platformDirectory)
                                        <th scope="col" class="px-5 py-3 font-medium">Organisation</th>
                                    @endif
                                    <th scope="col" class="px-5 py-3 font-medium">Role</th>
                                    <th scope="col" class="px-5 py-3 font-medium">Status</th>
                                    <th scope="col" class="px-5 py-3 font-medium">Last sign-in</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-edge">
                                @foreach ($members as $member)
                                    <tr>
                                        <td class="px-5 py-3">
                                            <p class="font-medium text-fg">{{ $member->name }}</p>
                                            <p class="text-xs text-fg-subtle">{{ $member->email }}</p>
                                        </td>
                                        @if ($platformDirectory)
                                            <td class="px-5 py-3 text-fg-muted">{{ $member->organization?->name }}</td>
                                        @endif
                                        <td class="px-5 py-3 text-fg-muted">
                                            {{ $member->roles->map(fn ($r) => config('orbit.roles.tenant.'.$r->name, $r->name))->join(', ') ?: '—' }}
                                        </td>
                                        <td class="px-5 py-3">
                                            <x-badge :tone="$member->isActive() ? 'success' : 'danger'">{{ $member->status->label() }}</x-badge>
                                            @unless ($member->hasVerifiedEmail() && $member->hasVerifiedPhone())
                                                <x-badge tone="warning" class="ml-1">Unverified</x-badge>
                                            @endunless
                                        </td>
                                        <td class="px-5 py-3 text-fg-muted">
                                            @php
                                                $zone = $platformDirectory
                                                    ? ($member->organization?->timezone ?? config('app.timezone'))
                                                    : $organization->timezone;
                                            @endphp
                                            {{ $member->last_login_at?->timezone($zone)->diffForHumans() ?? 'Never' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($members->hasPages())
                        <div class="border-t border-edge px-5 py-3">{{ $members->links() }}</div>
                    @endif
                @endif
            </x-card>

            @if ($invitations->isNotEmpty())
                <x-card title="Invitations" description="Pending, expired, and revoked invitations." :padding="false">
                    <ul class="divide-y divide-edge">
                        @foreach ($invitations as $invitation)
                            <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 text-sm">
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-fg">{{ $invitation->email }}</p>
                                    <p class="text-xs text-fg-subtle">
                                        {{ config('orbit.roles.tenant.'.$invitation->role, $invitation->role) }}
                                        · invited {{ $invitation->created_at->diffForHumans() }}
                                        @if ($invitation->inviter) by {{ $invitation->inviter->name }} @endif
                                        · expires {{ $invitation->expires_at->timezone($organization->timezone)->toFormattedDateString() }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    @php
                                        $tone = match ($invitation->status) {
                                            \App\Enums\InvitationStatus::Pending => 'info',
                                            \App\Enums\InvitationStatus::Expired => 'warning',
                                            \App\Enums\InvitationStatus::Revoked => 'danger',
                                            default => 'neutral',
                                        };
                                    @endphp
                                    <x-badge :tone="$tone">{{ $invitation->status->label() }}</x-badge>
                                    @if ($invitation->isPending() && $canInvite)
                                        <form method="POST" action="{{ route('app.invitations.destroy', $invitation) }}" onsubmit="return confirm('Revoke this invitation?');">
                                            @csrf
                                            @method('DELETE')
                                            <x-button type="submit" variant="ghost" size="sm">Revoke</x-button>
                                        </form>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </div>

        <div>
            @if ($canInvite)
                <x-card title="Invite a member" description="They will receive a single-use link valid for {{ config('orbit.invitation_ttl_days') }} days.">
                    <form method="POST" action="{{ route('app.invitations.store') }}" class="space-y-4" x-data="{ submitting: false }" @submit="submitting = true">
                        @csrf
                        <x-form.input name="email" label="E-mail address" type="email" required autocomplete="off" />
                        <x-form.select name="role" label="Role" :options="$invitableRoles" value="member" required />
                        <x-button type="submit" class="w-full" x-bind:disabled="submitting">Send invitation</x-button>
                    </form>
                </x-card>
            @elseif ($platformDirectory)
                <x-alert type="info" title="Read only">Invitations stay with each organisation's administrator. This directory does not grant a platform account a tenant workspace.</x-alert>
            @elseif ($organization && ! $organization->isActive())
                <x-alert type="info" title="Invitations unavailable">Invitations can be sent once the organisation is active (after e-mail verification).</x-alert>
            @else
                <x-alert type="info">Only organisation administrators can send invitations.</x-alert>
            @endif
        </div>
    </div>
</x-layouts.app>
