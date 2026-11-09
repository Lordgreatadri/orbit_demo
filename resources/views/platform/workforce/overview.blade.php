<x-layouts.app title="Workforce overview" wide>
    <x-page-header title="Workforce overview" description="Counts for today in each organisation's time zone. Member names are not shown here." />

    <x-data-table :paginator="$organizations" caption="Organisation workforce counts">
        <thead>
            <tr>
                <th scope="col">Organisation</th>
                <th scope="col">Today</th>
                <th scope="col">Open field</th>
                <th scope="col">Leave pending</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($organizations as $organization)
                @php
                    $rows = $counts->get($organization->getKey(), collect());
                    $value = fn ($status) => (int) optional($rows->first(fn ($row) => ($row->status->value ?? $row->status) === $status))->total;
                @endphp
                <tr>
                    <td class="font-medium text-fg">{{ $organization->name }}</td>
                    <td>{{ $value('present') }} present · {{ $value('late') }} late</td>
                    <td>{{ number_format((int) ($openField[$organization->getKey()] ?? 0)) }}</td>
                    <td>{{ number_format((int) ($pendingLeave[$organization->getKey()] ?? 0)) }}</td>
                </tr>
            @endforeach
        </tbody>
    </x-data-table>
</x-layouts.app>
