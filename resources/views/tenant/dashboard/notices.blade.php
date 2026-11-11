<h2 id="notices-heading" class="text-base font-semibold text-fg">Notifications</h2>
<p class="mt-1 text-sm text-fg-muted">{{ $unreadNotifications === 1 ? '1 unread' : $unreadNotifications.' unread' }} · recent notices. The inbox holds the full list.</p>
@if ($recentNotifications->isEmpty())
    <p class="mt-3 text-sm text-fg-muted">No notifications yet.</p>
@else
    <ul class="mt-3 divide-y divide-edge">
        @foreach ($recentNotifications->take(3) as $notification)
            <li class="flex min-h-11 flex-wrap items-center justify-between gap-2 py-3 text-sm">
                <p class="min-w-0 font-medium text-fg">{{ $notification->data['title'] ?? 'Notice' }}</p>
                @if ($notification->read_at === null)
                    <x-badge tone="warning">Unread</x-badge>
                @else
                    <x-badge>Read</x-badge>
                @endif
            </li>
        @endforeach
    </ul>
@endif
<a href="{{ route('org.notifications.index') }}" class="mt-1 inline-flex min-h-11 items-center text-sm font-medium text-primary hover:underline">Open notifications</a>
