<x-layouts.app :title="$module['label']">
    <x-page-header :title="$module['label']" :description="$module['description']" eyebrow="Planned module" />

    <x-coming-soon :module="$module">
        <x-button :href="route('admin.dashboard')" variant="secondary" size="sm"><x-icon name="home" class="size-4" /> Back to overview</x-button>
    </x-coming-soon>
</x-layouts.app>
