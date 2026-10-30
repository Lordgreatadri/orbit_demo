<x-layouts.app :title="$module['label']">
    <x-page-header :title="$module['label']" :description="$module['description']" eyebrow="Planned module" />

    <x-coming-soon :module="$module">
        <x-button :href="route('org.dashboard')" variant="secondary" size="sm"><x-icon name="home" class="size-4" /> Back to dashboard</x-button>
        <x-button :href="route('guide')" variant="ghost" size="sm"><x-icon name="book" class="size-4" /> Read the user guide</x-button>
    </x-coming-soon>

    <x-card title="What you can prepare now" description="Data entered today is used by this module when it ships.">
        <ul class="grid gap-3 text-sm sm:grid-cols-3">
            <li class="card-hover rounded-xl border border-edge bg-surface p-3"><a href="{{ route('org.staff.index') }}" class="font-medium text-fg hover:text-primary">Staff register</a><p class="mt-1 text-xs text-fg-muted">Everyone whose attendance will be tracked.</p></li>
            <li class="card-hover rounded-xl border border-edge bg-surface p-3"><a href="{{ route('org.locations.index') }}" class="font-medium text-fg hover:text-primary">Locations</a><p class="mt-1 text-xs text-fg-muted">Offices and field areas with optional geofences.</p></li>
            <li class="card-hover rounded-xl border border-edge bg-surface p-3"><a href="{{ route('org.departments.index') }}" class="font-medium text-fg hover:text-primary">Departments</a><p class="mt-1 text-xs text-fg-muted">Grouping used in reports and approvals.</p></li>
        </ul>
    </x-card>
</x-layouts.app>
