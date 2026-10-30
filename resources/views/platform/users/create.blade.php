<x-layouts.app title="New platform account">
    <x-page-header title="New platform account" description="Platform accounts belong to no organisation and are never created through public registration. The person receives an e-mail link to set their own password." eyebrow="Access control">
        <x-button :href="route('admin.users.index')" variant="ghost" size="sm"><x-icon name="chevron-left" class="size-4" /> All accounts</x-button>
    </x-page-header>

    <form method="POST" action="{{ route('admin.users.store') }}" class="mx-auto max-w-2xl space-y-6">
        @csrf
        <x-card title="Identity">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="first_name" label="First name" required autocomplete="off" />
                <x-form.input name="last_name" label="Last name" required autocomplete="off" />
                <x-form.input name="email" type="email" label="E-mail address" required autocomplete="off" hint="Must be unique across the platform." />
                <x-form.input name="phone" type="tel" label="Phone" placeholder="+233201234567" />
            </div>
        </x-card>
        <x-card title="Role" description="Only platform-scoped roles can be assigned here.">
            <x-form.select name="role" label="Platform role" :options="$roles" required placeholder="Choose a role" />
        </x-card>
        <x-alert type="info">The account is created active with a random, unknown password. A password-set link is e-mailed immediately; the invitation flow for organisation users is separate and handled by each organisation's administrators.</x-alert>
        <div class="flex justify-end gap-3">
            <x-button :href="route('admin.users.index')" variant="ghost">Cancel</x-button>
            <x-button type="submit">Create platform account</x-button>
        </div>
    </form>
</x-layouts.app>
