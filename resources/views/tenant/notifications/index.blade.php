<x-layouts.app title="Notifications">
    <x-page-header title="Notifications" :description="($unread === 1 ? '1 unread notice' : $unread.' unread notices').' · read state follows when a notice was opened'" eyebrow="Inbox">
        @if ($unread > 0)
            <form method="POST" action="{{ route('org.notifications.read-all') }}">
                @csrf
                <x-button type="submit" variant="secondary" size="sm">Mark all as read</x-button>
            </form>
        @endif
    </x-page-header>

    <nav aria-label="Notification categories" class="flex flex-wrap gap-2">
        <a href="{{ route('org.notifications.index') }}" @if ($category === null) aria-current="page" @endif class="inline-flex min-h-11 items-center rounded-full border px-3 text-sm focus-visible:outline-2 {{ $category === null ? 'border-primary bg-primary-soft font-medium text-fg' : 'border-edge text-fg-muted hover:bg-surface-muted' }}">All</a>
        @foreach ($categories as $key => $label)
            <a href="{{ route('org.notifications.index', ['category' => $key]) }}" @if ($category === $key) aria-current="page" @endif class="inline-flex min-h-11 items-center rounded-full border px-3 text-sm focus-visible:outline-2 {{ $category === $key ? 'border-primary bg-primary-soft font-medium text-fg' : 'border-edge text-fg-muted hover:bg-surface-muted' }}">{{ $label }}</a>
        @endforeach
    </nav>

    <section aria-labelledby="inbox-heading" class="card overflow-hidden">
        <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-edge px-5 py-4">
            <h2 id="inbox-heading" class="flex items-center gap-2 text-base font-semibold text-fg"><x-icon name="bell" class="size-4 text-fg-subtle" /> Inbox</h2>
            <p class="text-sm text-fg-muted">Newest first. Fifteen notices on each page.</p>
        </div>
        @if ($inbox->isEmpty())
            <p class="px-5 py-6 text-sm text-fg-muted">No notifications. Leave, schedule, and attendance notices for you will appear here.</p>
        @else
            <ul class="divide-y divide-edge">
                @foreach ($inbox as $notification)
                    @php
                        $target = \App\Workforce\Communication\Inbox::safeUrl($notification->data['url'] ?? null, auth()->user());
                        $unreadRow = $notification->read_at === null;
                    @endphp
                    <li class="relative px-5 py-4 {{ $unreadRow ? 'bg-primary-soft/40' : '' }}">
                        @if ($unreadRow)
                            <span class="absolute top-4 bottom-4 left-0 w-[3px] rounded-r-full bg-primary" aria-hidden="true"></span>
                        @endif
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                @if ($target)
                                    <form method="POST" action="{{ route('org.notifications.open', $notification->uuid) }}">
                                        @csrf
                                        <button type="submit" class="text-left font-medium text-fg hover:text-primary hover:underline focus-visible:outline-2">{{ $notification->data['title'] ?? 'Notice' }}</button>
                                    </form>
                                @else
                                    <p class="font-medium text-fg">{{ $notification->data['title'] ?? 'Notice' }}</p>
                                @endif
                                <p class="mt-1 text-sm text-fg-muted">{{ $notification->data['body'] ?? '' }}</p>
                                <p class="mt-2 flex flex-wrap items-center gap-2 text-xs text-fg-subtle">
                                    @if ($unreadRow)
                                        <x-badge tone="warning">Unread</x-badge>
                                    @else
                                        <x-badge>Read</x-badge>
                                    @endif
                                    <span>{{ \App\Workforce\WorkforceEvents::categories()[$notification->data['category'] ?? ''] ?? 'Notice' }}</span>
                                    <time datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->timezone(auth()->user()->organization->timezone)->format('D j M Y, H:i') }}</time>
                                </p>
                            </div>
                            @if ($unreadRow)
                                <form method="POST" action="{{ route('org.notifications.read', $notification->uuid) }}">
                                    @csrf
                                    <x-button type="submit" variant="ghost" size="sm">Mark as read</x-button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            @if ($inbox->hasPages())
                <div class="border-t border-edge px-5 py-3">{{ $inbox->links() }}</div>
            @endif
        @endif
    </section>

    <x-card title="Your choices" description="These apply to optional notices. Approval results, schedule assignments, and attendance problems stay on while the organisation allows that channel.">
        <form method="POST" action="{{ route('org.notifications.preferences') }}" class="space-y-4">
            @csrf
            @method('PUT')
            @foreach ($categories as $key => $label)
                @php $choice = $own->get($key); @endphp
                <fieldset class="rounded-xl border border-edge p-3">
                    <legend class="px-1 text-sm font-medium text-fg">{{ $label }}</legend>
                    <p class="mt-1 text-xs text-fg-muted">Optional {{ strtolower($label) }} notices.</p>
                    <div class="mt-2 flex flex-wrap gap-4 text-sm">
                        <input type="hidden" name="categories[{{ $key }}][mail]" value="0">
                        <label class="inline-flex items-center gap-2"><input type="checkbox" name="categories[{{ $key }}][mail]" value="1" class="focus-visible:outline-2" @checked(old('categories.'.$key.'.mail', $choice?->mail ?? true))> Email</label>
                        <input type="hidden" name="categories[{{ $key }}][database]" value="0">
                        <label class="inline-flex items-center gap-2"><input type="checkbox" name="categories[{{ $key }}][database]" value="1" class="focus-visible:outline-2" @checked(old('categories.'.$key.'.database', $choice?->database ?? true))> In-app</label>
                        <input type="hidden" name="categories[{{ $key }}][sms]" value="0">
                        <label class="inline-flex items-center gap-2"><input type="checkbox" name="categories[{{ $key }}][sms]" value="1" class="focus-visible:outline-2" @checked(old('categories.'.$key.'.sms', $choice?->sms ?? false))> SMS</label>
                    </div>
                </fieldset>
            @endforeach
            <x-button type="submit" size="sm">Save my choices</x-button>
        </form>
    </x-card>

    @if ($canManage)
        <x-card title="Organisation policy" description="Turns a whole channel or category off for everyone. People cannot switch required notices back on after you turn the category off.">
            <form method="POST" action="{{ route('org.notifications.policy') }}" class="space-y-3">
                @csrf
                @method('PUT')
                @foreach ([
                    'email_enabled' => 'Email',
                    'in_app_enabled' => 'In-app inbox',
                    'sms_enabled' => 'SMS',
                    'leave_notifications_enabled' => 'Leave notices',
                    'schedule_notifications_enabled' => 'Schedule notices',
                    'attendance_reminders_enabled' => 'Shift and missed-attendance reminders',
                    'announcements_enabled' => 'Announcements',
                ] as $flag => $label)
                    <input type="hidden" name="{{ $flag }}" value="0">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="{{ $flag }}" value="1" class="focus-visible:outline-2" @checked(old($flag, $policy->{$flag}))>
                        {{ $label }}
                    </label>
                @endforeach
                <x-button type="submit" size="sm">Save policy</x-button>
            </form>
        </x-card>

        <x-card title="Organisation channels" description="Required notices can still be limited to a channel here. SMS needs a platform provider. Push has no provider yet.">
            <form method="POST" action="{{ route('org.notifications.update') }}" class="space-y-4">
                @csrf
                @method('PUT')
                @foreach ($events as $event => $label)
                    @php
                        $pref = $preferences->get($event);
                        $required = $definitions[$event]['mandatory'] ?? false;
                    @endphp
                    <fieldset class="rounded-xl border border-edge p-3">
                        <legend class="px-1 text-sm font-medium text-fg">
                            {{ $label }}
                            @if ($required)
                                <span class="ml-1 text-xs font-normal text-fg-muted">Required</span>
                            @endif
                        </legend>
                        <div class="mt-2 flex flex-wrap gap-4 text-sm">
                            <input type="hidden" name="events[{{ $event }}][mail]" value="0">
                            <label class="inline-flex items-center gap-2"><input type="checkbox" name="events[{{ $event }}][mail]" value="1" class="focus-visible:outline-2" @checked(old('events.'.$event.'.mail', $pref?->mail ?? true))> Email</label>
                            <input type="hidden" name="events[{{ $event }}][database]" value="0">
                            <label class="inline-flex items-center gap-2"><input type="checkbox" name="events[{{ $event }}][database]" value="1" class="focus-visible:outline-2" @checked(old('events.'.$event.'.database', $pref?->database ?? true))> In-app</label>
                            <input type="hidden" name="events[{{ $event }}][sms]" value="0">
                            <label class="inline-flex items-center gap-2"><input type="checkbox" name="events[{{ $event }}][sms]" value="1" class="focus-visible:outline-2" @checked(old('events.'.$event.'.sms', $pref?->sms ?? false))> SMS</label>
                            <input type="hidden" name="events[{{ $event }}][push]" value="0">
                            <label class="inline-flex items-center gap-2"><input type="checkbox" name="events[{{ $event }}][push]" value="1" class="focus-visible:outline-2" @checked(old('events.'.$event.'.push', $pref?->push ?? false))> Push plug</label>
                        </div>
                    </fieldset>
                @endforeach
                <x-button type="submit" size="sm">Save channels</x-button>
            </form>
        </x-card>

        <x-card title="Wording" description="Optional. Leave both fields empty to use the standard wording. Placeholders such as person_name, organization_name, leave_type, start_date, end_date, schedule_date, shift_name, and action_url are written inside double braces and filled by the server. Anything else is removed.">
            <form method="POST" action="{{ route('org.notifications.template') }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label for="template-event" class="text-sm font-medium text-fg">Notice</label>
                    <select id="template-event" name="event" class="mt-1 w-full rounded-lg border border-edge bg-surface px-3 py-2 text-sm focus-visible:outline-2">
                        @foreach ($events as $event => $label)
                            <option value="{{ $event }}" @selected(old('event', array_key_first($events)) === $event)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <x-form.input name="subject" label="Title" maxlength="140" :value="old('subject')" />
                <x-form.textarea name="body" label="Message" rows="4" maxlength="1000" :value="old('body')" />
                <x-button type="submit" size="sm">Save wording</x-button>
            </form>
        </x-card>
    @endif
</x-layouts.app>
