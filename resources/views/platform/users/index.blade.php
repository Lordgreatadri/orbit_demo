<x-layouts.app title="All user accounts" wide>
    <x-page-header title="All user accounts" description="Every login identity across the platform and all organisations. Opening this list is recorded in the audit trail." eyebrow="Access control">
        @if ($canCreate)
            <x-button :href="route('admin.users.create')" size="sm"><x-icon name="user-plus" class="size-4" /> New platform account</x-button>
        @endif
    </x-page-header>

    <x-filter-bar :action="route('admin.users.index')" :reset="route('admin.users.index')">
        <x-form.inline-search placeholder="Name or e-mail…" />
        <x-form.inline-select name="kind" label="Account kind" :options="['tenant' => 'Organisation accounts', 'platform' => 'Platform accounts']" />
        <x-form.inline-select name="organization" label="Organisation" :options="$organizations" />
        <x-form.inline-select name="role" label="Role" :options="$roles" />
        <x-form.inline-select name="status" label="Status" :options="$statuses" />
    </x-filter-bar>

    @if ($users->isEmpty())
        <x-empty-state title="No accounts match" />
    @else
        <x-data-table :paginator="$users" caption="User accounts">
            <thead>
                <tr>
                    <th scope="col">Account</th>
                    <th scope="col" class="hidden md:table-cell">Organisation</th>
                    <th scope="col" class="hidden lg:table-cell">Roles</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="hidden sm:table-cell">Last sign-in</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $account)
                    <tr>
                        <td><x-user-chip :user="$account" :href="route('admin.users.show', $account)" /></td>
                        <td class="hidden md:table-cell">
                            @if ($account->organization)
                                @can('platform.organizations.view')
                                    <a href="{{ route('admin.organizations.show', $account->organization) }}" class="text-fg hover:text-primary hover:underline">{{ $account->organization->name }}</a>
                                @else
                                    {{ $account->organization->name }}
                                @endcan
                            @else
                                <x-badge tone="info">Platform</x-badge>
                            @endif
                        </td>
                        <td class="hidden lg:table-cell">
                            <div class="flex flex-wrap gap-1">
                                @forelse ($account->roles as $role)
                                    <x-badge :tone="$role->isPlatform() ? 'info' : 'accent'">{{ $role->label() }}</x-badge>
                                @empty
                                    <span class="text-xs text-fg-subtle">None</span>
                                @endforelse
                            </div>
                        </td>
                        <td><x-badge :tone="$account->status->tone()">{{ $account->status->label() }}</x-badge></td>
                        <td class="hidden text-xs text-fg-muted sm:table-cell">{{ $account->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                        <td class="text-right"><x-button :href="route('admin.users.show', $account)" variant="ghost" size="sm" aria-label="Open {{ $account->name }}"><x-icon name="chevron-right" class="size-4" /></x-button></td>
                    </tr>
                @endforeach
            </tbody>
        </x-data-table>
        <p class="text-xs text-fg-subtle">Showing {{ $users->firstItem() }}–{{ $users->lastItem() }} of {{ number_format($users->total()) }}.</p>
    @endif
</x-layouts.app>
