{{-- Effective permissions, recent sign-ins and recent audit entries for one account. --}}
@props(['user', 'effectivePermissions', 'recentSignIns', 'recentActivity', 'timezone'])

<x-card title="Effective permissions" :description="$effectivePermissions->count().' in total, through roles and direct grants'">
    @if ($effectivePermissions->isEmpty())
        <p class="text-sm text-fg-muted">None. The account can only use self-service features.</p>
    @else
        <ul class="flex flex-wrap gap-1.5">
            @foreach ($effectivePermissions as $permission)
                <li><x-badge tone="neutral" :title="$permission->description">{{ $permission->name }}</x-badge></li>
            @endforeach
        </ul>
    @endif
</x-card>

@if ($recentSignIns instanceof \Illuminate\Support\Collection && ($recentSignIns->isNotEmpty() || $user->last_login_at))
    <x-card title="Recent sign-ins" :padding="false">
        @if ($recentSignIns->isEmpty())
            <p class="p-5 text-sm text-fg-muted">No events recorded yet.</p>
        @else
            <ul class="divide-y divide-edge">
                @foreach ($recentSignIns as $event)
                    <li class="flex items-center justify-between gap-2 px-5 py-2.5 text-sm">
                        <div class="flex min-w-0 items-center gap-2">
                            <x-badge :tone="$event->event->tone()">{{ $event->event->label() }}</x-badge>
                            <span class="truncate text-xs text-fg-muted">{{ $event->created_at->timezone($timezone)->format('D j M Y, H:i') }}</span>
                        </div>
                        <span class="font-mono text-xs text-fg-subtle">{{ $event->ip_address ?? '—' }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
@endif

@if ($recentActivity->isNotEmpty())
    <x-card title="Recent audit entries" description="Where this account was the actor or the subject." :padding="false">
        <ul class="divide-y divide-edge">
            @foreach ($recentActivity as $activity)
                <li class="px-5 py-2.5 text-sm">
                    <p class="font-medium text-fg">{{ $activity->eventLabel() }}</p>
                    <p class="text-xs text-fg-subtle">{{ $activity->causer?->name ?? 'System' }} · {{ $activity->created_at->timezone($timezone)->format('D j M Y, H:i') }}</p>
                </li>
            @endforeach
        </ul>
    </x-card>
@endif
