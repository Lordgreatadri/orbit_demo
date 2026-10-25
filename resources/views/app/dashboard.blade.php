<x-layouts.app title="Dashboard">
    @php
        $hour = (int) now($organization?->timezone ?? config('app.timezone'))->format('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    @endphp

    <x-page-header :title="$greeting.', '.$user->first_name" :description="$organization ? $organization->name.' · '.now($organization->timezone)->translatedFormat('l, j F Y') : 'Platform account'">
        @if ($organization && $user->can('create', \App\Models\Invitation::class))
            <x-button :href="route('app.members.index')" variant="secondary" size="sm">Invite members</x-button>
        @endif
        @if ($organization && $user->can('view', $organization))
            <x-button :href="route('app.organizations.edit', $organization)" variant="secondary" size="sm">Organisation settings</x-button>
        @endif
    </x-page-header>

    @if ($organization)
        <section aria-labelledby="overview-heading" class="space-y-3">
            <h2 id="overview-heading" class="text-sm font-semibold text-fg-muted">Organisation overview</h2>
            <dl class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-edge bg-surface p-4">
                    <dt class="text-xs text-fg-muted">Status</dt>
                    <dd class="mt-1"><x-badge :tone="$organization->isActive() ? 'success' : 'warning'">{{ $organization->status->label() }}</x-badge></dd>
                </div>
                <div class="rounded-xl border border-edge bg-surface p-4">
                    <dt class="text-xs text-fg-muted">Members</dt>
                    <dd class="mt-1 text-2xl font-semibold text-fg">{{ number_format($memberCount) }}</dd>
                </div>
                <div class="rounded-xl border border-edge bg-surface p-4">
                    <dt class="text-xs text-fg-muted">Pending invitations</dt>
                    <dd class="mt-1 text-2xl font-semibold text-fg">{{ number_format($pendingInvitations) }}</dd>
                </div>
            </dl>
        </section>

        <section aria-labelledby="attendance-heading" class="space-y-3">
            <h2 id="attendance-heading" class="text-sm font-semibold text-fg-muted">Attendance today</h2>
            <x-empty-state title="No attendance data yet" description="Attendance sessions, QR check-in, and location verification are delivered in the next stage. Once enabled, present, late, absent, and on-leave counts appear here.">
                <x-button :href="route('guide')" variant="secondary" size="sm">Read the user guide</x-button>
            </x-empty-state>
        </section>

        <section aria-labelledby="next-steps-heading" class="space-y-3">
            <h2 id="next-steps-heading" class="text-sm font-semibold text-fg-muted">Next steps</h2>
            <ol class="grid gap-3 md:grid-cols-3">
                <li class="rounded-xl border border-edge bg-surface p-4">
                    <p class="font-medium text-fg">1. Confirm organisation details</p>
                    <p class="mt-1 text-sm text-fg-muted">Check the name, type, time zone, and contact details.</p>
                </li>
                <li class="rounded-xl border border-edge bg-surface p-4">
                    <p class="font-medium text-fg">2. Invite your administrators and members</p>
                    <p class="mt-1 text-sm text-fg-muted">Each person receives a single-use invitation link.</p>
                </li>
                <li class="rounded-xl border border-edge bg-surface p-4">
                    <p class="font-medium text-fg">3. Prepare locations and departments</p>
                    <p class="mt-1 text-sm text-fg-muted">Available in the next stage of delivery.</p>
                </li>
            </ol>
        </section>
    @else
        <x-empty-state title="Platform account" description="Platform tools are not part of this milestone. Tenant data is not shown to platform accounts without an explicit, audited action." />
    @endif
</x-layouts.app>
