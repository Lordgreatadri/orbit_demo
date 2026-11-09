@php $editing = $department->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit '.$department->name : 'New department'">
    <x-page-header
        :title="$editing ? 'Edit department' : 'New department'"
        :description="$editing ? $department->name : 'Add a department staff records can be placed in.'"
        eyebrow="Departments"
    >
        <x-button :href="route('org.departments.index')" variant="ghost" size="sm"><x-icon name="chevron-left" class="size-4" /> Back</x-button>
    </x-page-header>

    @error('department')
        <x-alert type="danger">{{ $message }}</x-alert>
    @enderror

    <form method="POST" action="{{ $editing ? route('org.departments.update', $department) : route('org.departments.store') }}" class="space-y-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-card title="Department">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="name" label="Name" :value="$department->name" required />
                <x-form.input name="code" label="Code" :value="$department->code" hint="Optional short code, e.g. FIN or OPS." class="font-mono uppercase" />
                <div class="sm:col-span-2">
                    <x-form.textarea name="description" label="Description" :value="$department->description" rows="3" />
                </div>
            </div>
            <input type="hidden" name="is_active" value="0">
            <label class="mt-5 flex items-center gap-3 text-sm text-fg">
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    @checked((string) old('is_active', $editing ? (int) $department->is_active : 1) === '1')
                    class="size-4 rounded border-edge-strong text-primary"
                >
                Active (available when creating staff records)
            </label>
        </x-card>

        <div class="flex flex-wrap items-center justify-end gap-3">
            <x-button :href="route('org.departments.index')" variant="ghost">Cancel</x-button>
            <x-button type="submit">{{ $editing ? 'Save changes' : 'Create department' }}</x-button>
        </div>
    </form>

    @if ($editing)
        @can('delete', $department)
            <x-card id="delete-department" title="Delete department" description="Deletion is permanent. It is refused while any staff record still belongs to this department.">
                @if (($department->staff_members_count ?? 0) > 0)
                    <x-alert type="warning" title="Staff are still assigned">
                        Move the {{ number_format($department->staff_members_count) }} {{ $department->staff_members_count === 1 ? 'person' : 'people' }} to another department, or mark this department inactive. You need the departments permission to delete it, and that permission is not enough while staff remain.
                    </x-alert>
                @else
                    <form method="POST" action="{{ route('org.departments.destroy', $department) }}">
                        @csrf
                        @method('DELETE')
                        <x-button type="submit" variant="danger" size="sm"><x-icon name="trash" class="size-4" /> Delete department</x-button>
                    </form>
                @endif
            </x-card>
        @endcan
    @endif
</x-layouts.app>
