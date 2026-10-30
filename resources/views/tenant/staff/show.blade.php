<x-layouts.app :title="$staff->name">
    <x-page-header :title="$staff->name" :description="($staff->job_title ? $staff->job_title.' · ' : '').'Staff no. '.$staff->staff_number" eyebrow="Staff register">
        <x-button :href="route('org.staff.index')" variant="ghost" size="sm"><x-icon name="chevron-left" class="size-4" /> Register</x-button>
        @if ($canUpdate)
            <x-button :href="route('org.staff.edit', $staff)" variant="secondary" size="sm"><x-icon name="pencil" class="size-4" /> Edit</x-button>
        @endif
    </x-page-header>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Details">
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-fg-muted">Status</dt><dd class="mt-0.5"><x-badge :tone="$staff->status->tone()">{{ $staff->status->label() }}</x-badge></dd></div>
                    <div><dt class="text-fg-muted">Work type</dt><dd class="mt-0.5 font-medium text-fg">{{ $staff->work_type->label() }}</dd></div>
                    <div><dt class="text-fg-muted">Department</dt><dd class="mt-0.5 font-medium text-fg">{{ $staff->department?->name ?? '—' }}</dd></div>
                    <div><dt class="text-fg-muted">Primary location</dt><dd class="mt-0.5 font-medium text-fg">{{ $staff->primaryLocation?->name ?? '—' }}</dd></div>
                    <div><dt class="text-fg-muted">Work e-mail</dt><dd class="mt-0.5 font-medium text-fg">{{ $staff->email ?? '—' }}</dd></div>
                    <div><dt class="text-fg-muted">Phone</dt><dd class="mt-0.5 font-medium text-fg">{{ $staff->phone ?? '—' }}</dd></div>
                    <div><dt class="text-fg-muted">Started</dt><dd class="mt-0.5 font-medium text-fg">{{ $staff->started_on?->toFormattedDateString() ?? '—' }}</dd></div>
                    <div><dt class="text-fg-muted">Ended</dt><dd class="mt-0.5 font-medium text-fg">{{ $staff->ended_on?->toFormattedDateString() ?? '—' }}</dd></div>
                </dl>
                @if ($staff->notes)
                    <div class="mt-5 border-t border-edge pt-4 text-sm">
                        <p class="text-fg-muted">Notes</p>
                        <p class="mt-1 whitespace-pre-line text-fg">{{ $staff->notes }}</p>
                    </div>
                @endif
            </x-card>

            <x-coming-soon :module="config('orbit.modules.attendance') + ['label' => 'Attendance history for this staff member']" />
        </div>

        <div class="space-y-6">
            <x-card title="Login account" description="The user identity linked to this record, if any.">
                @if ($staff->user)
                    <x-user-chip :user="$staff->user" :href="$canViewUser ? route('org.users.show', $staff->user) : null" />
                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between gap-2"><dt class="text-fg-muted">Account status</dt><dd><x-badge :tone="$staff->user->status->tone()">{{ $staff->user->status->label() }}</x-badge></dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-fg-muted">Roles</dt><dd class="text-right text-fg">{{ $staff->user->roles->map->label()->join(', ') ?: '—' }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-fg-muted">Last sign-in</dt><dd class="text-fg">{{ $staff->user->last_login_at?->timezone($timezone)->diffForHumans() ?? 'Never' }}</dd></div>
                    </dl>
                @else
                    <p class="text-sm text-fg-muted">Not linked to an account. @if ($canUpdate)<a href="{{ route('org.staff.edit', $staff) }}" class="text-primary hover:underline">Link an existing account</a> or invite one from Users &amp; access.@endif</p>
                @endif
            </x-card>

            <x-card title="Record">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-2"><dt class="text-fg-muted">Created</dt><dd class="text-fg">{{ $staff->created_at->timezone($timezone)->toFormattedDateString() }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-fg-muted">Updated</dt><dd class="text-fg">{{ $staff->updated_at->timezone($timezone)->diffForHumans() }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-fg-muted">Reference</dt><dd class="font-mono text-xs text-fg-subtle">{{ $staff->uuid }}</dd></div>
                </dl>
            </x-card>

            @can('delete', $staff)
                <x-card title="Archive record" description="Removes the record from the register while keeping history. The linked account is not affected.">
                    <x-confirm-form :action="route('org.staff.destroy', $staff)" method="DELETE" :confirm="'Archive the staff record for '.$staff->name.'?'">
                        <x-button type="submit" variant="danger-outline" size="sm"><x-icon name="trash" class="size-4" /> Archive</x-button>
                    </x-confirm-form>
                </x-card>
            @endcan
        </div>
    </div>
</x-layouts.app>
