{{-- Recent notices for the signed-in person. Level 4: quiet, routes to the inbox. --}}
<x-card icon="bell" title="Notifications" :description="($unreadNotifications === 1 ? '1 unread' : $unreadNotifications.' unread').' · the inbox holds the full list.'">
    <x-slot:actions><a href="{{ route('org.notifications.index') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-primary hover:underline">Open notifications</a></x-slot:actions>
    @if ($recentNotifications->isEmpty())
        <p class="text-sm text-fg-muted">No notifications yet.</p>
    @else
        <ul class="-my-2 divide-y divide-edge">
            @foreach ($recentNotifications->take(3) as $notification)
                @php $unread = $notification->read_at === null; @endphp
                <li class="flex items-start gap-3 py-2.5 text-sm">
                    <span @class(['mt-1.5 size-2 shrink-0 rounded-full', 'bg-primary' => $unread, 'bg-edge-strong' => ! $unread]) aria-hidden="true"></span>
                    <div class="min-w-0 flex-1">
                        <p @class(['truncate', 'font-semibold text-fg' => $unread, 'font-medium text-fg-muted' => ! $unread])>{{ $notification->data['title'] ?? 'Notice' }}</p>
                        <p class="mt-0.5 text-xs text-fg-subtle"><time datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->diffForHumans() }}</time> · {{ $unread ? 'Unread' : 'Read' }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
