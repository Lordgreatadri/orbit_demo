<x-layouts.app title="Departments">
    <x-page-header title="Departments" description="Group staff records the way your organisation is structured. Departments are referenced by staff records and, later, by schedules and reports.">
        @if ($canManage)
            <x-button type="button" size="sm" x-data @click="$dispatch('open-department-form', null)"><x-icon name="plus" class="size-4" /> New department</x-button>
        @endif
    </x-page-header>
    @error('department')<x-alert type="danger">{{ $message }}</x-alert>@enderror

    @if ($departments->isEmpty())
        <x-empty-state title="No departments yet" description="Create the first department to start organising your staff register.">
            @if ($canManage)
                <x-button type="button" size="sm" x-data @click="$dispatch('open-department-form', null)">New department</x-button>
            @endif
        </x-empty-state>
    @else
        <x-data-table :paginator="$departments" caption="Departments">
            <thead>
                <tr>
                    <th scope="col">Department</th>
                    <th scope="col" class="hidden sm:table-cell">Code</th>
                    <th scope="col">Active staff</th>
                    <th scope="col">Status</th>
                    @if ($canManage)<th scope="col"><span class="sr-only">Actions</span></th>@endif
                </tr>
            </thead>
            <tbody>
                @foreach ($departments as $department)
                    <tr>
                        <td>
                            <a href="{{ route('org.staff.index', ['department' => $department->uuid]) }}" class="font-medium text-fg hover:text-primary hover:underline">{{ $department->name }}</a>
                            @if ($department->description)<p class="max-w-md truncate text-xs text-fg-subtle">{{ $department->description }}</p>@endif
                        </td>
                        <td class="hidden font-mono text-xs sm:table-cell">{{ $department->code ?? '—' }}</td>
                        <td class="tabular-nums">{{ number_format($department->staff_members_count) }}</td>
                        <td><x-badge :tone="$department->is_active ? 'success' : 'neutral'">{{ $department->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                        @if ($canManage)
                            <td class="text-right whitespace-nowrap">
                                <x-button type="button" variant="ghost" size="sm" x-data aria-label="Edit {{ $department->name }}" @click="$dispatch('open-department-form', @js(['uuid' => $department->uuid, 'name' => $department->name, 'code' => $department->code, 'description' => $department->description, 'is_active' => $department->is_active]))"><x-icon name="pencil" class="size-4" /></x-button>
                                <x-confirm-form :action="route('org.departments.destroy', $department)" method="DELETE" :confirm="'Delete department '.$department->name.'?'">
                                    <x-button type="submit" variant="ghost" size="sm" class="text-danger hover:bg-danger-soft" aria-label="Delete {{ $department->name }}"><x-icon name="trash" class="size-4" /></x-button>
                                </x-confirm-form>
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </x-data-table>
    @endif

    @if ($canManage)
        {{-- Create / edit dialog (one form, swapped by event payload) --}}
        <div
            x-data="departmentForm(@js(old()), @js($errors->any()), @js(route('org.departments.store')), @js(route('org.departments.update', ['department' => '__UUID__'])))"
            x-on:open-department-form.window="open($event.detail)"
            x-show="visible" x-cloak
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-4 backdrop-blur-[2px] sm:items-center"
            role="dialog" aria-modal="true" aria-labelledby="department-form-title"
            @keydown.escape.window="visible = false"
        >
            <form method="POST" :action="action" @click.outside="visible = false" class="card w-full max-w-lg space-y-5 p-6">
                @csrf
                <template x-if="uuid"><input type="hidden" name="_method" value="PUT"></template>
                <input type="hidden" name="_uuid" :value="uuid ?? ''">
                <div class="flex items-start justify-between gap-3">
                    <h2 id="department-form-title" class="text-lg font-semibold text-fg" x-text="uuid ? 'Edit department' : 'New department'"></h2>
                    <button type="button" class="rounded-lg p-1.5 text-fg-muted hover:bg-surface-muted hover:text-fg" @click="visible = false" aria-label="Close"><x-icon name="x" class="size-5" /></button>
                </div>
                <x-form.input name="name" label="Name" required x-model="form.name" />
                <x-form.input name="code" label="Code" hint="Optional short code, e.g. FIN or OPS." class="font-mono uppercase" x-model="form.code" />
                <x-form.textarea name="description" label="Description" rows="2" x-model="form.description" />
                <input type="hidden" name="is_active" value="0">
                <label class="flex items-center gap-3 text-sm text-fg">
                    <input type="checkbox" name="is_active" value="1" x-model="form.is_active" class="size-4 rounded border-edge-strong text-primary">
                    Active (available when creating staff records)
                </label>
                <div class="flex justify-end gap-3 pt-1">
                    <x-button type="button" variant="ghost" @click="visible = false">Cancel</x-button>
                    <x-button type="submit" x-text="uuid ? 'Save changes' : 'Create department'"></x-button>
                </div>
            </form>
        </div>

        @push('scripts')
            <script>
                document.addEventListener('alpine:init', () => {
                    Alpine.data('departmentForm', (old, hasErrors, storeUrl, updateTemplate) => ({
                        visible: hasErrors,
                        uuid: old._uuid ?? null,
                        form: { name: old.name ?? '', code: old.code ?? '', description: old.description ?? '', is_active: old.is_active === undefined ? true : old.is_active == 1 },
                        get action() { return this.uuid ? updateTemplate.replace('__UUID__', this.uuid) : storeUrl; },
                        open(detail) {
                            this.uuid = detail?.uuid ?? null;
                            this.form = { name: detail?.name ?? '', code: detail?.code ?? '', description: detail?.description ?? '', is_active: detail ? !!detail.is_active : true };
                            this.visible = true;
                            this.$nextTick(() => this.$root.querySelector('input[name=name]')?.focus());
                        },
                    }));
                });
            </script>
        @endpush
    @endif
</x-layouts.app>
