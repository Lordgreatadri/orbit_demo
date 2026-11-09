<x-layouts.app title="Leave">
    <div class="space-y-6" x-data="leavePage(@js($openRequest), @js($typeMeta), @js($details))" @open-leave.window="openDetail($event.detail.uuid)">
        <x-page-header title="Leave" description="Request time away when you still have days left. An approver accepts or rejects each request.">
            @if ($canRequest)
                <x-button type="button" size="sm" @click="requestOpen = true">
                    <x-icon name="plus" class="size-4" /> Request leave
                </x-button>
            @endif
        </x-page-header>

        @error('leave')<x-alert type="danger" title="Leave was not submitted">{{ $message }}</x-alert>@enderror
        @error('decision_note')<x-alert type="danger">{{ $message }}</x-alert>@enderror

        @if ($staff && $types->isEmpty())
            <x-alert type="warning" title="No leave types yet">An administrator needs to add a leave type before you can request time away.</x-alert>
        @elseif ($staff && ! $canRequest)
            <x-alert type="warning" title="No days remaining">Every leave type that uses a balance has none left. A request stays closed until an administrator adds days.</x-alert>
        @endif

        @if ($staff && $types->isNotEmpty())
            <section aria-labelledby="leave-balance-heading">
                <h2 id="leave-balance-heading" class="sr-only">Leave balances</h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($types as $type)
                        @php $meta = $typeMeta[$type->uuid] ?? null; @endphp
                        <div class="rounded-xl border border-edge bg-surface px-4 py-3">
                            <p class="text-sm font-medium text-fg">{{ $type->name }}</p>
                            <p class="mt-1 text-sm text-fg-muted">
                                @if ($meta && $meta['limited'])
                                    {{ rtrim(rtrim(number_format((float) $meta['remaining'], 1), '0'), '.') }} days remaining
                                @else
                                    No day limit
                                @endif
                            </p>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($staff)
            @foreach ([
                'pending' => ['Pending', 'Waiting for an approver.', $pending],
                'due' => ['Due', 'Approved leave that has not ended.', $due],
                'approved' => ['Approved', 'Approved leave that has already ended.', $approved],
            ] as $key => [$heading, $hint, $rows])
                <section aria-labelledby="leave-{{ $key }}-heading">
                    <div class="mb-3">
                        <h2 id="leave-{{ $key }}-heading" class="text-base font-semibold text-fg">{{ $heading }}</h2>
                        <p class="text-sm text-fg-muted">{{ $hint }}</p>
                    </div>
                    @if ($rows->isEmpty())
                        <div class="rounded-xl border border-edge bg-surface px-5 py-8 text-center">
                            <x-icon name="clock" class="mx-auto size-6 text-fg-subtle" />
                            <p class="mt-2 text-sm text-fg-muted">No {{ strtolower($heading) }} leave yet.</p>
                        </div>
                    @else
                        <ul class="space-y-3">
                            @foreach ($rows as $leaveRequest)
                                <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-edge border-l-4 border-l-primary bg-surface px-4 py-3">
                                    <div class="min-w-0">
                                        <p class="font-medium text-fg">{{ $leaveRequest->leaveType?->name }}</p>
                                        <p class="text-sm text-fg-muted">{{ $leaveRequest->starts_on->format('m/d/Y') }} – {{ $leaveRequest->ends_on->format('m/d/Y') }}</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <x-badge :tone="$leaveRequest->status->tone()">{{ $leaveRequest->status->label() }}</x-badge>
                                        <x-button type="button" variant="secondary" size="sm" x-on:click="openDetail('{{ $leaveRequest->uuid }}')">View</x-button>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endforeach

            @if ($closed->isNotEmpty())
                <section aria-labelledby="leave-closed-heading">
                    <h2 id="leave-closed-heading" class="mb-3 text-base font-semibold text-fg">Not approved</h2>
                    <ul class="space-y-3">
                        @foreach ($closed as $leaveRequest)
                            <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-edge bg-surface px-4 py-3">
                                <div>
                                    <p class="font-medium text-fg">{{ $leaveRequest->leaveType?->name }}</p>
                                    <p class="text-sm text-fg-muted">{{ $leaveRequest->starts_on->format('m/d/Y') }} – {{ $leaveRequest->ends_on->format('m/d/Y') }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <x-badge :tone="$leaveRequest->status->tone()">{{ $leaveRequest->status->label() }}</x-badge>
                                    <x-button type="button" variant="secondary" size="sm" x-on:click="openDetail('{{ $leaveRequest->uuid }}')">View</x-button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        @else
            <x-empty-state title="No person record is linked to this account" description="Please contact your organisation administrator before requesting leave." />
        @endif

        @if ($canApprove)
            <section aria-labelledby="leave-queue-heading">
                <div class="mb-3">
                    <h2 id="leave-queue-heading" class="text-base font-semibold text-fg">Requests for my approval</h2>
                    <p class="text-sm text-fg-muted">Submitted by other people in this organisation.</p>
                </div>
                @if ($queue->isEmpty())
                    <div class="rounded-xl border border-edge bg-surface px-5 py-10 text-center">
                        <x-icon name="clock" class="mx-auto size-8 text-fg-subtle" />
                        <p class="mt-3 text-sm text-fg-muted">No leave requests yet.</p>
                    </div>
                @else
                    <ul class="space-y-3">
                        @foreach ($queue as $leaveRequest)
                            <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-edge border-l-4 border-l-primary bg-surface px-4 py-3">
                                <div>
                                    <p class="font-medium text-fg">{{ $leaveRequest->staffMember?->name }}</p>
                                    <p class="text-sm text-fg-muted">{{ $leaveRequest->leaveType?->name }} · {{ $leaveRequest->starts_on->format('m/d/Y') }} – {{ $leaveRequest->ends_on->format('m/d/Y') }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <x-badge :tone="$leaveRequest->status->tone()">{{ $leaveRequest->status->label() }}</x-badge>
                                    <x-button type="button" variant="secondary" size="sm" x-on:click="openDetail('{{ $leaveRequest->uuid }}')">View</x-button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif

        @if ($canManageTypes)
            <section aria-labelledby="leave-types-heading" class="space-y-4">
                <div>
                    <h2 id="leave-types-heading" class="text-base font-semibold text-fg">Leave types</h2>
                    <p class="text-sm text-fg-muted">Types offered in this organisation. Inactive types stay on past requests and cannot be chosen for a new one.</p>
                </div>
                @if ($managedTypes->isEmpty())
                    <p class="rounded-xl border border-edge bg-surface px-5 py-8 text-center text-sm text-fg-muted">No leave types yet.</p>
                @else
                    <div class="overflow-x-auto rounded-xl border border-edge bg-surface">
                        <table class="min-w-full text-sm">
                            <caption class="sr-only">Leave types</caption>
                            <thead>
                                <tr class="border-b border-edge text-left text-fg-muted">
                                    <th scope="col" class="px-4 py-3 font-medium">Name</th>
                                    <th scope="col" class="px-4 py-3 font-medium">Code</th>
                                    <th scope="col" class="hidden px-4 py-3 font-medium sm:table-cell">Balance</th>
                                    <th scope="col" class="hidden px-4 py-3 font-medium md:table-cell">Default days</th>
                                    <th scope="col" class="px-4 py-3 font-medium">Status</th>
                                    <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($managedTypes as $type)
                                    <tr class="border-b border-edge last:border-0">
                                        <td class="px-4 py-3 font-medium text-fg">{{ $type->name }}</td>
                                        <td class="px-4 py-3 font-mono text-xs text-fg-muted">{{ $type->code }}</td>
                                        <td class="hidden px-4 py-3 text-fg-muted sm:table-cell">{{ $type->requires_balance ? 'Uses a balance' : 'No day limit' }}</td>
                                        <td class="hidden px-4 py-3 tabular-nums text-fg-muted md:table-cell">{{ $type->default_days ?? '—' }}</td>
                                        <td class="px-4 py-3"><x-badge :tone="$type->is_active ? 'success' : 'neutral'">{{ $type->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                                        <td class="px-4 py-3 text-right">
                                            <a href="{{ route('org.leave.types.edit', $type) }}" class="inline-flex size-9 items-center justify-center rounded-lg text-fg-muted hover:bg-surface-muted hover:text-primary focus-visible:outline-2" aria-label="Edit {{ $type->name }}">
                                                <x-icon name="pencil" class="size-4" />
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                <form method="POST" action="{{ route('org.leave.types.store') }}" class="grid gap-4 rounded-xl border border-edge bg-surface px-5 py-4 sm:grid-cols-2">
                    @csrf
                    <div class="sm:col-span-2">
                        <h3 class="text-sm font-medium text-fg">New leave type</h3>
                    </div>
                    <x-form.input name="name" label="Name" required />
                    <x-form.input name="code" label="Code" required />
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="paid" value="1" checked> Paid</label>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="requires_balance" value="1"> Use a balance</label>
                    <x-form.input type="number" name="default_days" label="Default days" value="0" step="1" min="0" />
                    <div class="sm:col-span-2"><x-button type="submit" size="sm">Save type</x-button></div>
                </form>
            </section>
        @endif

        <div
            x-show="requestOpen"
            x-cloak
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-4 backdrop-blur-[2px] sm:items-center"
            role="dialog"
            aria-modal="true"
            aria-labelledby="leave-request-title"
            @keydown.escape.window="requestOpen = false"
            @click.self="requestOpen = false"
        >
            <div class="card flex max-h-[min(40rem,calc(100svh-2rem))] w-full max-w-lg flex-col overflow-hidden">
                <header class="flex items-start justify-between gap-3 border-b border-edge px-5 py-4">
                    <div>
                        <h2 id="leave-request-title" class="text-lg font-semibold text-fg">New leave request</h2>
                        <p class="mt-1 text-sm text-fg-muted">Choose a type your organisation offers. The number of days is counted from the dates.</p>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-fg-muted hover:bg-surface-muted hover:text-fg" @click="requestOpen = false" aria-label="Close">
                        <x-icon name="x" class="size-5" />
                    </button>
                </header>
                <form method="POST" action="{{ route('org.leave.store') }}" enctype="multipart/form-data" class="space-y-4 overflow-y-auto px-5 py-4">
                    @csrf
                    <x-form.select name="leave_type_uuid" label="Type of leave" :options="$typeOptions" required placeholder="Choose a type" x-model="type" />
                    <p class="text-sm text-fg-muted" x-show="remainingLabel" x-text="remainingLabel"></p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-form.input type="date" name="starts_on" label="Start date" required x-model="starts" />
                        <x-form.input type="date" name="ends_on" label="End date" required x-model="ends" />
                    </div>
                    <div>
                        <label for="leave-days" class="mb-1.5 block text-sm font-medium text-fg">Number of days</label>
                        <input id="leave-days" type="text" readonly tabindex="-1" class="block h-10 w-full rounded-lg border border-edge bg-surface-muted px-3 text-sm text-fg" :value="dayCount" aria-readonly="true">
                        <p class="mt-1.5 text-xs text-fg-subtle">Calendar days, including the start and end dates.</p>
                    </div>
                    <x-form.textarea name="reason" label="Description" required rows="3" placeholder="Why you need this leave" />
                    <x-form.input type="file" name="document" label="Supporting document" hint="PDF or image, up to 2 MB. Optional." />
                    <div class="flex justify-end gap-2">
                        <x-button type="button" variant="secondary" size="sm" @click="requestOpen = false">Cancel</x-button>
                        <x-button type="submit" size="sm">Submit request</x-button>
                    </div>
                </form>
            </div>
        </div>

        <div
            x-show="detailOpen"
            x-cloak
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-4 backdrop-blur-[2px] sm:items-center"
            role="dialog"
            aria-modal="true"
            aria-labelledby="leave-detail-title"
            @keydown.escape.window="detailOpen = false"
            @click.self="detailOpen = false"
        >
            <div class="card flex max-h-[min(42rem,calc(100svh-2rem))] w-full max-w-lg flex-col overflow-hidden" x-show="entry">
                <header class="flex items-center justify-between gap-3 border-b border-edge px-5 py-4">
                    <h2 id="leave-detail-title" class="text-lg font-semibold text-fg">Leave request details</h2>
                    <button type="button" class="rounded-lg p-1.5 text-fg-muted hover:bg-surface-muted hover:text-fg" @click="detailOpen = false" aria-label="Close">
                        <x-icon name="x" class="size-5" />
                    </button>
                </header>
                <div class="space-y-4 overflow-y-auto px-5 py-4" x-show="entry">
                    <div>
                        <p class="font-medium text-fg" x-text="entry?.name"></p>
                        <p class="mt-1"><span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium" :class="toneClass(entry?.tone)" x-text="entry?.status"></span></p>
                        <p class="mt-2 text-sm text-fg" x-text="entry?.type"></p>
                        <p class="text-sm text-fg-muted" x-text="entry ? (entry.starts + ' – ' + entry.ends + ' (' + entry.days + (entry.days === 1 ? ' day' : ' days') + ' off)') : ''"></p>
                        <p class="text-sm text-fg-muted" x-show="entry" x-text="entry ? (entry.balance === null ? 'Leave balance: no day limit' : ('Leave balance: ' + entry.balance + ' days')) : ''"></p>
                    </div>
                    <section class="rounded-xl border border-edge">
                        <h3 class="border-b border-edge px-4 py-2 text-sm font-medium text-fg">Approvers</h3>
                        <div class="px-4 py-3 text-sm">
                            <template x-if="entry && entry.decider">
                                <div>
                                    <p class="font-medium text-fg" x-text="entry.decider"></p>
                                    <p class="text-fg-muted" x-text="entry.decidedAt"></p>
                                    <p class="text-fg-muted" x-text="entry.decisionNote || 'No comment added'"></p>
                                </div>
                            </template>
                            <p class="text-fg-muted" x-show="entry && ! entry.decider">Waiting for an approver.</p>
                        </div>
                    </section>
                    <section class="rounded-xl border border-edge">
                        <h3 class="border-b border-edge px-4 py-2 text-sm font-medium text-fg">Note</h3>
                        <p class="px-4 py-3 text-sm text-fg" x-text="entry?.reason"></p>
                    </section>
                    <form method="POST" :action="entry?.decideUrl" class="space-y-3" x-show="entry && entry.canDecide">
                        @csrf
                        <x-form.input name="decision_note" label="Comment" hint="Required when you reject the request." />
                        <div class="flex flex-wrap gap-2">
                            <x-button type="submit" name="decision" value="approve" size="sm">Approve</x-button>
                            <x-button type="submit" name="decision" value="reject" size="sm" variant="danger-outline">Reject</x-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('leavePage', (requestOpen, types, details) => ({
                    requestOpen,
                    type: @js(old('leave_type_uuid', '')),
                    starts: @js(old('starts_on', '')),
                    ends: @js(old('ends_on', '')),
                    types,
                    details,
                    detailOpen: false,
                    entry: null,
                    get dayCount() {
                        if (!this.starts || !this.ends) return '';
                        const start = new Date(this.starts + 'T00:00:00');
                        const end = new Date(this.ends + 'T00:00:00');
                        if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end < start) return '';
                        return String(Math.round((end - start) / 86400000) + 1);
                    },
                    get remainingLabel() {
                        const row = this.types[this.type];
                        if (!row || !row.limited || row.remaining === null) return '';
                        return row.remaining + ' days remaining for ' + row.name + '.';
                    },
                    openDetail(uuid) {
                        this.entry = this.details.find((row) => row.uuid === uuid) ?? null;
                        this.detailOpen = this.entry !== null;
                    },
                    toneClass(tone) {
                        return {
                            success: 'border-success/30 bg-success-soft text-success',
                            warning: 'border-warning/30 bg-warning-soft text-warning',
                            danger: 'border-danger/30 bg-danger-soft text-danger',
                            neutral: 'border-edge bg-surface-muted text-fg-muted',
                        }[tone] || 'border-edge bg-surface-muted text-fg-muted';
                    },
                }));
            });
        </script>
    @endpush
</x-layouts.app>
