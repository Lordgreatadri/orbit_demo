<x-layouts.app title="Branding · {{ $organization->name }}">
    <x-page-header :title="$organization->name" description="Platform view of published and draft branding." eyebrow="Organisation">
        <x-button :href="route('admin.organizations.show', $organization)" variant="ghost" size="sm">Overview</x-button>
    </x-page-header>
    <x-workspace.settings-tabs :organization="$organization" current="branding" />
    <x-workspace.branding-panel :organization="$organization" :record="$record" :draft="$draft" :preview="$preview" :theme-modes="$themeModes" :fonts="$fonts" :published="$published" />
</x-layouts.app>
