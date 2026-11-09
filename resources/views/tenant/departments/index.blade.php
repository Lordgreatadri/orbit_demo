<x-layouts.app title="Departments">
    <x-page-header title="Departments" description="Group staff records the way your organisation is structured. Departments are referenced by staff records and, later, by schedules and reports.">
        @if ($canManage)
            <x-button :href="route('org.departments.create')" size="sm"><x-icon name="plus" class="size-4" /> New department</x-button>
        @endif
    </x-page-header>
    @error('department')<x-alert type="danger">{{ $message }}</x-alert>@enderror

    @if ($departments->isEmpty())
        <x-empty-state title="No departments yet" description="Create the first department to start organising your staff register.">
            @if ($canManage)
                <x-button :href="route('org.departments.create')" size="sm">New department</x-button>
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
                                @can('update', $department)
                                    <a href="{{ route('org.departments.edit', $department) }}" class="inline-flex size-9 items-center justify-center rounded-lg text-fg-muted hover:bg-surface-muted hover:text-primary focus-visible:outline-2" aria-label="Edit {{ $department->name }}">
                                        <x-icon name="pencil" class="size-4" />
                                    </a>
                                @endcan
                                @can('delete', $department)
                                    <button type="button" class="inline-flex size-9 items-center justify-center rounded-lg text-danger hover:bg-danger-soft focus-visible:outline-2" aria-label="Delete {{ $department->name }}" onclick="this.nextElementSibling.showModal()">
                                        <x-icon name="trash" class="size-4" />
                                    </button>
                                    <dialog class="w-[min(100%,24rem)] rounded-xl border border-edge bg-surface p-0 text-fg shadow-lift backdrop:bg-black/50">
                                        <form method="POST" action="{{ route('org.departments.destroy', $department) }}" class="space-y-4 p-5">
                                            @csrf
                                            @method('DELETE')
                                            <h2 class="text-base font-semibold text-fg">Delete {{ $department->name }}?</h2>
                                            <p class="text-sm text-fg-muted">This cannot be undone. If staff still belong to this department, the delete is refused and the department stays in the register.</p>
                                            <div class="flex justify-end gap-2">
                                                <x-button type="button" variant="ghost" size="sm" onclick="this.closest('dialog').close()">Cancel</x-button>
                                                <x-button type="submit" variant="danger" size="sm">Delete department</x-button>
                                            </div>
                                        </form>
                                    </dialog>
                                @endcan
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </x-data-table>
    @endif
</x-layouts.app>
