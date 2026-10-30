<x-layouts.app title="Audit trail" wide>
    <x-page-header title="Audit trail" description="Who changed what, and when, across your organisation. Entries are written by the system and cannot be edited. Sign-in attempts are tracked separately in sign-in history.">
        <x-button :href="route('org.audit.logins')" variant="secondary" size="sm"><x-icon name="login" class="size-4" /> Sign-in history</x-button>
    </x-page-header>

    <x-filter-bar :action="route('org.audit.index')" :reset="route('org.audit.index')">
        <x-form.inline-search placeholder="Actor name, e-mail, or description…" />
        <x-form.inline-select name="event" label="Event" :options="$events" />
        <label class="flex flex-col gap-1 text-xs font-medium text-fg-muted"><span>From</span><input type="date" name="from" value="{{ $filters['from'] }}" class="h-9 rounded-lg border border-edge bg-surface px-2.5 text-sm text-fg"></label>
        <label class="flex flex-col gap-1 text-xs font-medium text-fg-muted"><span>To</span><input type="date" name="to" value="{{ $filters['to'] }}" class="h-9 rounded-lg border border-edge bg-surface px-2.5 text-sm text-fg"></label>
    </x-filter-bar>

    <x-audit.activity-table :activities="$activities" :timezone="$timezone" />
</x-layouts.app>
