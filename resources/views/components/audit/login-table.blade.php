{{-- Sign-in history table shared by tenant and platform consoles. --}}
@props(['events', 'summary', 'timezone', 'showOrganization' => false, 'userRoute' => null])

<div class="grid grid-cols-3 gap-4">
    <x-stat label="Sign-ins today" :value="number_format($summary['today']['login'])" :hint="number_format($summary['week']['login']).' in 7 days'" icon="login" tone="success" />
    <x-stat label="Failed today" :value="number_format($summary['today']['failed'])" :hint="number_format($summary['week']['failed']).' in 7 days'" icon="alert" tone="warning" />
    <x-stat label="Lockouts today" :value="number_format($summary['today']['lockout'])" :hint="number_format($summary['week']['lockout']).' in 7 days'" icon="lock" tone="danger" />
</div>

{{ $slot }}

@if ($events->isEmpty())
    <x-empty-state title="No sign-in events match" description="Adjust the filters or widen the date range." />
@else
    <x-data-table :paginator="$events" caption="Sign-in history">
        <thead>
            <tr>
                <th scope="col">When</th>
                <th scope="col">Event</th>
                <th scope="col">Account</th>
                @if ($showOrganization)<th scope="col" class="hidden lg:table-cell">Organisation</th>@endif
                <th scope="col" class="hidden sm:table-cell">IP address</th>
                <th scope="col" class="hidden xl:table-cell">Client</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($events as $event)
                <tr>
                    <td class="whitespace-nowrap text-xs text-fg-muted"><time datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->timezone($timezone)->format('D j M Y') }}<br>{{ $event->created_at->timezone($timezone)->format('H:i:s') }}</time></td>
                    <td><x-badge :tone="$event->event->tone()">{{ $event->event->label() }}</x-badge>@if ($event->remember) <span class="ml-1 text-[0.65rem] text-fg-subtle" title="Remember me">R</span>@endif</td>
                    <td>
                        @if ($event->user)
                            @if ($userRoute)
                                <a href="{{ route($userRoute, $event->user) }}" class="font-medium text-fg hover:text-primary hover:underline">{{ $event->user->name }}</a>
                            @else
                                <p class="font-medium text-fg">{{ $event->user->name }}</p>
                            @endif
                            <p class="truncate text-xs text-fg-subtle">{{ $event->user->email }}</p>
                        @else
                            <p class="font-medium text-fg">{{ $event->email ?? 'Unknown' }}</p>
                            <p class="text-xs text-fg-subtle">No matching account</p>
                        @endif
                    </td>
                    @if ($showOrganization)
                        <td class="hidden lg:table-cell">{{ $event->organization?->name ?? '— Platform —' }}</td>
                    @endif
                    <td class="hidden font-mono text-xs sm:table-cell">{{ $event->ip_address ?? '—' }}</td>
                    <td class="hidden max-w-xs truncate text-xs text-fg-subtle xl:table-cell" title="{{ $event->user_agent }}">{{ \Illuminate\Support\Str::limit($event->user_agent ?? '—', 60) }}</td>
                </tr>
            @endforeach
        </tbody>
    </x-data-table>
@endif
