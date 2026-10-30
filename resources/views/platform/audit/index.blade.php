<x-layouts.app title="Audit trail" wide>
    <x-page-header title="Audit trail" description="Every audited change across the platform and all organisations, including actions taken from this console." eyebrow="Security">
        <x-button :href="route('admin.audit.logins')" variant="secondary" size="sm"><x-icon name="login" class="size-4" /> Sign-in history</x-button>
    </x-page-header>

    <x-filter-bar :action="route('admin.audit.index')" :reset="route('admin.audit.index')">
        <x-form.inline-search placeholder="Actor name, e-mail, or description…" />
        <x-form.inline-select name="organization" label="Organisation" :options="$organizations" />
        <x-form.inline-select name="event" label="Event" :options="$events" />
        <label class="flex flex-col gap-1 text-xs font-medium text-fg-muted"><span>From</span><input type="date" name="from" value="{{ $filters['from'] }}" class="h-9 rounded-lg border border-edge bg-surface px-2.5 text-sm text-fg"></label>
        <label class="flex flex-col gap-1 text-xs font-medium text-fg-muted"><span>To</span><input type="date" name="to" value="{{ $filters['to'] }}" class="h-9 rounded-lg border border-edge bg-surface px-2.5 text-sm text-fg"></label>
    </x-filter-bar>

    <x-audit.activity-table :activities="$activities" :timezone="$timezone" show-organization />
</x-layouts.app>
