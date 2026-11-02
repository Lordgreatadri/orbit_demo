<x-layouts.app title="SMS message">
    @php
        $steps = array_filter([
            ['Queued', $message->queued_at ?? $message->created_at, 'info'],
            ['Submitted', $message->submitted_at, 'info'],
            ['Accepted', $message->accepted_at, 'warning'],
            ['Delivered', $message->delivered_at, 'success'],
            ['Failed', $message->failed_at, 'danger'],
        ], fn (array $step) => $step[1] !== null);
        if ($message->status === \App\Enums\SmsMessageStatus::Logged && $steps === []) {
            $steps = [['Logged', $message->created_at, 'neutral']];
        }
    @endphp

    <x-page-header title="SMS message" description="The text that was stored, and each status this message has reached. Accepted means the provider took it. Delivered waits for their callback." eyebrow="Platform">
        <x-button :href="route('admin.sms.overview')" variant="ghost" size="sm"><x-icon name="chevron-left" class="size-4" /> SMS overview</x-button>
        @if ($message->provider)
            <x-button :href="route('admin.sms-providers.show', $message->provider)" variant="secondary" size="sm">{{ $message->provider->name }}</x-button>
        @endif
    </x-page-header>

    <section class="grid gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(18rem,0.8fr)]">
        <div class="space-y-6">
            <x-card title="Message">
                <div class="mb-4 flex flex-wrap items-center gap-2">
                    <x-badge :tone="$message->status->tone()">{{ $message->status->label() }}</x-badge>
                    @if ($message->provider_status)
                        <x-badge tone="neutral">{{ $message->provider_status }}</x-badge>
                    @endif
                    <span class="text-sm text-fg-muted">{{ str_replace('_', ' ', $message->purpose) }}</span>
                </div>
                <p class="whitespace-pre-wrap rounded-xl border border-edge bg-surface-muted px-4 py-3 text-sm leading-relaxed text-fg">{{ $message->body }}</p>
                @if ($message->last_error)
                    <p class="mt-4 text-sm text-danger">{{ $message->last_error }}</p>
                @endif
            </x-card>

            <x-card title="Status history" description="Times are recorded when the message reaches that state. A later report does not walk the status backwards.">
                @if ($steps === [])
                    <p class="text-sm text-fg-muted">No status time has been recorded yet.</p>
                @else
                    <ol class="space-y-0">
                        @foreach ($steps as [$label, $at, $tone])
                            <li class="flex gap-3">
                                <div class="flex flex-col items-center">
                                    <span @class([
                                        'mt-1 size-2.5 rounded-full',
                                        'bg-info' => $tone === 'info',
                                        'bg-warning' => $tone === 'warning',
                                        'bg-success' => $tone === 'success',
                                        'bg-danger' => $tone === 'danger',
                                        'bg-fg-subtle' => $tone === 'neutral',
                                    ])></span>
                                    @if (! $loop->last)
                                        <span class="w-px flex-1 bg-edge"></span>
                                    @endif
                                </div>
                                <div class="pb-4">
                                    <p class="text-sm font-medium text-fg">{{ $label }}</p>
                                    <p class="text-xs text-fg-muted">{{ $at->timezone(config('app.timezone'))->format('d M Y H:i:s') }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-card>

            <section class="space-y-3" aria-labelledby="sms-events">
                <h2 id="sms-events" class="text-base font-semibold text-fg">Provider reports</h2>
                @if ($events->total() === 0)
                    <x-empty-state title="No provider report yet" description="A send result or a delivery callback will appear here." />
                @else
                    <x-data-table :paginator="$events" caption="Provider reports">
                        <thead>
                            <tr>
                                <th scope="col">When</th>
                                <th scope="col">Report</th>
                                <th scope="col">Status</th>
                                <th scope="col">Provider status</th>
                                <th scope="col">Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($events as $event)
                                <tr>
                                    <td class="text-fg-muted">{{ $event->created_at->timezone(config('app.timezone'))->format('d M H:i:s') }}</td>
                                    <td>{{ $event->type === 'callback' ? 'Delivery callback' : 'Send result' }}</td>
                                    <td>{{ $event->status ? str_replace('_', ' ', $event->status) : '—' }}</td>
                                    <td class="text-fg-muted">{{ $event->provider_status ?: '—' }}</td>
                                    <td class="max-w-xs text-fg-muted">{{ data_get($event->payload, 'error') ?? data_get($event->payload, 'reason') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-data-table>
                @endif
            </section>
        </div>

        <x-card title="Delivery">
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-fg-muted">To</dt><dd class="font-medium text-fg">{{ $message->to }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-fg-muted">Sender</dt><dd class="font-medium text-fg">{{ $message->sender_id ?: '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-fg-muted">Provider</dt><dd class="font-medium text-fg">{{ $message->provider?->name ?? 'Application log' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-fg-muted">Provider reference</dt><dd class="max-w-[12rem] truncate font-mono text-xs text-fg">{{ $message->provider_message_id ?: '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-fg-muted">Attempts</dt><dd class="font-medium text-fg tabular-nums">{{ $message->attempts }} of {{ $message->max_attempts }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-fg-muted">Fallback used</dt><dd class="font-medium text-fg">{{ $message->used_fallback ? 'Yes' : 'No' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-fg-muted">Organisation</dt><dd class="text-right font-medium text-fg">{{ $message->organization?->name ?? 'Platform' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-fg-muted">Billing</dt><dd class="text-right font-medium text-fg">{{ $message->counts_toward_subscription ? 'Subscription' : 'Paid by ORBIT' }}</dd></div>
                <div><dt class="text-fg-muted">Reference</dt><dd class="mt-1 break-all font-mono text-xs text-fg-subtle">{{ $message->uuid }}</dd></div>
            </dl>
        </x-card>
    </section>
</x-layouts.app>
