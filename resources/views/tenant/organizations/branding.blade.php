<x-layouts.app title="Branding">
    <x-page-header title="Branding" description="Logo, colours, and login copy for this organisation's workspace. Save a draft, preview it, then publish." eyebrow="Administration" />
    <x-workspace.settings-tabs :organization="$organization" current="branding" />
    <x-workspace.branding-panel :organization="$organization" :record="$record" :draft="$draft" :preview="$preview" :theme-modes="$themeModes" :fonts="$fonts" :published="$published" />
</x-layouts.app>
