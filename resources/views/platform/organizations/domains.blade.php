<x-layouts.app title="Domains · {{ $organization->name }}">
    <x-page-header :title="$organization->name" description="Platform view of registered hostnames. Changes are audited." eyebrow="Organisation">
        <x-button :href="route('admin.organizations.show', $organization)" variant="ghost" size="sm">Overview</x-button>
    </x-page-header>
    <x-workspace.settings-tabs :organization="$organization" current="domains" />
    <x-workspace.domains-panel :organization="$organization" :domains="$domains" :base-domain="$baseDomain" :types="$types" />
</x-layouts.app>
