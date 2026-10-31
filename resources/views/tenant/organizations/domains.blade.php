<x-layouts.app title="Workspace domains">
    <x-page-header title="Workspace domains" description="The hostname people type to reach this organisation. Aliases resolve to the same workspace. Custom domains stay inactive until DNS is verified." eyebrow="Administration" />
    <x-workspace.settings-tabs :organization="$organization" current="domains" />
    <x-workspace.domains-panel :organization="$organization" :domains="$domains" :base-domain="$baseDomain" :types="$types" />
</x-layouts.app>
