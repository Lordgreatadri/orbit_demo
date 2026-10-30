<x-layouts.app title="Roles & permissions">
    <x-page-header title="Roles &amp; permissions" description="What each role grants in your organisation. Role definitions are maintained centrally by the platform so every tenant gets the same checks and balances; you assign and revoke them from User accounts.">
        <x-button :href="route('org.users.index')" variant="secondary" size="sm"><x-icon name="key" class="size-4" /> Manage user access</x-button>
    </x-page-header>

    <div class="grid gap-5 md:grid-cols-2">
        @foreach ($roles as $role)
            <x-card :title="$role->label()" :description="$role->description" :padding="false">
                <x-slot:actions>
                    <x-badge tone="neutral">{{ $usage[$role->getKey()] ?? 0 }} {{ ($usage[$role->getKey()] ?? 0) === 1 ? 'member' : 'members' }}</x-badge>
                    @if (in_array($role->name, $grantable, true))
                        <x-badge tone="success">Assignable</x-badge>
                    @else
                        <x-badge tone="warning" title="Assigned by the platform only">Restricted</x-badge>
                    @endif
                </x-slot:actions>
                <div class="p-5">
                    @if ($role->permissions->isEmpty())
                        <p class="text-sm text-fg-muted">Self-service only: no permissions beyond the account's own data.</p>
                    @else
                        <dl class="space-y-3">
                            @foreach ($role->permissions->sortBy('name')->groupBy('module') as $module => $permissions)
                                <div>
                                    <dt class="text-xs font-semibold tracking-wide text-fg-subtle uppercase">{{ $moduleLabels[$module] ?? \Illuminate\Support\Str::headline($module) }}</dt>
                                    <dd class="mt-1 flex flex-wrap gap-1.5">
                                        @foreach ($permissions as $permission)
                                            <x-badge tone="neutral" :title="$permission->description">{{ $permission->name }}</x-badge>
                                        @endforeach
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif
                </div>
                <div class="border-t border-edge px-5 py-3 text-right">
                    <a href="{{ route('org.users.index', ['role' => $role->name]) }}" class="text-sm text-primary hover:underline">View members</a>
                </div>
            </x-card>
        @endforeach
    </div>
</x-layouts.app>
