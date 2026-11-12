<x-layouts.app title="Platform statistics" wide>
    @php
        $orgs = $stats['organisations'];
        $accounts = $stats['accounts'];
        $staff = $stats['staff'];
        $invites = $stats['invitations'];
        $growth = $stats['growth'];
        $signIns = $stats['sign_ins'];
        $activeOrgs = (int) (collect($orgs['by_status'])->firstWhere('key', 'active')['total'] ?? 0);
        $activeOrgShare = $orgs['total'] > 0 ? (int) round($activeOrgs / $orgs['total'] * 100) : null;
        $engaged = $accounts['total'] > 0 ? (int) round(min($accounts['signed_in_30d'], $accounts['total']) / $accounts['total'] * 100) : null;
        $verifiedShare = $accounts['total'] > 0 ? (int) round($accounts['verified'] / $accounts['total'] * 100) : null;
        $staffActiveShare = $staff['total'] > 0 ? (int) round($staff['active'] / $staff['total'] * 100) : null;
        $linkedRatio = $staff['active'] > 0 ? round($accounts['tenant'] / $staff['active'], 2) : null;
        $signInSeries = [
            ['key' => 'login', 'label' => 'Signed in', 'ink' => 'var(--success)'],
            ['key' => 'failed', 'label' => 'Failed', 'ink' => 'var(--warning)'],
            ['key' => 'lockout', 'label' => 'Lockout', 'ink' => 'var(--danger)'],
        ];
        $invitationRows = [
            ['key' => 'accepted', 'label' => 'Accepted', 'total' => $invites['accepted'], 'ink' => 'var(--success)'],
            ['key' => 'pending', 'label' => 'Pending', 'total' => $invites['pending'], 'ink' => 'var(--info)'],
            ['key' => 'expired', 'label' => 'Expired', 'total' => $invites['expired'], 'ink' => 'var(--warning)'],
            ['key' => 'revoked', 'label' => 'Revoked', 'total' => $invites['revoked'], 'ink' => 'var(--fg-subtle)'],
        ];
        $educational = (int) (collect($orgs['by_type'])->firstWhere('key', 'educational_institution')['total'] ?? 0);
    @endphp

    <x-page-header title="Platform statistics" description="Growth, composition, adoption, and sign-in health across every organisation. Aggregates only: no tenant attendance, leave, or schedules, and no member names." eyebrow="Platform console">
        <x-button :href="route('admin.dashboard')" variant="secondary" size="sm"><x-icon name="home" class="size-4" /> Overview</x-button>
        <x-button :href="route('admin.workforce.overview')" variant="secondary" size="sm"><x-icon name="users" class="size-4" /> Workforce overview</x-button>
    </x-page-header>

    <section aria-labelledby="stats-kpis">
        <div class="mb-3 flex items-baseline justify-between gap-4">
            <h2 id="stats-kpis" class="section-title">Platform at a glance</h2>
            <p class="section-meta">Calculated {{ now()->format('D j M Y, H:i') }} UTC</p>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 xl:grid-cols-5">
            <x-stat label="Organisations" :value="number_format($orgs['total'])" :hint="$activeOrgShare !== null ? $activeOrgShare.'% active' : 'None registered'" icon="building" tone="primary" :meter="$activeOrgShare" :href="$user->can('platform.organizations.view') ? route('admin.organizations.index') : null" />
            <x-stat label="Tenant accounts" :value="number_format($accounts['tenant'])" :hint="number_format($accounts['platform']).' platform · '.number_format($accounts['suspended']).' suspended'" icon="users" tone="success" :href="$user->can('platform.users.view') ? route('admin.users.index') : null" />
            <x-stat label="Signed in · 30 days" :value="number_format($accounts['signed_in_30d'])" :hint="$engaged !== null ? $engaged.'% of all accounts' : 'No accounts yet'" icon="login" tone="info" :meter="$engaged" />
            <x-stat label="Staff records" :value="number_format($staff['total'])" :hint="number_format($staff['active']).' active on registers'" icon="id-card" tone="neutral" :meter="$staffActiveShare" />
            <x-stat class="col-span-2 sm:col-span-1" label="Invitations pending" :value="number_format($invites['pending'])" :hint="$invites['acceptance'] !== null ? $invites['acceptance'].'% of decided invitations accepted' : 'No invitation decided yet'" icon="mail" tone="warning" />
        </div>
    </section>

    {{-- Growth --}}
    <div class="grid gap-6 lg:grid-cols-2">
        <x-card icon="building" :title="number_format($growth['organisations_total']).' new '.($growth['organisations_total'] === 1 ? 'organisation' : 'organisations').' in 12 months'" description="Organisations registered each calendar month (UTC). The current month is highlighted." decision="whether onboarding and verification capacity keeps pace with demand">
            <x-charts.columns :rows="$growth['organisations']" height="11rem" :highlight-last="true" :column-width="2" caption="Organisations registered per month, last 12 months" />
        </x-card>
        <x-card icon="user-plus" :title="number_format($growth['accounts_total']).' new tenant '.($growth['accounts_total'] === 1 ? 'account' : 'accounts').' in 12 months'" description="Organisation accounts created each calendar month, by registration or accepted invitation." decision="whether organisations are inviting their people after they register">
            <x-charts.columns :rows="$growth['accounts']" height="11rem" :highlight-last="true" :column-width="2" caption="Tenant accounts created per month, last 12 months" />
        </x-card>
    </div>

    {{-- Composition --}}
    <div class="grid gap-6 lg:grid-cols-3">
        <x-card icon="layers" title="Organisations by type" description="Each organisation counted once." decision="which sectors to prioritise for support and guidance">
            <x-charts.donut :segments="$orgs['by_type']" :center="number_format($orgs['total'])" center-label="organisations" size="8.5rem" caption="Organisations by type" empty="No organisations are registered yet." />
            @if ($educational > 0)
                <p class="mt-4 flex items-start gap-2 rounded-lg bg-accent-soft px-3 py-2 text-xs text-fg"><x-icon name="academic-cap" class="mt-px size-3.5 shrink-0 text-accent" /> {{ number_format($educational) }} educational {{ $educational === 1 ? 'institution uses' : 'institutions use' }} school wording for roles and the student workspace.</p>
            @endif
        </x-card>

        <x-card icon="shield-check" title="Tenant health" description="Organisations by current status." decision="which tenants need verification, support, or offboarding">
            <x-charts.bars :rows="$orgs['by_status']" caption="Organisations by status" />
        </x-card>

        <x-card icon="id-card" title="Register composition" description="Staff records on every register, by status; active people by work type." decision="how much field and remote check-in support the platform must carry">
            <x-charts.bars :rows="$staff['by_status']" caption="Staff records by status" />
            <div class="mt-5 border-t border-edge pt-4">
                <p class="text-xs font-medium text-fg-muted">Active people by work type</p>
                @php $workTotal = max(1, (int) collect($staff['by_work_type'])->sum('total')); @endphp
                <div class="mt-2 flex h-2.5 gap-0.5 overflow-hidden rounded-full bg-surface-muted" role="presentation">
                    @foreach ($staff['by_work_type'] as $row)
                        @if ($row['total'] > 0)
                            <span class="h-full" style="width: {{ round($row['total'] / $workTotal * 100, 2) }}%; background: {{ $row['ink'] }}"></span>
                        @endif
                    @endforeach
                </div>
                <ul class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                    @foreach ($staff['by_work_type'] as $row)
                        <li class="flex items-center justify-between gap-2"><span class="inline-flex items-center gap-1.5 text-fg-muted"><span class="size-2 rounded-full" style="background: {{ $row['ink'] }}" aria-hidden="true"></span>{{ $row['label'] }}</span><span class="font-semibold tabular-nums text-fg">{{ number_format($row['total']) }}</span></li>
                    @endforeach
                </ul>
            </div>
        </x-card>
    </div>

    {{-- Adoption and access --}}
    <div class="grid gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2" icon="lock" :title="$signIns['success'] === null ? 'No sign-in attempts in 14 days' : $signIns['success'].'% of sign-in attempts succeeded in 14 days'" :description="number_format($signIns['login']).' sign-ins · '.number_format($signIns['failed']).' failed · '.number_format($signIns['lockout']).' lockouts, across every organisation and the platform.'" decision="whether a spike in failures or lockouts needs an investigation">
            @can('platform.audit.view')
                <x-slot:actions><a href="{{ route('admin.audit.logins') }}" class="inline-flex min-h-11 items-center gap-1 text-sm font-medium text-primary hover:underline">Sign-in history <x-icon name="arrow-right" class="size-3.5" /></a></x-slot:actions>
            @endcan
            @if ($signIns['login'] + $signIns['failed'] + $signIns['lockout'] > 0)
                <x-charts.stacked :rows="$signIns['days']" :series="$signInSeries" height="15rem" caption="Sign-in events per day by outcome, last 14 days" />
            @else
                <div class="flex items-center gap-3 rounded-lg bg-surface-muted px-4 py-6">
                    <x-icon name="login" class="size-5 shrink-0 text-fg-subtle" />
                    <p class="text-sm text-fg-muted">No sign-in events in the last 14 days, so no plot is drawn.</p>
                </div>
            @endif
        </x-card>

        <x-card icon="mail" title="Invitations" :description="number_format($invites['issued']).' issued by organisation administrators in total.'" decision="whether invitations are reaching people before they expire">
            <div class="flex items-center gap-4">
                <x-charts.gauge :value="$invites['acceptance']" label="accepted" tone="success" size="6.5rem" />
                <div class="min-w-0 text-sm">
                    <p class="text-fg"><span class="text-2xl font-semibold tabular-nums">{{ number_format($invites['accepted']) }}</span> <span class="text-fg-muted">accepted</span></p>
                    <p class="mt-0.5 text-fg-muted">{{ number_format($invites['pending']) }} still pending</p>
                </div>
            </div>
            <p class="mt-3 text-xs leading-relaxed text-fg-subtle">The rate counts decided invitations only: accepted, expired, or revoked.</p>
            <div class="mt-4">
                <x-charts.bars :rows="$invitationRows" caption="Invitations by status" />
            </div>
        </x-card>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card icon="users" title="Account health" description="All accounts, platform and tenant." decision="whether verification or suspensions need follow-up">
            <dl class="space-y-4 text-sm">
                <div>
                    <div class="flex items-baseline justify-between gap-3"><dt class="text-fg-muted">E-mail verified</dt><dd class="tabular-nums"><span class="font-semibold text-fg">{{ number_format($accounts['verified']) }}</span> <span class="text-xs text-fg-subtle">of {{ number_format($accounts['total']) }}</span></dd></div>
                    <div class="mt-1.5 meter"><span class="bg-success" style="width: {{ $verifiedShare ?? 0 }}%"></span></div>
                </div>
                <div>
                    <div class="flex items-baseline justify-between gap-3"><dt class="text-fg-muted">Active tenant accounts</dt><dd class="tabular-nums"><span class="font-semibold text-fg">{{ number_format($accounts['tenant_active']) }}</span> <span class="text-xs text-fg-subtle">of {{ number_format($accounts['tenant']) }}</span></dd></div>
                    <div class="mt-1.5 meter"><span class="bg-primary" style="width: {{ $accounts['tenant'] > 0 ? round($accounts['tenant_active'] / $accounts['tenant'] * 100, 2) : 0 }}%"></span></div>
                </div>
                <div class="flex items-baseline justify-between gap-3 border-t border-edge pt-3"><dt class="text-fg-muted">Suspended accounts</dt><dd @class(['font-semibold tabular-nums', 'text-danger' => $accounts['suspended'] > 0, 'text-fg' => $accounts['suspended'] === 0])>{{ number_format($accounts['suspended']) }}</dd></div>
                <div class="flex items-baseline justify-between gap-3"><dt class="text-fg-muted">Tenant accounts per active staff record</dt><dd class="font-semibold tabular-nums text-fg">{{ $linkedRatio === null ? '—' : number_format($linkedRatio, 2) }}</dd></div>
            </dl>
        </x-card>

        <x-card class="lg:col-span-2" icon="building" title="Largest organisations" description="By staff records on the register. Counts only." :padding="false" decision="where platform load and support demand concentrate">
            @if ($stats['largest']->isEmpty())
                <p class="px-5 py-4 text-sm text-fg-muted">No organisations are registered yet.</p>
            @else
                @php $largestMax = max(1, (int) $stats['largest']->max('staff_count')); @endphp
                <x-data-table caption="Largest organisations by staff records">
                    <thead>
                        <tr>
                            <th scope="col">Organisation</th>
                            <th scope="col" class="min-w-[10rem]">Staff records</th>
                            <th scope="col" class="hidden text-right sm:table-cell">Active</th>
                            <th scope="col" class="text-right">Accounts</th>
                            <th scope="col" class="hidden md:table-cell">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($stats['largest'] as $organization)
                            <tr>
                                <td>
                                    @can('platform.organizations.view')
                                        <a href="{{ route('admin.organizations.show', $organization) }}" class="font-medium text-fg hover:text-primary hover:underline">{{ $organization->name }}</a>
                                    @else
                                        <span class="font-medium text-fg">{{ $organization->name }}</span>
                                    @endcan
                                    <span class="block text-xs text-fg-subtle">{{ $organization->type->label() }} · since {{ $organization->created_at->format('M Y') }}</span>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <div class="meter h-2 flex-1"><span class="bg-primary" style="width: {{ round($organization->staff_count / $largestMax * 100, 2) }}%"></span></div>
                                        <span class="w-8 text-right font-semibold tabular-nums text-fg">{{ number_format($organization->staff_count) }}</span>
                                    </div>
                                </td>
                                <td class="hidden text-right tabular-nums sm:table-cell">{{ number_format($organization->active_staff_count) }}</td>
                                <td class="text-right tabular-nums">{{ number_format($organization->users_count) }}</td>
                                <td class="hidden md:table-cell"><x-badge :tone="$organization->status->tone()">{{ $organization->status->label() }}</x-badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-data-table>
            @endif
        </x-card>
    </div>
</x-layouts.app>
