<x-layouts.app :title="$organization->name">
    <x-page-header :title="$organization->name" :description="$organization->type->label().' · '.str_replace('_', ' ', $organization->timezone).' · registered '.$organization->created_at->toFormattedDateString()" eyebrow="Organisation">
        <x-button :href="route('admin.organizations.index')" variant="ghost" size="sm"><x-icon name="chevron-left" class="size-4" /> All organisations</x-button>
        @can('platform.users.view')
            <x-button :href="route('admin.users.index', ['organization' => $organization->uuid])" variant="secondary" size="sm"><x-icon name="users" class="size-4" /> Accounts</x-button>
        @endcan
    </x-page-header>

    <x-workspace.settings-tabs :organization="$organization" current="profile" />

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="User accounts" :value="number_format($counts['users'])" :hint="$counts['users_suspended'] > 0 ? $counts['users_suspended'].' suspended' : $counts['users_active'].' active'" icon="key" />
        <x-stat label="Staff records" :value="number_format($counts['staff'])" icon="id-card" tone="success" />
        <x-stat label="Departments" :value="number_format($counts['departments'])" icon="layers" tone="accent" />
        <x-stat label="Locations" :value="number_format($counts['locations'])" icon="map-pin" tone="neutral" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Profile">
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-fg-muted">Status</dt><dd class="mt-0.5"><x-badge :tone="$organization->status->tone()">{{ $organization->status->label() }}</x-badge></dd></div>
                    <div><dt class="text-fg-muted">Workspace slug</dt><dd class="mt-0.5 font-mono text-xs text-fg">{{ $organization->slug }}</dd></div>
                    <div><dt class="text-fg-muted">Contact e-mail</dt><dd class="mt-0.5 font-medium text-fg">{{ $organization->contact_email }}</dd></div>
                    <div><dt class="text-fg-muted">Contact phone</dt><dd class="mt-0.5 font-medium text-fg">{{ $organization->contact_phone ?? '—' }}</dd></div>
                    <div><dt class="text-fg-muted">Address</dt><dd class="mt-0.5 font-medium text-fg">{{ collect([$organization->address_line, $organization->city])->filter()->join(', ') ?: '—' }}</dd></div>
                    <div><dt class="text-fg-muted">Verified</dt><dd class="mt-0.5 font-medium text-fg">{{ $organization->verified_at?->toDayDateTimeString() ?? 'Not yet' }}</dd></div>
                    <div><dt class="text-fg-muted">Demo tenant</dt><dd class="mt-0.5 font-medium text-fg">{{ $organization->isDemo() ? 'Yes — contains labelled sample data' : 'No' }}</dd></div>
                    <div><dt class="text-fg-muted">Reference</dt><dd class="mt-0.5 font-mono text-xs text-fg-subtle">{{ $organization->uuid }}</dd></div>
                </dl>
            </x-card>

            <x-card title="Organisation administrators" :padding="false">
                @if ($administrators->isEmpty())
                    <p class="p-5 text-sm text-fg-muted">No organisation administrator. The platform can assign the role from the user's account page.</p>
                @else
                    <ul class="divide-y divide-edge">
                        @foreach ($administrators as $admin)
                            <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                                <x-user-chip :user="$admin" :href="auth()->user()->can('view', $admin) ? route('admin.users.show', $admin) : null" />
                                <div class="flex items-center gap-2">
                                    <span class="hidden text-xs text-fg-subtle sm:inline">{{ $admin->last_login_at ? 'Signed in '.$admin->last_login_at->diffForHumans() : 'Never signed in' }}</span>
                                    <x-badge :tone="$admin->status->tone()">{{ $admin->status->label() }}</x-badge>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            @if ($recentActivity->isNotEmpty())
                <x-card title="Recent activity in this organisation" :padding="false">
                    <x-slot:actions><x-button :href="route('admin.audit.index', ['organization' => $organization->uuid])" variant="ghost" size="sm">Full trail</x-button></x-slot:actions>
                    <ul class="divide-y divide-edge">
                        @foreach ($recentActivity as $activity)
                            <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-2.5 text-sm">
                                <div class="min-w-0"><p class="font-medium text-fg">{{ $activity->eventLabel() }}</p><p class="text-xs text-fg-subtle">{{ $activity->causer?->name ?? 'System' }}</p></div>
                                <time class="text-xs text-fg-subtle">{{ $activity->created_at->timezone($organization->timezone)->format('j M, H:i') }}</time>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </div>

        <div class="space-y-6">
            @if ($canUpdateStatus && $statusOptions !== [])
                <x-card title="Change status" description="Suspending or closing signs out every account in the organisation immediately. The reason is recorded in the audit trail.">
                    <form method="POST" action="{{ route('admin.organizations.status', $organization) }}" class="space-y-4" onsubmit="return confirm('Change the status of {{ addslashes($organization->name) }}?');">
                        @csrf
                        @method('PATCH')
                        <x-form.select name="status" label="New status" :options="$statusOptions" required placeholder="Choose…" />
                        <x-form.textarea name="reason" label="Reason" rows="2" required placeholder="Why is this status changing?" />
                        <x-button type="submit" variant="danger-outline" class="w-full"><x-icon name="alert" class="size-4" /> Apply status change</x-button>
                    </form>
                </x-card>
            @endif

            <x-card title="Boundaries" description="What the platform console does and does not do.">
                <ul class="space-y-2 text-sm text-fg-muted">
                    <li class="flex gap-2"><x-icon name="check" class="mt-0.5 size-4 shrink-0 text-success" /> View tenant metadata, administrators, and audit history.</li>
                    <li class="flex gap-2"><x-icon name="check" class="mt-0.5 size-4 shrink-0 text-success" /> Suspend, close, or reactivate the organisation.</li>
                    <li class="flex gap-2"><x-icon name="ban" class="mt-0.5 size-4 shrink-0 text-fg-subtle" /> No editing of tenant staff, departments, or settings — that remains with the organisation's own administrators.</li>
                </ul>
            </x-card>
        </div>
    </div>
</x-layouts.app>
