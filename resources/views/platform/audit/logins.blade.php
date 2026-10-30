<x-layouts.app title="Sign-in history" wide>
    <x-page-header title="Sign-in history" description="Platform-wide sign-ins, sign-outs, failed attempts, and lockouts. Times are shown in the platform time zone ({{ $timezone }})." eyebrow="Security">
        <x-button :href="route('admin.audit.index')" variant="secondary" size="sm"><x-icon name="document-check" class="size-4" /> Audit trail</x-button>
    </x-page-header>

    <x-audit.login-table :events="$events" :summary="$summary" :timezone="$timezone" show-organization user-route="admin.users.show">
        <x-filter-bar :action="route('admin.audit.logins')" :reset="route('admin.audit.logins')">
            <x-form.inline-search placeholder="E-mail or IP address…" />
            <x-form.inline-select name="organization" label="Organisation" :options="$organizations" />
            <x-form.inline-select name="event" label="Event" :options="$types" />
            <label class="flex flex-col gap-1 text-xs font-medium text-fg-muted"><span>From</span><input type="date" name="from" value="{{ $filters['from'] }}" class="h-9 rounded-lg border border-edge bg-surface px-2.5 text-sm text-fg"></label>
            <label class="flex flex-col gap-1 text-xs font-medium text-fg-muted"><span>To</span><input type="date" name="to" value="{{ $filters['to'] }}" class="h-9 rounded-lg border border-edge bg-surface px-2.5 text-sm text-fg"></label>
        </x-filter-bar>
    </x-audit.login-table>
</x-layouts.app>
