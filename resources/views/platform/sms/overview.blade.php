<x-layouts.app title="SMS overview" wide>
    @php
        $peak = max(1, ...array_column($days, 'total'));
        $window = $totals['delivered'] + $totals['accepted'] + $totals['failed'] + $totals['logged'] + $totals['inflight'];
    @endphp

    <x-page-header title="SMS overview" description="Platform messages from the last 7 days. Accepted means the provider took the message. Delivered waits for their callback. These totals are paid by ORBIT and are not organisation subscription usage." eyebrow="Platform">
        <x-button :href="route('admin.sms-providers.index')" size="sm"><x-icon name="settings" class="size-4" /> Configure providers</x-button>
    </x-page-header>

    <section class="relative overflow-hidden rounded-2xl border border-edge bg-surface shadow-float" aria-labelledby="sms-hero-title">
        <div class="bg-mesh absolute inset-0" aria-hidden="true"></div>
        <div class="bg-grid absolute inset-0 opacity-70" aria-hidden="true"></div>
        <div class="relative grid gap-8 p-6 sm:p-8 lg:grid-cols-[minmax(0,1.4fr)_minmax(16rem,0.8fr)]">
            <div>
                <p class="eyebrow">Transmission</p>
                <h2 id="sms-hero-title" class="mt-3 text-3xl font-semibold tracking-tight text-fg sm:text-4xl">
                    @if ($rate === null)
                        No answered messages yet
                    @else
                        {{ $rate }}% confirmed delivered
                    @endif
                </h2>
                <p class="mt-3 max-w-xl text-sm leading-relaxed text-fg-muted">
                    @if ($rate === null)
                        A delivery rate appears after a provider accepts, fails, or confirms a message. Logged messages had no active provider.
                    @else
                        Of the messages a provider has answered, {{ number_format($totals['delivered']) }} {{ $totals['delivered'] === 1 ? 'is' : 'are' }} confirmed on the handset. {{ number_format($totals['accepted']) }} {{ $totals['accepted'] === 1 ? 'is' : 'are' }} accepted and still waiting.
                    @endif
                </p>
                <dl class="mt-6 grid grid-cols-3 gap-3">
                    <div class="rounded-xl border border-edge bg-surface/80 p-3 shadow-float">
                        <dt class="text-xs text-fg-muted">Today sent</dt>
                        <dd class="mt-1 text-2xl font-semibold text-fg tabular-nums">{{ number_format($today['sent']) }}</dd>
                    </div>
                    <div class="rounded-xl border border-edge bg-success-soft/70 p-3 shadow-float">
                        <dt class="text-xs text-fg-muted">Today delivered</dt>
                        <dd class="mt-1 text-2xl font-semibold text-fg tabular-nums">{{ number_format($today['delivered']) }}</dd>
                    </div>
                    <div class="rounded-xl border border-edge bg-danger-soft/70 p-3 shadow-float">
                        <dt class="text-xs text-fg-muted">Today failed</dt>
                        <dd class="mt-1 text-2xl font-semibold text-fg tabular-nums">{{ number_format($today['failed']) }}</dd>
                    </div>
                </dl>
            </div>
            <div class="rounded-xl border border-edge bg-surface/90 p-5 shadow-float">
                <p class="text-xs font-semibold tracking-wide text-fg-subtle uppercase">Active sender</p>
                @if ($active)
                    <p class="mt-2 text-xl font-semibold text-fg">{{ $active->name }}</p>
                    <p class="mt-1"><x-badge :tone="$active->health_status->tone()">{{ $active->health_status->label() }}</x-badge></p>
                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-fg-muted">SMS bundle</dt><dd class="font-medium text-fg tabular-nums">{{ $active->smsBundleReading() !== null ? $active->smsBundleReading().' SMS' : 'Not checked' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-fg-muted">Cash</dt><dd class="font-medium text-fg tabular-nums">{{ $active->cash_balance !== null ? $active->cash_balance.' '.$active->cash_balance_unit : 'Not checked' }}</dd></div>
                    </dl>
                    <a href="{{ route('admin.sms-providers.show', $active) }}" class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline">Open {{ $active->name }} <x-icon name="arrow-right" class="size-4" /></a>
                @else
                    <p class="mt-2 text-lg font-semibold text-fg">No provider is sending</p>
                    <p class="mt-2 text-sm text-fg-muted">New platform messages are stored and written to the log until one provider is turned on.</p>
                    <a href="{{ route('admin.sms-providers.index') }}" class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline">Choose a provider <x-icon name="arrow-right" class="size-4" /></a>
                @endif
            </div>
        </div>
    </section>

    <section aria-labelledby="sms-kpis">
        <h2 id="sms-kpis" class="sr-only">Seven day figures</h2>
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
            <x-stat label="Sent" :value="number_format($totals['sent'])" hint="Last 7 days, excluding the log" icon="chat" />
            <x-stat label="Delivered" :value="number_format($totals['delivered'])" hint="Callback confirmed" icon="check-circle" tone="success" />
            <x-stat label="Accepted" :value="number_format($totals['accepted'])" hint="Waiting for delivery" icon="clock" tone="warning" />
            <x-stat label="Failed" :value="number_format($totals['failed'])" hint="Rejected or undelivered" icon="bell" tone="danger" />
            <x-stat label="Logged" :value="number_format($totals['logged'])" hint="No active provider" icon="document-check" tone="neutral" />
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.3fr)_minmax(18rem,0.7fr)]">
        <x-card title="Each day" description="Stack shows delivered, accepted, failed, and everything else. The tallest day is the busiest day in this window.">
            @if ($window === 0)
                <x-empty-state title="No messages in the last 7 days" description="A test or a platform notification will draw the first column." />
            @else
                <div class="flex items-end gap-2 sm:gap-3" role="img" aria-label="Messages per day for the last 7 days">
                    @foreach ($days as $day)
                        @php $height = (int) round($day['total'] / $peak * 100); @endphp
                        <div class="flex min-w-0 flex-1 flex-col items-center gap-2">
                            <span class="text-xs font-medium text-fg tabular-nums">{{ $day['total'] }}</span>
                            <div class="flex h-32 w-full flex-col justify-end overflow-hidden rounded-lg bg-surface-muted">
                                @if ($day['total'] > 0)
                                    <div class="bg-surface-muted" style="height: {{ 100 - $height }}%"></div>
                                    <div class="bg-fg-subtle/40" style="height: {{ $height * ($day['other'] / $day['total']) }}%"></div>
                                    <div class="bg-danger" style="height: {{ $height * ($day['failed'] / $day['total']) }}%"></div>
                                    <div class="bg-warning" style="height: {{ $height * ($day['accepted'] / $day['total']) }}%"></div>
                                    <div class="bg-success" style="height: {{ $height * ($day['delivered'] / $day['total']) }}%"></div>
                                @endif
                            </div>
                            <span class="text-xs text-fg-muted">{{ $day['label'] }}</span>
                        </div>
                    @endforeach
                </div>
                <ul class="mt-4 flex flex-wrap gap-4 text-xs text-fg-muted">
                    <li class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-success"></span> Delivered</li>
                    <li class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-warning"></span> Accepted</li>
                    <li class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-danger"></span> Failed</li>
                    <li class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-fg-subtle/40"></span> Queued, submitted, or logged</li>
                </ul>
            @endif
        </x-card>

        <x-card title="In flight" description="{{ number_format($totals['inflight']) }} queued or submitted, not yet accepted.">
            <dl class="space-y-3 text-sm">
                @foreach (['delivered' => ['Delivered', 'success'], 'accepted' => ['Accepted', 'warning'], 'failed' => ['Failed', 'danger'], 'logged' => ['Logged', 'neutral'], 'inflight' => ['Still sending', 'info']] as $key => [$label, $tone])
                    <div>
                        <div class="mb-1 flex items-center justify-between gap-3">
                            <dt class="text-fg-muted">{{ $label }}</dt>
                            <dd class="font-semibold text-fg tabular-nums">{{ number_format($totals[$key]) }}</dd>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-surface-muted" aria-hidden="true">
                            <div @class([
                                'h-full rounded-full',
                                'bg-success' => $tone === 'success',
                                'bg-warning' => $tone === 'warning',
                                'bg-danger' => $tone === 'danger',
                                'bg-info' => $tone === 'info',
                                'bg-fg-subtle' => $tone === 'neutral',
                            ]) style="width: {{ $window === 0 ? 0 : (int) round($totals[$key] / $window * 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </dl>
        </x-card>
    </div>

    <section aria-labelledby="sms-providers-heading">
        <div class="mb-3 flex items-end justify-between gap-3">
            <h2 id="sms-providers-heading" class="text-base font-semibold text-fg">Providers</h2>
            <p class="text-xs text-fg-subtle">Counts are the last 7 days.</p>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($providers as $row)
                @php
                    $provider = $row['provider'];
                    $parts = max(1, $row['sent']);
                @endphp
                <article class="card flex flex-col p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-fg">
                                <a href="{{ route('admin.sms-providers.show', $provider) }}" class="hover:text-primary hover:underline">{{ $provider->name }}</a>
                            </h3>
                            <p class="mt-1 flex flex-wrap items-center gap-2">
                                @if ($provider->is_active && $provider->enabled)
                                    <x-badge tone="success">Sending</x-badge>
                                @elseif ($provider->enabled)
                                    <x-badge tone="info">On</x-badge>
                                @else
                                    <x-badge>Off</x-badge>
                                @endif
                                <x-badge :tone="$provider->health_status->tone()">{{ $provider->health_status->label() }}</x-badge>
                                @if ($row['bundle_low'])
                                    <x-badge tone="warning">Low SMS bundle</x-badge>
                                @endif
                                @if ($row['cash_low'])
                                    <x-badge tone="warning">Low cash</x-badge>
                                @endif
                            </p>
                        </div>
                        <p class="text-right">
                            <span class="block text-2xl font-semibold text-fg tabular-nums">{{ number_format($row['sent']) }}</span>
                            <span class="text-xs text-fg-subtle">sent</span>
                        </p>
                    </div>
                    <div class="mt-4 h-2 overflow-hidden rounded-full bg-surface-muted" aria-hidden="true">
                        <div class="flex h-full">
                            <div class="bg-success" style="width: {{ (int) round($row['delivered'] / $parts * 100) }}%"></div>
                            <div class="bg-warning" style="width: {{ (int) round($row['accepted'] / $parts * 100) }}%"></div>
                            <div class="bg-danger" style="width: {{ (int) round($row['failed'] / $parts * 100) }}%"></div>
                        </div>
                    </div>
                    <dl class="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
                        <div><dt class="text-fg-muted">Delivered</dt><dd class="mt-0.5 font-semibold text-fg tabular-nums">{{ number_format($row['delivered']) }}</dd></div>
                        <div><dt class="text-fg-muted">Accepted</dt><dd class="mt-0.5 font-semibold text-fg tabular-nums">{{ number_format($row['accepted']) }}</dd></div>
                        <div><dt class="text-fg-muted">Failed</dt><dd class="mt-0.5 font-semibold text-fg tabular-nums">{{ number_format($row['failed']) }}</dd></div>
                    </dl>
                    <dl class="mt-4 space-y-1 border-t border-edge pt-3 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-fg-muted">SMS bundle</dt><dd class="font-medium text-fg tabular-nums">{{ $provider->smsBundleReading() !== null ? $provider->smsBundleReading().' SMS' : '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-fg-muted">Cash</dt><dd class="font-medium text-fg tabular-nums">{{ $provider->cash_balance !== null ? $provider->cash_balance.' '.$provider->cash_balance_unit : '—' }}</dd></div>
                    </dl>
                </article>
            @endforeach
        </div>
    </section>

    <section class="space-y-3" aria-labelledby="sms-latest">
        <div>
            <h2 id="sms-latest" class="text-base font-semibold text-fg">Latest messages</h2>
            <p class="text-sm text-fg-muted">Open a row to read the message and follow its status. This list keeps the number masked.</p>
        </div>
        @if ($messages->total() === 0)
            <x-empty-state title="No messages yet" description="A test from a provider page will show up here." />
        @else
            <x-data-table :paginator="$messages" caption="Latest messages">
                <thead>
                    <tr>
                        <th scope="col">When</th>
                        <th scope="col">To</th>
                        <th scope="col">Provider</th>
                        <th scope="col">Purpose</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="sr-only">Open</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($messages as $message)
                        <tr>
                            <td class="text-fg-muted"><a href="{{ route('admin.sms.messages.show', $message) }}" class="hover:text-primary hover:underline">{{ $message->created_at->timezone(config('app.timezone'))->format('d M H:i') }}</a></td>
                            <td>{{ $message->maskedTo() }}</td>
                            <td>{{ $message->provider?->name ?? 'Log' }}</td>
                            <td>{{ str_replace('_', ' ', $message->purpose) }}</td>
                            <td><x-badge :tone="$message->status->tone()">{{ $message->status->label() }}</x-badge></td>
                            <td class="text-right"><x-button :href="route('admin.sms.messages.show', $message)" variant="ghost" size="sm" aria-label="Open message to {{ $message->maskedTo() }}"><x-icon name="chevron-right" class="size-4" /></x-button></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-data-table>
        @endif
    </section>
</x-layouts.app>
