{{-- Audit-trail table shared by tenant and platform consoles. --}}
@props(['activities', 'timezone', 'showOrganization' => false])

@if ($activities->isEmpty())
    <x-empty-state title="No audit entries match" description="Adjust the filters or widen the date range." />
@else
    <x-data-table :paginator="$activities" caption="Audit trail">
        <thead>
            <tr>
                <th scope="col">When</th>
                <th scope="col">Event</th>
                <th scope="col" class="hidden md:table-cell">Actor</th>
                @if ($showOrganization)<th scope="col" class="hidden lg:table-cell">Organisation</th>@endif
                <th scope="col" class="hidden xl:table-cell">Details</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($activities as $activity)
                <tr>
                    <td class="whitespace-nowrap text-xs text-fg-muted">
                        <time datetime="{{ $activity->created_at->toIso8601String() }}">{{ $activity->created_at->timezone($timezone)->format('D j M Y') }}<br>{{ $activity->created_at->timezone($timezone)->format('H:i:s') }}</time>
                    </td>
                    <td>
                        <p class="font-medium text-fg">{{ $activity->eventLabel() }}</p>
                        <p class="font-mono text-[0.7rem] text-fg-subtle">{{ $activity->event }}</p>
                    </td>
                    <td class="hidden md:table-cell">
                        @if ($activity->causer)
                            <p class="font-medium text-fg">{{ $activity->causer->name }}</p>
                            <p class="truncate text-xs text-fg-subtle">{{ $activity->causer->email }}</p>
                        @else
                            <span class="text-xs text-fg-subtle">System</span>
                        @endif
                    </td>
                    @if ($showOrganization)
                        <td class="hidden lg:table-cell">{{ $activity->organization?->name ?? '— Platform —' }}</td>
                    @endif
                    <td class="hidden max-w-sm xl:table-cell">
                        @php $props = collect($activity->properties ?? [])->except(['ip', 'user_agent', 'token', 'password']); @endphp
                        @if ($props->isEmpty())
                            <span class="text-xs text-fg-subtle">—</span>
                        @else
                            <details class="group">
                                <summary class="cursor-pointer text-xs text-primary hover:underline">{{ $props->count() }} {{ $props->count() === 1 ? 'field' : 'fields' }}</summary>
                                <dl class="mt-2 space-y-1 text-xs">
                                    @foreach ($props as $key => $value)
                                        <div class="flex gap-2"><dt class="shrink-0 font-medium text-fg-muted">{{ $key }}</dt><dd class="min-w-0 truncate font-mono text-fg">{{ is_scalar($value) || $value === null ? var_export($value, true) : json_encode($value) }}</dd></div>
                                    @endforeach
                                </dl>
                            </details>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </x-data-table>
@endif
