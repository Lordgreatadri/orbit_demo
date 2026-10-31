<x-layouts.app title="Staff register" wide>
    <x-page-header title="Staff register" description="People records for your organisation: staff number, department, work type, and status. A staff record is separate from a login account and may be linked to one.">
        @if ($canCreate)
            <x-button :href="route('org.staff.create')" size="sm"><x-icon name="user-plus" class="size-4" /> Add staff member</x-button>
        @endif
    </x-page-header>
    <x-filter-bar :action="route('org.staff.index')" :reset="route('org.staff.index')">
        <x-form.inline-search placeholder="Name, staff number, e-mail…" />
        <x-form.inline-select name="department" label="Department" :options="$departments" />
        <x-form.inline-select name="work_type" label="Work type" :options="$workTypes" />
        <x-form.inline-select name="status" label="Status" :options="$statuses" />
    </x-filter-bar>

    @if ($staff->isEmpty())
        <x-empty-state title="No staff records match" :description="request()->query() ? 'Try clearing a filter.' : 'Add the first staff member to start building your register.'">
            @if ($canCreate && ! request()->query())
                <x-button :href="route('org.staff.create')" size="sm">Add staff member</x-button>
            @endif
        </x-empty-state>
    @else
        <x-data-table :paginator="$staff" caption="Staff members">
            <thead>
                <tr>
                    <x-data-table.sort column="name" :sort="$sort">Staff member</x-data-table.sort>
                    <x-data-table.sort column="number" :sort="$sort">Staff no.</x-data-table.sort>
                    <th scope="col" class="hidden md:table-cell">Department</th>
                    <th scope="col" class="hidden lg:table-cell">Location</th>
                    <x-data-table.sort column="type" :sort="$sort" class="hidden sm:table-cell">Work type</x-data-table.sort>
                    <x-data-table.sort column="status" :sort="$sort">Status</x-data-table.sort>
                    <th scope="col" class="hidden xl:table-cell">Account</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($staff as $member)
                    <tr>
                        <td>
                            <a href="{{ route('org.staff.show', $member) }}" class="font-medium text-fg hover:text-primary hover:underline">{{ $member->name }}</a>
                            @if ($member->job_title)<p class="text-xs text-fg-subtle">{{ $member->job_title }}</p>@endif
                        </td>
                        <td class="font-mono text-xs">{{ $member->staff_number }}</td>
                        <td class="hidden md:table-cell">{{ $member->department?->name ?? '—' }}</td>
                        <td class="hidden lg:table-cell">{{ $member->primaryLocation?->name ?? '—' }}</td>
                        <td class="hidden sm:table-cell">{{ $member->work_type->label() }}</td>
                        <td><x-badge :tone="$member->status->tone()">{{ $member->status->label() }}</x-badge></td>
                        <td class="hidden xl:table-cell">
                            @if ($member->user)
                                <x-badge :tone="$member->user->status->tone()">Linked</x-badge>
                            @else
                                <span class="text-xs text-fg-subtle">No account</span>
                            @endif
                        </td>
                        <td class="text-right">
                            <x-button :href="route('org.staff.show', $member)" variant="ghost" size="sm" aria-label="View {{ $member->name }}"><x-icon name="chevron-right" class="size-4" /></x-button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-data-table>
        <p class="text-xs text-fg-subtle">Showing {{ $staff->firstItem() }}–{{ $staff->lastItem() }} of {{ number_format($staff->total()) }}.</p>
    @endif
</x-layouts.app>
