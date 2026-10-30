<x-layouts.public description="ORBIT is a centralised attendance, workforce scheduling, and operational visibility platform for schools, businesses, public institutions, NGOs, and field-based teams.">
    {{-- Hero --}}
    <section class="relative isolate overflow-hidden border-b border-edge bg-surface">
        <div class="bg-mesh absolute inset-0 -z-10" aria-hidden="true"></div>
        <div class="bg-grid absolute inset-0 -z-10" aria-hidden="true"></div>
        <div aria-hidden="true" class="absolute -top-40 right-[-10rem] -z-10 size-[34rem] rounded-full bg-primary/10 blur-3xl"></div>

        <div class="mx-auto grid max-w-7xl items-center gap-14 px-4 pt-16 pb-20 sm:px-6 lg:grid-cols-12 lg:px-8 lg:pt-24 lg:pb-28">
            <div class="lg:col-span-7">
                <p class="eyebrow">{{ config('orbit.product_tagline') }}</p>
                <h1 class="mt-5 text-[2.75rem] leading-[1.05] font-semibold tracking-tight text-balance text-fg sm:text-6xl lg:text-[4.25rem]">
                    One clear view of attendance, schedules, and <span class="text-gradient-brand">where work happens.</span>
                </h1>
                <p class="mt-7 max-w-xl text-lg leading-relaxed text-pretty text-fg-muted sm:text-xl">ORBIT replaces paper registers and scattered spreadsheets with QR and location-aware check-in, shift schedules, leave workflows, and reports your administrators can act on.</p>

                <div class="mt-10 flex flex-wrap items-center gap-3">
                    <x-button :href="route('register')" size="lg">Register your organisation <x-icon name="arrow-right" class="size-4" /></x-button>
                    <x-button :href="route('login')" variant="secondary" size="lg">Sign in</x-button>
                    <x-button :href="route('contact')" variant="ghost" size="lg">Request a demo</x-button>
                </div>

                <p class="mt-6 flex max-w-xl items-start gap-2.5 text-[0.9375rem] text-fg-subtle">
                    <x-icon name="lock" class="mt-0.5 size-4 shrink-0 text-primary" />
                    <span>Organisation registration is for the responsible contact. Staff, students, and field officers are invited by their organisation's administrator.</span>
                </p>

                <ul class="mt-10 flex flex-wrap gap-x-8 gap-y-3 text-sm font-medium text-fg-muted" aria-label="Who ORBIT is designed for">
                    @foreach ([['landmark', 'Ministries & agencies'], ['academic-cap', 'Universities & schools'], ['building', 'Businesses'], ['heart-hand', 'NGOs']] as [$icon, $label])
                        <li class="inline-flex items-center gap-2"><x-icon :name="$icon" class="size-4 text-primary" /> {{ $label }}</li>
                    @endforeach
                </ul>
            </div>

            {{-- Illustrative product preview --}}
            <div class="relative lg:col-span-5" aria-label="Illustrative example of an attendance summary">
                <div aria-hidden="true" class="absolute -inset-4 -z-10 rounded-[2rem] bg-gradient-to-br from-primary/15 via-transparent to-accent/15 blur-2xl"></div>

                <div class="overflow-hidden rounded-2xl border border-edge bg-surface shadow-lift">
                    <div class="flex items-center justify-between border-b border-edge bg-surface-muted/60 px-4 py-2.5">
                        <div class="flex items-center gap-1.5" aria-hidden="true">
                            <span class="size-2.5 rounded-full bg-danger/60"></span>
                            <span class="size-2.5 rounded-full bg-warning/60"></span>
                            <span class="size-2.5 rounded-full bg-success/60"></span>
                        </div>
                        <p class="text-xs font-medium text-fg-subtle">Today at a glance</p>
                        <x-badge tone="neutral">Illustrative</x-badge>
                    </div>

                    <div class="p-5">
                        <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            @foreach ([['Present', 'success', 'bg-success w-4/5'], ['Late', 'warning', 'bg-warning w-2/5'], ['Absent', 'danger', 'bg-danger w-1/5'], ['On leave', 'accent', 'bg-accent w-1/4']] as [$label, $tone, $bar])
                                <div class="rounded-xl border border-edge bg-page p-3 shadow-float">
                                    <dt class="text-xs font-medium text-fg-muted">{{ $label }}</dt>
                                    <dd class="mt-2"><x-badge :tone="$tone">{{ $label }}</x-badge></dd>
                                    <dd class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-surface-muted" aria-hidden="true">
                                        <span class="block h-full rounded-full {{ $bar }}"></span>
                                    </dd>
                                </div>
                            @endforeach
                        </dl>

                        <ul class="mt-4 divide-y divide-edge rounded-xl border border-edge shadow-float">
                            @foreach ([
                                ['Head office', 'QR session · location verified', 'qr', 'bg-success'],
                                ['Field team', 'GPS check-in · geofence', 'map-pin', 'bg-success'],
                                ['Remote staff', 'Scheduled shift', 'calendar', 'bg-info'],
                            ] as [$place, $meta, $icon, $dot])
                                <li class="flex items-center gap-3 px-3.5 py-3">
                                    <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-primary-soft text-primary"><x-icon :name="$icon" class="size-4.5" /></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-fg">{{ $place }}</p>
                                        <p class="truncate text-xs text-fg-subtle">{{ $meta }}</p>
                                    </div>
                                    <span class="size-2 rounded-full {{ $dot }}" aria-hidden="true"></span>
                                </li>
                            @endforeach
                        </ul>

                        <p class="mt-4 text-xs text-fg-subtle">Layout preview only. No live data is shown on this page.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Audiences --}}
    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8 lg:py-24">
        <x-public.section-heading
            eyebrow="Who it serves"
            title="Built for organisations that need accountability without specialised hardware"
            lead="A single platform configured per organisation, whether you manage a campus, an office network, or teams in the field."
        />
        <ul class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ([
                ['academic-cap', 'Educational institutions', 'Lecturers, staff, and students across faculties and campuses.'],
                ['building', 'Businesses', 'Offices, branches, shift workers, and remote employees.'],
                ['landmark', 'Public institutions', 'Agencies with head offices, regional offices, and field personnel.'],
                ['heart-hand', 'NGOs', 'Programme teams split between offices and project sites.'],
                ['map-pin', 'Field-based teams', 'Inspectors, surveyors, and mobile staff who check in on site.'],
            ] as [$icon, $title, $text])
                <li class="card-hover group rounded-2xl border border-edge bg-surface p-6">
                    <span class="grid size-11 place-items-center rounded-xl bg-primary-soft text-primary transition-colors group-hover:bg-primary group-hover:text-primary-fg"><x-icon :name="$icon" class="size-5.5" /></span>
                    <h3 class="mt-5 text-lg font-semibold text-fg">{{ $title }}</h3>
                    <p class="mt-2 text-[0.9375rem] leading-relaxed text-fg-muted">{{ $text }}</p>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- Capabilities --}}
    <section class="relative isolate overflow-hidden border-y border-edge bg-surface">
        <div class="bg-mesh absolute inset-0 -z-10 opacity-70" aria-hidden="true"></div>
        <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8 lg:py-24">
            <x-public.section-heading
                eyebrow="Core capabilities"
                title="Everything an administrator needs to see attendance clearly"
                lead="Each capability is built on the same organisation record, so attendance, schedules, leave, and reports always agree with each other."
            />
            <ul class="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    ['qr', 'QR attendance', 'Administrators open a time-limited attendance session; members scan a rotating QR code with their phone browser. Static QR codes are never used.'],
                    ['map-pin', 'Location-aware check-in', 'Field officers start attendance with explicit GPS permission; the server verifies the position against an approved geofence.'],
                    ['calendar', 'Schedules', 'Define shifts by department or location and assign members, so lateness and absence are measured against the right expectation.'],
                    ['document-check', 'Leave requests', 'Members submit leave with dates and reason; authorised approvers decide, with the decision recorded.'],
                    ['bell', 'Notifications', 'Attendance confirmations, shift reminders, and leave outcomes delivered by e-mail, with SMS available once a provider is configured.'],
                    ['chart', 'Reporting', 'Daily and monthly attendance, late and absentee views, filtered by location, department, and member.'],
                ] as [$icon, $title, $text])
                    <li class="card-hover relative rounded-2xl border border-edge bg-surface/90 p-6 backdrop-blur-sm">
                        <div class="flex items-center gap-4">
                            <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-brand-teal to-brand-mint text-white shadow-sm"><x-icon :name="$icon" class="size-6" /></span>
                            <h3 class="text-lg font-semibold text-fg">{{ $title }}</h3>
                        </div>
                        <p class="mt-4 text-[0.9375rem] leading-relaxed text-fg-muted">{{ $text }}</p>
                    </li>
                @endforeach
            </ul>
            <p class="mt-10 flex items-start gap-2.5 text-sm text-fg-subtle">
                <x-icon name="layers" class="mt-0.5 size-4 shrink-0 text-primary" />
                <span>Attendance, scheduling, leave, notifications, and reporting modules are delivered in stages. Your administrator dashboard shows which modules are enabled for your organisation.</span>
            </p>
        </div>
    </section>

    {{-- Benefits + Trust --}}
    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8 lg:py-24">
        <div class="grid items-start gap-12 lg:grid-cols-12">
            <div class="lg:col-span-6">
                <x-public.section-heading eyebrow="Why ORBIT" title="Why organisations choose ORBIT" />
                <ul class="mt-10 space-y-4">
                    @foreach ([
                        ['clock', 'Less manual work', 'No registers to transcribe or spreadsheets to reconcile at month end.'],
                        ['id-card', 'Clearer accountability', 'Every check-in records who, when, where, and how it was verified.'],
                        ['globe', 'Centralised visibility', 'Head office, branches, remote staff, and field teams in one dashboard.'],
                        ['device-phone', 'Lower hardware dependence', 'Works in the browser on the phones your team already carry; no biometric terminals required.'],
                    ] as [$icon, $title, $text])
                        <li class="flex gap-5 rounded-2xl border border-edge bg-surface p-5 card-hover">
                            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-accent-soft text-accent"><x-icon :name="$icon" class="size-5.5" /></span>
                            <div>
                                <h3 class="text-lg font-semibold text-fg">{{ $title }}</h3>
                                <p class="mt-1 text-[0.9375rem] leading-relaxed text-fg-muted">{{ $text }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="lg:col-span-6">
                <div class="rounded-3xl border border-edge bg-surface p-7 shadow-float sm:p-9">
                    <p class="eyebrow">Onboarding</p>
                    <h2 class="mt-3 text-2xl font-semibold tracking-tight text-fg sm:text-3xl">How onboarding works</h2>
                    <ol class="relative mt-8 space-y-7 before:absolute before:top-3 before:bottom-3 before:left-[1.1rem] before:w-px before:bg-edge">
                        @foreach ([
                            ['Register your organisation', 'The responsible contact creates the organisation and becomes its first administrator.'],
                            ['Verify and configure', 'Confirm your e-mail, then set your organisation profile and time zone.'],
                            ['Invite your team', 'Administrators invite members by e-mail with the right role. There is no open sign-up.'],
                            ['Start recording attendance', 'Enable locations and attendance sessions as modules become available to your organisation.'],
                        ] as $index => [$title, $text])
                            <li class="relative flex gap-5">
                                <span aria-hidden="true" class="relative z-10 grid size-9 shrink-0 place-items-center rounded-full bg-primary text-sm font-semibold text-primary-fg ring-4 ring-surface">{{ $index + 1 }}</span>
                                <div class="pt-1">
                                    <h3 class="text-lg font-semibold text-fg">{{ $title }}</h3>
                                    <p class="mt-1 text-[0.9375rem] leading-relaxed text-fg-muted">{{ $text }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                    <div class="mt-9 flex flex-wrap gap-3">
                        <x-button :href="route('register')">Register your organisation</x-button>
                        <x-button :href="route('about')" variant="secondary">See how it works</x-button>
                    </div>
                </div>

                <ul class="mt-6 grid gap-3 sm:grid-cols-3" aria-label="Security principles">
                    @foreach ([
                        ['shield-check', 'Separate by design', 'Each organisation\'s records are isolated.'],
                        ['eye-off', 'No background tracking', 'Location is captured only on explicit check-in.'],
                        ['document-check', 'Audited actions', 'Administrative changes are recorded.'],
                    ] as [$icon, $title, $text])
                        <li class="card-hover rounded-2xl border border-edge bg-surface-muted/50 p-4">
                            <x-icon :name="$icon" class="size-5 text-primary" />
                            <p class="mt-3 text-sm font-semibold text-fg">{{ $title }}</p>
                            <p class="mt-1 text-sm text-fg-muted">{{ $text }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    {{-- Closing call to action on the brand panel --}}
    <section class="mx-auto max-w-7xl px-4 pb-20 sm:px-6 lg:px-8 lg:pb-24">
        <div class="bg-brand-panel relative isolate overflow-hidden rounded-3xl px-6 py-14 text-white shadow-lift sm:px-12 lg:px-16">
            <div aria-hidden="true" class="absolute -right-24 -bottom-32 -z-10 size-[28rem] rounded-full border border-white/10"></div>
            <div aria-hidden="true" class="absolute -right-10 -bottom-20 -z-10 size-[20rem] rounded-full border border-white/10"></div>
            <div class="grid items-center gap-10 lg:grid-cols-12">
                <div class="lg:col-span-7">
                    <img src="{{ asset('images/brand/orbit-lockup-dark.png') }}" alt="ORBIT — Track · Manage · Empower. Real Team. Real Locations. Real Time." width="736" height="278" class="h-24 w-auto sm:h-28" loading="lazy" decoding="async">
                    <h2 class="mt-8 text-3xl font-semibold tracking-tight text-balance sm:text-4xl">Ready to bring your organisation's attendance into one place?</h2>
                    <p class="mt-4 max-w-xl text-lg text-white/80">Register as the responsible contact, verify your e-mail, and invite your team. Setup takes minutes; no hardware to install.</p>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row lg:col-span-5 lg:flex-col lg:items-stretch">
                    <x-button :href="route('register')" variant="inverse" size="lg">Register your organisation <x-icon name="arrow-right" class="size-4" /></x-button>
                    <x-button :href="route('contact')" variant="outline-inverse" size="lg">Request a demo</x-button>
                </div>
            </div>
        </div>
    </section>
</x-layouts.public>
