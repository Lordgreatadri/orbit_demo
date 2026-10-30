<x-layouts.app title="Permission catalogue" wide>
    <x-page-header title="Permission catalogue" description="Every permission the application checks, grouped by module. System permissions come from the application and keep their names; custom permissions can be added for roles to carry, and take effect once a feature checks them." eyebrow="Access control">
        <x-button :href="route('admin.roles.index')" variant="secondary" size="sm"><x-icon name="shield-check" class="size-4" /> Roles</x-button>
        @if ($canManage)
            <x-button type="button" size="sm" x-data @click="$dispatch('open-permission-form', null)"><x-icon name="plus" class="size-4" /> New permission</x-button>
        @endif
    </x-page-header>

    @error('permission')<x-alert type="danger">{{ $message }}</x-alert>@enderror

    @foreach ($permissions as $module => $modulePermissions)
        <section class="space-y-3" aria-labelledby="perm-{{ $module }}">
            <h2 id="perm-{{ $module }}" class="flex items-center gap-2 text-base font-semibold text-fg">
                {{ $moduleLabels[$module] ?? \Illuminate\Support\Str::headline($module) }}
                <span class="text-sm font-normal text-fg-subtle">{{ $modulePermissions->count() }}</span>
                @if ($module === 'platform')<x-badge tone="info">Platform roles only</x-badge>@endif
            </h2>
            <x-data-table caption="{{ $moduleLabels[$module] ?? $module }} permissions">
                <thead>
                    <tr>
                        <th scope="col">Permission</th>
                        <th scope="col" class="hidden md:table-cell">Description</th>
                        <th scope="col">Roles</th>
                        <th scope="col" class="hidden sm:table-cell">Kind</th>
                        @if ($canManage)<th scope="col"><span class="sr-only">Actions</span></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($modulePermissions as $permission)
                        <tr>
                            <td class="font-mono text-xs text-fg">{{ $permission->name }}</td>
                            <td class="hidden max-w-lg text-fg-muted md:table-cell">{{ $permission->description ?? '—' }}</td>
                            <td class="tabular-nums">{{ $permission->roles_count }}</td>
                            <td class="hidden sm:table-cell"><x-badge :tone="$permission->is_system ? 'info' : 'neutral'">{{ $permission->is_system ? 'System' : 'Custom' }}</x-badge></td>
                            @if ($canManage)
                                <td class="text-right whitespace-nowrap">
                                    <x-button type="button" variant="ghost" size="sm" x-data aria-label="Edit {{ $permission->name }}" @click="$dispatch('open-permission-form', @js(['uuid' => $permission->uuid, 'name' => $permission->name, 'description' => $permission->description, 'is_system' => $permission->is_system]))"><x-icon name="pencil" class="size-4" /></x-button>
                                    @can('delete', $permission)
                                        <x-confirm-form :action="route('admin.permissions.destroy', $permission)" method="DELETE" :confirm="'Delete permission '.$permission->name.'?'">
                                            <x-button type="submit" variant="ghost" size="sm" class="text-danger hover:bg-danger-soft" aria-label="Delete {{ $permission->name }}"><x-icon name="trash" class="size-4" /></x-button>
                                        </x-confirm-form>
                                    @endcan
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </x-data-table>
        </section>
    @endforeach

    @if ($canManage)
        <div
            x-data="permissionForm(@js(old()), @js($errors->any()), @js(route('admin.permissions.store')), @js(route('admin.permissions.update', ['permission' => '__UUID__'])))"
            x-on:open-permission-form.window="open($event.detail)"
            x-show="visible" x-cloak
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-4 backdrop-blur-[2px] sm:items-center"
            role="dialog" aria-modal="true" aria-labelledby="permission-form-title"
            @keydown.escape.window="visible = false"
        >
            <form method="POST" :action="action" @click.outside="visible = false" class="card w-full max-w-lg space-y-5 p-6">
                @csrf
                <template x-if="uuid"><input type="hidden" name="_method" value="PUT"></template>
                <input type="hidden" name="_uuid" :value="uuid ?? ''">
                <div class="flex items-start justify-between gap-3">
                    <h2 id="permission-form-title" class="text-lg font-semibold text-fg" x-text="uuid ? 'Edit permission' : 'New permission'"></h2>
                    <button type="button" class="rounded-lg p-1.5 text-fg-muted hover:bg-surface-muted hover:text-fg" @click="visible = false" aria-label="Close"><x-icon name="x" class="size-5" /></button>
                </div>
                <x-form.input name="name" label="Name" required hint="module.action, e.g. reports.export. The module prefix groups it in the catalogue." class="font-mono" x-model="form.name" x-bind:readonly="isSystem" />
                <p x-show="isSystem" x-cloak class="text-xs text-fg-subtle">System permission: the name is fixed because application code checks it.</p>
                <x-form.textarea name="description" label="Description" rows="2" required x-model="form.description" />
                <div class="flex justify-end gap-3 pt-1">
                    <x-button type="button" variant="ghost" @click="visible = false">Cancel</x-button>
                    <x-button type="submit" x-text="uuid ? 'Save changes' : 'Create permission'"></x-button>
                </div>
            </form>
        </div>

        @push('scripts')
            <script>
                document.addEventListener('alpine:init', () => {
                    Alpine.data('permissionForm', (old, hasErrors, storeUrl, updateTemplate) => ({
                        visible: hasErrors,
                        uuid: old._uuid ?? null,
                        isSystem: false,
                        form: { name: old.name ?? '', description: old.description ?? '' },
                        get action() { return this.uuid ? updateTemplate.replace('__UUID__', this.uuid) : storeUrl; },
                        open(detail) {
                            this.uuid = detail?.uuid ?? null;
                            this.isSystem = !!detail?.is_system;
                            this.form = { name: detail?.name ?? '', description: detail?.description ?? '' };
                            this.visible = true;
                            this.$nextTick(() => this.$root.querySelector(this.isSystem ? 'textarea[name=description]' : 'input[name=name]')?.focus());
                        },
                    }));
                });
            </script>
        @endpush
    @endif
</x-layouts.app>
