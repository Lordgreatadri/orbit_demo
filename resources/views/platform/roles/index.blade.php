<x-layouts.app title="Roles" wide>
    <x-page-header title="Roles" description="Roles are global: every organisation sees the same tenant roles, which keeps checks and balances consistent across the platform. System roles cannot be renamed or deleted; their permission sets can be tuned." eyebrow="Access control">
        <x-button :href="route('admin.permissions.index')" variant="secondary" size="sm"><x-icon name="key" class="size-4" /> Permission catalogue</x-button>
        @if ($canCreate)
            <x-button :href="route('admin.roles.create')" size="sm"><x-icon name="plus" class="size-4" /> New role</x-button>
        @endif
    </x-page-header>

    @error('role')<x-alert type="danger">{{ $message }}</x-alert>@enderror

    @foreach (['tenant' => ['Organisation roles', 'Assignable to accounts that belong to an organisation.'], 'platform' => ['Platform roles', 'Assignable only to platform accounts. May hold platform.* permissions.']] as $scope => [$heading, $blurb])
        <section class="space-y-3" aria-labelledby="roles-{{ $scope }}">
            <div>
                <h2 id="roles-{{ $scope }}" class="text-base font-semibold text-fg">{{ $heading }}</h2>
                <p class="text-sm text-fg-muted">{{ $blurb }}</p>
            </div>
            @if (($roles[$scope] ?? collect())->isEmpty())
                <x-empty-state title="No {{ strtolower($heading) }} defined" />
            @else
                <x-data-table caption="{{ $heading }}">
                    <thead>
                        <tr>
                            <th scope="col">Role</th>
                            <th scope="col" class="hidden md:table-cell">Description</th>
                            <th scope="col">Permissions</th>
                            <th scope="col">Users</th>
                            <th scope="col" class="hidden sm:table-cell">Kind</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($roles[$scope] as $role)
                            <tr>
                                <td>
                                    <p class="font-medium text-fg">{{ $role->label() }}</p>
                                    <p class="font-mono text-[0.7rem] text-fg-subtle">{{ $role->name }}</p>
                                </td>
                                <td class="hidden max-w-md text-fg-muted md:table-cell">{{ $role->description ?? '—' }}</td>
                                <td class="tabular-nums">{{ $role->permissions_count }}</td>
                                <td class="tabular-nums">{{ $role->users_count }}</td>
                                <td class="hidden sm:table-cell"><x-badge :tone="$role->is_system ? 'info' : 'neutral'">{{ $role->is_system ? 'System' : 'Custom' }}</x-badge></td>
                                <td class="text-right whitespace-nowrap">
                                    @can('update', $role)
                                        <x-button :href="route('admin.roles.edit', $role)" variant="ghost" size="sm" aria-label="Edit {{ $role->label() }}"><x-icon name="pencil" class="size-4" /></x-button>
                                    @endcan
                                    @can('delete', $role)
                                        <x-confirm-form :action="route('admin.roles.destroy', $role)" method="DELETE" :confirm="'Delete role '.$role->label().'?'">
                                            <x-button type="submit" variant="ghost" size="sm" class="text-danger hover:bg-danger-soft" aria-label="Delete {{ $role->label() }}"><x-icon name="trash" class="size-4" /></x-button>
                                        </x-confirm-form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-data-table>
            @endif
        </section>
    @endforeach
</x-layouts.app>
