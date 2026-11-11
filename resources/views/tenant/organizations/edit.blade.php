<x-layouts.app title="Organisation settings">
    <x-page-header title="Organisation settings" description="Details shown to your users and used for time-zone-aware records. Changes are written to the audit trail." eyebrow="Administration" />

    <x-workspace.settings-tabs :organization="$organization" current="profile" />

    <dl class="flex flex-wrap gap-x-8 gap-y-3 text-sm">
        <div><dt class="text-xs text-fg-subtle">Status</dt><dd class="mt-1"><x-badge :tone="$organization->status->tone()">{{ $organization->status->label() }}</x-badge></dd></div>
        <div><dt class="text-xs text-fg-subtle">Workspace URL</dt><dd class="mt-1 font-mono text-xs text-fg">{{ $organization->primaryHost() }}</dd></div>
        <div><dt class="text-xs text-fg-subtle">Verified</dt><dd class="mt-1 text-fg">{{ $organization->verified_at?->toFormattedDateString() ?? 'Pending' }}</dd></div>
        <div><dt class="text-xs text-fg-subtle">Created</dt><dd class="mt-1 text-fg">{{ $organization->created_at->toFormattedDateString() }}</dd></div>
    </dl>

    <form method="POST" action="{{ route('org.organizations.update', $organization) }}" class="max-w-3xl space-y-8">
        @csrf
        @method('PUT')
        <fieldset @disabled(! $canUpdate) class="space-y-8">
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
            <x-button type="submit">Save settings</x-button>
        @else
            <p class="text-sm text-fg-muted">You can view these settings, but only an organisation administrator can change them.</p>
        @endif
    </form>

    <nav aria-label="Related registers" class="flex flex-wrap gap-x-4 gap-y-1 border-t border-edge pt-4 text-sm">
        <a href="{{ route('org.departments.index') }}" class="inline-flex min-h-11 items-center text-fg-muted hover:text-primary hover:underline">Departments</a>
        <a href="{{ route('org.locations.index') }}" class="inline-flex min-h-11 items-center text-fg-muted hover:text-primary hover:underline">Locations</a>
        <a href="{{ route('org.roles.index') }}" class="inline-flex min-h-11 items-center text-fg-muted hover:text-primary hover:underline">Roles and permissions</a>
        <a href="{{ \App\Tenancy\Origin::centralUrl('/contact') }}" class="inline-flex min-h-11 items-center text-fg-muted hover:text-primary hover:underline">Contact support</a>
    </nav>
    <p class="text-xs text-fg-subtle">Suspension, closure, and plan changes are handled by platform support.</p>
</x-layouts.app>
