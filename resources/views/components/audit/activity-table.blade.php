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
                <th scope="col" class="w-px"><span class="sr-only">Details</span></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($activities as $activity)
                @php
                    $props = collect($activity->properties ?? [])->except(['ip', 'user_agent', 'token', 'password']);
                    $fields = $props->map(function ($value, $key) {
                        if ($value === null) {
                            $text = '—';
                        } elseif (is_bool($value)) {
                            $text = $value ? 'true' : 'false';
                        } elseif (is_scalar($value)) {
                            $text = (string) $value;
                        } else {
                            $text = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
                        }

                        return ['key' => (string) $key, 'value' => $text];
                    })->values()->all();
                    $payload = [
                        'when' => $activity->created_at->timezone($timezone)->format('D j M Y, H:i:s'),
                        'event' => $activity->eventLabel(),
                        'key' => (string) $activity->event,
                        'actor' => $activity->causer?->name ?? 'System',
                        'email' => $activity->causer?->email,
                        'organization' => $showOrganization ? ($activity->organization?->name ?? 'Platform') : null,
                        'fields' => $fields,
                    ];
                @endphp
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
                    <td class="whitespace-nowrap text-right">
                        <span class="group relative inline-flex">
                            <button
                                type="button"
                                class="inline-flex size-9 items-center justify-center rounded-lg text-fg-muted hover:bg-surface-muted hover:text-primary focus-visible:outline-2"
                                aria-label="View details"
                                @click="$dispatch('open-audit-detail', @js($payload))"
                            >
                                <x-icon name="eye" class="size-4" />
                            </button>
                            <span aria-hidden="true" class="pointer-events-none absolute top-1/2 right-full z-20 mr-1.5 -translate-y-1/2 rounded-md bg-fg px-2 py-1 text-xs font-medium whitespace-nowrap text-surface opacity-0 shadow-lift transition-opacity group-hover:opacity-100 group-focus-within:opacity-100">View details</span>
                        </span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </x-data-table>

    <div
        x-data="auditDetail"
        x-on:open-audit-detail.window="open($event.detail)"
        x-show="visible"
        x-cloak
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-4 backdrop-blur-[2px] sm:items-center"
        role="dialog"
        aria-modal="true"
        aria-labelledby="audit-detail-title"
        @keydown.escape.window="close()"
        @click.self="close()"
    >
        <div class="card flex max-h-[min(40rem,calc(100svh-2rem))] w-full max-w-lg flex-col overflow-hidden">
            <header class="flex items-start justify-between gap-3 border-b border-edge px-5 py-4">
                <div class="min-w-0">
                    <h2 id="audit-detail-title" class="text-lg font-semibold text-fg" x-text="entry?.event ?? 'Audit details'"></h2>
                    <p class="mt-1 text-sm text-fg-muted" x-text="summary()"></p>
                    <p class="mt-1 font-mono text-[0.7rem] text-fg-subtle" x-text="entry?.key ?? ''"></p>
                </div>
                <button type="button" class="rounded-lg p-1.5 text-fg-muted hover:bg-surface-muted hover:text-fg" @click="close()" aria-label="Close">
                    <x-icon name="x" class="size-5" />
                </button>
            </header>
            <div class="overflow-y-auto px-5 py-4">
                <template x-if="entry && entry.fields.length === 0">
                    <p class="text-sm text-fg-muted">No additional fields were recorded for this event.</p>
                </template>
                <dl x-show="entry && entry.fields.length > 0">
                    <template x-for="field in entry?.fields ?? []" :key="field.key">
                        <div class="grid gap-1 border-b border-edge py-3 last:border-b-0 sm:grid-cols-[9rem_1fr] sm:gap-3">
                            <dt class="text-xs font-medium text-fg-muted" x-text="field.key"></dt>
                            <dd class="min-w-0 font-mono text-xs break-all whitespace-pre-wrap text-fg" x-text="field.value"></dd>
                        </div>
                    </template>
                </dl>
            </div>
        </div>
    </div>

    @once
        @push('scripts')
            <script>
                document.addEventListener('alpine:init', () => {
                    Alpine.data('auditDetail', () => ({
                        visible: false,
                        entry: null,
                        open(detail) {
                            this.entry = detail;
                            this.visible = true;
                            this.$nextTick(() => this.$root.querySelector('[aria-label="Close"]')?.focus());
                        },
                        close() {
                            this.visible = false;
                        },
                        summary() {
                            if (! this.entry) {
                                return '';
                            }
                            const parts = [this.entry.when, this.entry.actor];
                            if (this.entry.email) {
                                parts.push(this.entry.email);
                            }
                            if (this.entry.organization) {
                                parts.push(this.entry.organization);
                            }

                            return parts.filter(Boolean).join(' · ');
                        },
                    }));
                });
            </script>
        @endpush
    @endonce
@endif
