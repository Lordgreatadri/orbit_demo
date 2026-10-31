<x-layouts.app title="Organisation settings">
    <x-page-header title="Organisation settings" description="Details shown to your users and used for time-zone-aware records. Changes are written to the audit trail." eyebrow="Administration" />

    <x-workspace.settings-tabs :organization="$organization" current="profile" />

    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('org.organizations.update', $organization) }}" class="space-y-6 lg:col-span-2">
            @csrf
            @method('PUT')
            <fieldset @disabled(! $canUpdate) class="space-y-6">
                <x-card title="Identity">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="sm:col-span-2"><x-form.input name="name" label="Organisation name" :value="$organization->name" required /></div>
                        <x-form.select name="type" label="Organisation type" :options="$organizationTypes" :value="$organization->type->value" required />
                        <x-form.select name="timezone" label="Time zone" :options="$timezones" :value="$organization->timezone" required hint="Attendance and reports use this time zone." />
                    </div>
                </x-card>
                <x-card title="Contact">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-form.input name="contact_email" type="email" label="Contact e-mail" :value="$organization->contact_email" required />
                        <x-form.input name="contact_phone" type="tel" label="Contact phone" :value="$organization->contact_phone" placeholder="+233201234567" />
                        <div class="sm:col-span-2"><x-form.input name="address_line" label="Address" :value="$organization->address_line" /></div>
                        <x-form.input name="city" label="City / town" :value="$organization->city" />
                    </div>
                </x-card>
            </fieldset>
            @if ($canUpdate)
                <div class="flex justify-end"><x-button type="submit">Save settings</x-button></div>
            @else
                <p class="text-sm text-fg-muted">You can view these settings, but only an organisation administrator can change them.</p>
            @endif
        </form>

        <div class="space-y-6">
            <x-card title="Account">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-2"><dt class="text-fg-muted">Status</dt><dd><x-badge :tone="$organization->status->tone()">{{ $organization->status->label() }}</x-badge></dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-fg-muted">Workspace URL</dt><dd class="font-mono text-xs text-fg">{{ $organization->primaryHost() }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-fg-muted">Verified</dt><dd class="text-fg">{{ $organization->verified_at?->toFormattedDateString() ?? 'Pending' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-fg-muted">Created</dt><dd class="text-fg">{{ $organization->created_at->toFormattedDateString() }}</dd></div>
                </dl>
                <p class="mt-4 text-xs text-fg-subtle">Suspension, closure, and plan changes are handled by platform support. <a href="{{ \App\Tenancy\Origin::centralUrl('/contact') }}" class="text-primary hover:underline">Contact support</a>.</p>
            </x-card>
            <x-card title="Structure">
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('org.departments.index') }}" class="flex items-center justify-between text-fg hover:text-primary"><span>Departments</span><x-icon name="chevron-right" class="size-4 text-fg-subtle" /></a></li>
                    <li><a href="{{ route('org.locations.index') }}" class="flex items-center justify-between text-fg hover:text-primary"><span>Locations</span><x-icon name="chevron-right" class="size-4 text-fg-subtle" /></a></li>
                    <li><a href="{{ route('org.roles.index') }}" class="flex items-center justify-between text-fg hover:text-primary"><span>Roles &amp; permissions</span><x-icon name="chevron-right" class="size-4 text-fg-subtle" /></a></li>
                </ul>
            </x-card>
        </div>
    </div>
</x-layouts.app>
