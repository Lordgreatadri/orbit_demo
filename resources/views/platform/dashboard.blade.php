<x-layouts.app title="Platform overview" wide>
    @php
        $total = (int) $counts['organizations'];
        $activeShare = $total > 0 ? (int) round($counts['organizations_active'] / $total * 100) : null;
        $signInAttempts = (int) $counts['logins_24h'] + (int) $counts['failed_24h'];
        $failedShare = $signInAttempts > 0 ? (int) round($counts['failed_24h'] / $signInAttempts * 100) : null;
        $registrationsInRange = (int) collect($registrations)->sum('total');
        $thisWeek = (int) (collect($registrations)->last()['total'] ?? 0);

        $items = [];
        if ($counts['organizations_pending'] > 0) {
            $items[] = ['tone' => 'warning', 'icon' => 'building', 'statement' => number_format($counts['organizations_pending']).' '.($counts['organizations_pending'] === 1 ? 'organisation is' : 'organisations are').' pending verification.', 'meta' => 'Tenant onboarding', 'href' => $user->can('platform.organizations.view') ? route('admin.organizations.index', ['status' => 'pending_verification']) : null, 'action' => 'Review organisations'];
        }
        if ($counts['organizations_suspended'] > 0) {
            $items[] = ['tone' => 'danger', 'icon' => 'ban', 'statement' => number_format($counts['organizations_suspended']).' '.($counts['organizations_suspended'] === 1 ? 'organisation is' : 'organisations are').' suspended.', 'meta' => 'Tenant health', 'href' => $user->can('platform.organizations.view') ? route('admin.organizations.index', ['status' => 'suspended']) : null, 'action' => 'Open organisations'];
        }
        if ($counts['lockouts_24h'] > 0 || $counts['failed_24h'] > 0) {
            $items[] = ['tone' => $counts['lockouts_24h'] > 0 ? 'danger' : 'warning', 'icon' => 'lock', 'statement' => number_format($counts['failed_24h']).' failed '.($counts['failed_24h'] === 1 ? 'sign-in' : 'sign-ins').' and '.number_format($counts['lockouts_24h']).' '.($counts['lockouts_24h'] === 1 ? 'lockout' : 'lockouts').' across the platform.', 'meta' => 'Last 24 hours', 'href' => $user->can('platform.audit.view') ? route('admin.audit.logins') : null, 'action' => 'Sign-in history'];
        }
        if ($sms && $sms['totals']['failed'] > 0) {
            $items[] = ['tone' => 'warning', 'icon' => 'chat', 'statement' => number_format($sms['totals']['failed']).' SMS '.($sms['totals']['failed'] === 1 ? 'message' : 'messages').' failed to send.', 'meta' => 'Last 7 days', 'href' => route('admin.sms.overview'), 'action' => 'SMS overview'];
        }
        if ($sms && $sms['active'] === null) {
            $items[] = ['tone' => 'info', 'icon' => 'settings', 'statement' => 'No SMS provider is active and enabled.', 'meta' => 'Messaging', 'href' => route('admin.sms-providers.index'), 'action' => 'SMS providers'];
        }

        $checks = [
            ['No organisation is waiting for verification', $counts['organizations_pending'] === 0],
            ['No organisation is suspended', $counts['organizations_suspended'] === 0],
            ['No failed sign-ins or lockouts in 24 hours', $counts['failed_24h'] === 0 && $counts['lockouts_24h'] === 0],
        ];
        if ($sms) {
            $checks[] = ['No SMS failures in 7 days', $sms['totals']['failed'] === 0];
            $checks[] = ['An SMS provider is active', $sms['active'] !== null];
        }

        $headline = $items === []
            ? 'The platform is steady.'
            : count($items).' platform '.(count($items) === 1 ? 'signal needs' : 'signals need').' a look.';
        $subline = number_format($counts['organizations_active']).' of '.number_format($total).' organisations are active, with '.number_format($counts['users_tenant']).' tenant accounts.';
    @endphp

    <x-page-header title="Platform overview" :description="'Signed in as '.$user->name.'. Every cross-organisation action from this console is written to the audit trail.'" eyebrow="Platform console">
        <x-button :href="route('admin.statistics')" variant="secondary" size="sm"><x-icon name="chart" class="size-4" /> Statistics</x-button>
        @can('platform.organizations.view')
            <x-button :href="route('admin.organizations.index')" variant="secondary" size="sm"><x-icon name="building" class="size-4" /> Organisations</x-button>
        @endcan
        @can('createPlatformAccount', \App\Models\User::class)
            <x-button :href="route('admin.users.create')" size="sm"><x-icon name="user-plus" class="size-4" /> Platform account</x-button>
        @endcan
    </x-page-header>

    {{-- Level 1 · Platform briefing --}}
    <section aria-labelledby="platform-briefing" class="briefing overflow-hidden">
        <div class="grid lg:grid-cols-12">
            <div class="flex flex-col justify-between gap-5 p-5 sm:p-6 lg:col-span-5 lg:border-r lg:border-edge">
                <div>
                    <p class="inline-flex items-center gap-2 text-xs font-semibold text-primary">
                        <span class="grid size-6 place-items-center rounded-md bg-primary text-primary-fg" aria-hidden="true"><x-icon name="sparkles" class="size-3.5" /></span>
                        Platform briefing
                    </p>
                    <h2 id="platform-briefing" class="mt-3 text-[1.3125rem] leading-snug font-semibold tracking-tight text-balance text-fg sm:text-[1.5rem]">{{ $headline }}</h2>
                    <p class="mt-1 text-sm text-fg-muted">{{ $subline }}</p>
                    <p class="mt-2 text-xs text-fg-subtle">Aggregate counts only. This console never opens a tenant's attendance, leave, or schedules.</p>
                </div>
                @if ($activeShare !== null)
                    <div>
                        <div class="flex items-baseline justify-between gap-3 text-xs">
                            <span class="font-medium text-fg-muted">Active organisations</span>
                            <span class="tabular-nums text-fg-muted"><span class="font-semibold text-fg">{{ number_format($counts['organizations_active']) }}</span> of {{ number_format($total) }} · {{ $activeShare }}%</span>
                        </div>
                        <div class="mt-2 meter h-2"><span class="bg-primary" style="width: {{ $activeShare }}%"></span></div>
                    </div>
                @endif
            </div>
            <div class="border-t border-edge p-5 sm:p-6 lg:col-span-7 lg:border-t-0">
                @if ($items === [])
                    <h3 class="text-sm font-semibold text-fg">Health checks</h3>
                    <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                        @foreach ($checks as [$label, $passed])
                            <li class="flex items-start gap-2.5 rounded-lg border border-edge bg-surface px-3 py-2.5 text-sm">
                                <x-icon :name="$passed ? 'check-circle' : 'alert'" class="mt-0.5 size-4 shrink-0 {{ $passed ? 'text-success' : 'text-warning' }}" />
                                <span class="text-fg">{{ $label }}<span class="sr-only">: {{ $passed ? 'passed' : 'needs a look' }}</span></span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <h3 class="text-sm font-semibold text-fg">Signals</h3>
                    <ul class="mt-3 divide-y divide-edge">
                        @foreach ($items as $item)
                            <li class="flex flex-col gap-3 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center">
                                <div class="rail rail-{{ $item['tone'] }} flex min-w-0 flex-1 items-start gap-3">
                                    <span class="icon-well tone-{{ $item['tone'] }}" aria-hidden="true"><x-icon :name="$item['icon']" class="size-4" /></span>
                                    <div class="min-w-0">
                                        <p class="text-[0.9375rem] leading-snug font-medium text-fg">{{ $item['statement'] }}</p>
                                        <p class="mt-0.5 text-xs text-fg-subtle">{{ $item['meta'] }}</p>
                                    </div>
                                </div>
                                @if ($item['href'])
                                    <a href="{{ $item['href'] }}" class="inline-flex min-h-11 shrink-0 items-center gap-1.5 self-start rounded-lg border border-edge bg-surface px-3 text-sm font-medium text-fg transition-colors hover:border-edge-strong hover:bg-surface-muted focus-visible:outline-2 sm:self-center">{{ $item['action'] }} <x-icon name="arrow-right" class="size-3.5 text-primary" /></a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </section>

    {{-- Level 2 · Position --}}
    <section aria-labelledby="platform-kpis">
        <div class="mb-3 flex items-baseline justify-between gap-4">
            <h2 id="platform-kpis" class="section-title">Key figures</h2>
            <p class="section-meta">All organisations · {{ now()->format('D j M, H:i') }} UTC</p>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 xl:grid-cols-5">
            <x-stat label="Organisations" :value="number_format($counts['organizations'])" :hint="$counts['organizations_active'].' active · '.$counts['organizations_pending'].' pending'" icon="building" tone="primary" :meter="$activeShare" :href="$user->can('platform.organizations.view') ? route('admin.organizations.index') : null" />
            <x-stat label="New in 30 days" :value="number_format($counts['new_organizations_30d'])" :hint="$thisWeek.' this week'" icon="sparkles" tone="accent" />
            <x-stat label="Tenant accounts" :value="number_format($counts['users_tenant'])" :hint="$counts['users_platform'].' platform accounts'" icon="users" tone="success" :href="$user->can('platform.users.view') ? route('admin.users.index') : null" />
            <x-stat label="Staff records" :value="number_format($counts['staff_records'])" hint="Across all organisations" icon="id-card" tone="neutral" />
            <x-stat class="col-span-2 sm:col-span-1" label="Sign-ins · 24 h" :value="number_format($counts['logins_24h'])" :hint="$failedShare !== null ? $failedShare.'% of attempts failed' : 'No sign-in attempts'" icon="login" tone="info" :emphasis="$counts['lockouts_24h'] > 0 ? 'danger' : null" :href="$user->can('platform.audit.view') ? route('admin.audit.logins') : null" />
        </div>
    </section>

    {{-- Level 3 · Growth and health --}}
    <div class="grid gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2" icon="chart" title="Organisation registrations" :description="number_format($registrationsInRange).' new organisations in the last eight weeks. Weeks start on Monday. No tenant operations are charted here.'" decision="whether onboarding capacity and verification staffing keep pace">
            <x-charts.columns :rows="$registrations" height="13rem" :highlight-last="true" caption="Organisations registered in each of the last eight weeks" />
        </x-card>

        <x-card icon="shield-check" title="Tenant health" description="Each organisation counted once, by its current status." decision="which tenants need verification, support, or offboarding">
            <x-charts.donut :segments="$organisationStatuses" :center="number_format($total)" center-label="organisations" size="9rem" caption="Organisations by status" empty="No organisations are registered yet." />
        </x-card>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        @if ($sms)
            @php
                $smsSeries = [
                    ['key' => 'delivered', 'label' => 'Delivered', 'ink' => 'var(--success)'],
                    ['key' => 'accepted', 'label' => 'Accepted, waiting', 'ink' => 'var(--info)'],
                    ['key' => 'failed', 'label' => 'Failed', 'ink' => 'var(--danger)'],
                    ['key' => 'other', 'label' => 'Other', 'ink' => 'var(--fg-subtle)'],
                ];
                $smsRows = collect($sms['days'])->map(fn ($day) => $day + ['date' => $day['key']])->all();
            @endphp
            <x-card class="lg:col-span-2" icon="chat" :title="$sms['rate'] === null ? 'No delivery rate yet' : $sms['rate'].'% confirmed delivered'" :description="'Last 7 days. '.number_format($sms['totals']['sent']).' sent · '.number_format($sms['totals']['accepted']).' accepted and still waiting · '.number_format($sms['totals']['failed']).' failed.'" decision="whether to switch provider, top up, or investigate failures">
                <x-slot:actions><x-button :href="route('admin.sms.overview')" variant="secondary" size="sm">SMS overview <x-icon name="arrow-right" class="size-3.5" /></x-button></x-slot:actions>
                @if ($sms['totals']['sent'] + $sms['totals']['failed'] + $sms['totals']['accepted'] + $sms['totals']['delivered'] > 0)
                    <x-charts.stacked :rows="$smsRows" :series="$smsSeries" height="8.5rem" caption="SMS messages per day by delivery status, last 7 days" />
                @else
                    <div class="flex items-center gap-3 rounded-lg bg-surface-muted px-4 py-6">
                        <x-icon name="chat" class="size-5 shrink-0 text-fg-subtle" />
                        <p class="text-sm text-fg-muted">No SMS was sent in the last 7 days, so no plot is drawn. {{ $sms['active'] ? 'The active provider is '.$sms['active']->name.'.' : 'No provider is active.' }}</p>
                    </div>
                @endif
            </x-card>
        @endif

        <x-card :class="$sms ? '' : 'lg:col-span-3'" icon="lock" title="Security · last 24 h" decision="whether an account or tenant is under a credential attack">
            <x-slot:actions>@can('platform.audit.view')<a href="{{ route('admin.audit.logins') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-primary hover:underline">History</a>@endcan</x-slot:actions>
            <dl class="divide-y divide-edge rounded-lg border border-edge">
                @foreach ([['Successful sign-ins', $counts['logins_24h'], 'success'], ['Failed sign-ins', $counts['failed_24h'], 'warning'], ['Lockouts', $counts['lockouts_24h'], 'danger']] as [$label, $value, $tone])
                    <div class="flex items-center justify-between gap-3 px-3 py-2.5">
                        <dt class="inline-flex items-center gap-2 text-sm text-fg-muted"><span @class(['size-2 rounded-full', 'bg-success' => $tone === 'success', 'bg-warning' => $tone === 'warning', 'bg-danger' => $tone === 'danger']) aria-hidden="true"></span>{{ $label }}</dt>
                        <dd @class(['text-lg font-semibold tabular-nums', 'text-danger' => $tone === 'danger' && $value > 0, 'text-fg' => ! ($tone === 'danger' && $value > 0)])>{{ number_format($value) }}</dd>
                    </div>
                @endforeach
            </dl>
            @if ($failedShare !== null)
                <div class="mt-4">
                    <div class="flex items-baseline justify-between text-xs"><span class="text-fg-muted">Failed share of attempts</span><span class="font-semibold tabular-nums text-fg">{{ $failedShare }}%</span></div>
                    <div class="mt-1.5 meter"><span class="bg-warning" style="width: {{ $failedShare }}%"></span></div>
                </div>
            @endif
            <div class="mt-5 border-t border-edge pt-4">
                <p class="text-xs font-medium text-fg-muted">Access control</p>
                <ul class="mt-2 grid gap-1 text-sm">
                    @can('platform.users.view')<li><a href="{{ route('admin.users.index') }}" class="flex min-h-11 items-center justify-between rounded-md px-2 text-fg hover:bg-surface-muted hover:text-primary"><span class="inline-flex items-center gap-2"><x-icon name="users" class="size-4 text-fg-subtle" /> All user accounts</span><x-icon name="chevron-right" class="size-4 text-fg-subtle" /></a></li>@endcan
                    @can('viewAny', \App\Models\Role::class)<li><a href="{{ route('admin.roles.index') }}" class="flex min-h-11 items-center justify-between rounded-md px-2 text-fg hover:bg-surface-muted hover:text-primary"><span class="inline-flex items-center gap-2"><x-icon name="key" class="size-4 text-fg-subtle" /> Roles</span><x-icon name="chevron-right" class="size-4 text-fg-subtle" /></a></li>@endcan
                    @can('viewAny', \App\Models\Permission::class)<li><a href="{{ route('admin.permissions.index') }}" class="flex min-h-11 items-center justify-between rounded-md px-2 text-fg hover:bg-surface-muted hover:text-primary"><span class="inline-flex items-center gap-2"><x-icon name="shield-check" class="size-4 text-fg-subtle" /> Permission catalogue</span><x-icon name="chevron-right" class="size-4 text-fg-subtle" /></a></li>@endcan
                </ul>
            </div>
        </x-card>
    </div>

    {{-- Level 4 · Recent records --}}
    <div class="grid gap-6 lg:grid-cols-2">
        @can('platform.organizations.view')
            <x-card icon="building" title="Recently registered organisations" :padding="false">
                <x-slot:actions><a href="{{ route('admin.organizations.index') }}" class="inline-flex min-h-11 items-center gap-1 text-sm font-medium text-primary hover:underline">All organisations <x-icon name="arrow-right" class="size-3.5" /></a></x-slot:actions>
                @if ($recentOrganizations->isEmpty())
                    <div class="p-5"><x-empty-state title="No organisations yet" /></div>
                @else
                    <ul class="divide-y divide-edge">
                        @foreach ($recentOrganizations as $org)
                            <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm transition-colors hover:bg-surface-muted/50">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span aria-hidden="true" class="grid size-9 shrink-0 place-items-center rounded-lg bg-surface-muted text-xs font-semibold text-fg-muted">{{ \App\Support\Monogram::for($org->name) }}</span>
                                    <div class="min-w-0">
                                        <a href="{{ route('admin.organizations.show', $org) }}" class="block truncate font-medium text-fg hover:text-primary hover:underline">{{ $org->name }}</a>
                                        <p class="truncate text-xs text-fg-subtle">{{ $org->type->label() }} · {{ $org->users_count }} {{ $org->users_count === 1 ? 'account' : 'accounts' }} · {{ $org->created_at->diffForHumans() }}</p>
                                    </div>
                                </div>
                                <x-badge :tone="$org->status->tone()">{{ $org->status->label() }}</x-badge>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        @endcan

        @can('platform.audit.view')
            <x-card icon="document-check" title="Latest audit entries" description="Across all organisations and the platform." :padding="false">
                <x-slot:actions><a href="{{ route('admin.audit.index') }}" class="inline-flex min-h-11 items-center gap-1 text-sm font-medium text-primary hover:underline">Audit trail <x-icon name="arrow-right" class="size-3.5" /></a></x-slot:actions>
                @if ($recentActivity->isEmpty())
                    <div class="p-5"><x-empty-state title="No activity recorded yet" /></div>
                @else
                    <ol class="divide-y divide-edge">
                        @foreach ($recentActivity->take(6) as $activity)
                            <li class="flex items-start justify-between gap-3 px-5 py-3 text-sm">
                                <div class="flex min-w-0 items-start gap-3">
                                    <span class="mt-1.5 size-2 shrink-0 rounded-full bg-primary/70" aria-hidden="true"></span>
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-fg">{{ $activity->eventLabel() }}</p>
                                        <p class="truncate text-xs text-fg-muted">{{ $activity->causer?->name ?? 'System' }} · {{ $activity->organization?->name ?? 'Platform' }}</p>
                                    </div>
                                </div>
                                <time datetime="{{ $activity->created_at->toIso8601String() }}" class="shrink-0 text-xs text-fg-subtle">{{ $activity->created_at->diffForHumans() }}</time>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-card>
        @endcan
    </div>
</x-layouts.app>
