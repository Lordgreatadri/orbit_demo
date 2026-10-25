<x-layouts.app title="Organisation settings">
    <x-page-header title="Organisation settings" description="Profile and contact details for {{ $organization->name }}. Branding and additional settings arrive in later stages.">
        <x-badge :tone="$organization->isActive() ? 'success' : 'warning'">{{ $organization->status->label() }}</x-badge>
    </x-page-header>

    <x-card>
        <form method="POST" action="{{ route('app.organizations.update', $organization) }}" class="space-y-6" x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            @method('PUT')

            <fieldset class="space-y-5" @disabled(! $canUpdate)>
                <x-form.input name="name" label="Organisation name" :value="$organization->name" required />
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.select name="type" label="Organisation type" :options="$organizationTypes" :value="$organization->type->value" required />
                    <x-form.select name="timezone" label="Time zone" :options="$timezones" :value="$organization->timezone" required />
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.input name="contact_email" label="Contact e-mail" type="email" :value="$organization->contact_email" required />
                    <x-form.input name="contact_phone" label="Contact phone" type="tel" :value="$organization->contact_phone" placeholder="+233201234567" />
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.input name="address_line" label="Address" :value="$organization->address_line" />
                    <x-form.input name="city" label="City" :value="$organization->city" />
                </div>
            </fieldset>

            <dl class="grid gap-3 rounded-lg border border-edge bg-surface-muted p-4 text-sm sm:grid-cols-2">
                <div><dt class="text-fg-muted">Organisation reference</dt><dd class="font-mono text-xs text-fg">{{ $organization->uuid }}</dd></div>
                <div><dt class="text-fg-muted">Registered</dt><dd class="text-fg">{{ $organization->created_at->timezone($organization->timezone)->toDayDateTimeString() }}</dd></div>
            </dl>

            @if ($canUpdate)
                <x-button type="submit" x-bind:disabled="submitting">Save changes</x-button>
            @else
                <p class="text-sm text-fg-muted">You can view these settings but only an organisation administrator can change them.</p>
            @endif
        </form>
    </x-card>
</x-layouts.app>
