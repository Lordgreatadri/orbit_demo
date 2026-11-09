<x-layouts.app title="Notifications">
    <x-page-header title="Notifications" description="Mail and the in-app inbox are active. SMS uses the platform provider when it is enabled. Push is a plug with no provider connected." />

    <x-card title="Your inbox" :padding="false">
        <ul class="divide-y divide-edge">
            @forelse ($inbox as $notification)
                <li class="px-5 py-3 text-sm">
                    @if (filled($notification->data['url'] ?? null))
                        <a href="{{ $notification->data['url'] }}" class="font-medium text-fg hover:text-primary hover:underline">{{ $notification->data['title'] ?? 'Notice' }}</a>
                    @else
                        <p class="font-medium text-fg">{{ $notification->data['title'] ?? 'Notice' }}</p>
                    @endif
                    <p class="text-fg-muted">{{ $notification->data['body'] ?? '' }}</p>
                </li>
            @empty
                <li class="px-5 py-4 text-sm text-fg-muted">No notifications yet.</li>
            @endforelse
        </ul>
    </x-card>

    @if ($canManage)
        <x-card title="Organisation channels">
            <form method="POST" action="{{ route('org.notifications.update') }}" class="space-y-4">
                @csrf
                @method('PUT')
                @foreach ($events as $event => $label)
                    @php $pref = $preferences->get($event); @endphp
                    <fieldset class="rounded-xl border border-edge p-3">
                        <legend class="px-1 text-sm font-medium text-fg">{{ $label }}</legend>
                        <div class="mt-2 flex flex-wrap gap-4 text-sm">
                            <input type="hidden" name="events[{{ $event }}][mail]" value="0">
                            <label class="inline-flex items-center gap-2"><input type="checkbox" name="events[{{ $event }}][mail]" value="1" @checked(old('events.'.$event.'.mail', $pref->mail ?? true))> Email</label>
                            <input type="hidden" name="events[{{ $event }}][database]" value="0">
                            <label class="inline-flex items-center gap-2"><input type="checkbox" name="events[{{ $event }}][database]" value="1" @checked(old('events.'.$event.'.database', $pref->database ?? true))> In-app</label>
                            <input type="hidden" name="events[{{ $event }}][sms]" value="0">
                            <label class="inline-flex items-center gap-2"><input type="checkbox" name="events[{{ $event }}][sms]" value="1" @checked(old('events.'.$event.'.sms', $pref->sms ?? false))> SMS</label>
                            <input type="hidden" name="events[{{ $event }}][push]" value="0">
                            <label class="inline-flex items-center gap-2"><input type="checkbox" name="events[{{ $event }}][push]" value="1" @checked(old('events.'.$event.'.push', $pref->push ?? false))> Push plug</label>
                        </div>
                    </fieldset>
                @endforeach
                <x-button type="submit" size="sm">Save preferences</x-button>
            </form>
        </x-card>
    @endif
</x-layouts.app>
