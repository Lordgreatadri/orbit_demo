<x-layouts.public description="ORBIT is a centralised attendance, workforce scheduling, and operational visibility platform for schools, businesses, public institutions, NGOs, and field-based teams.">
    {{-- Hero --}}
    <section class="relative isolate overflow-hidden border-b border-edge bg-surface">
        <div class="bg-mesh absolute inset-0 -z-10 opacity-80" aria-hidden="true"></div>
        <div class="bg-grid absolute inset-x-0 top-0 -z-10 h-[34rem] opacity-60" aria-hidden="true"></div>

        <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 pt-14 pb-16 sm:px-6 lg:grid-cols-12 lg:gap-10 lg:px-8 lg:pt-20 lg:pb-24">
            <div class="lg:col-span-6">
                <p class="eyebrow">{{ config('orbit.product_tagline') }}</p>
                <h1 class="mt-5 text-[2.5rem] leading-[1.06] font-semibold tracking-tight text-balance text-fg sm:text-[3.25rem] lg:text-[3.625rem]">
                    One clear view of attendance, schedules, and <span class="text-primary">where work happens.</span>
                </h1>
                <p class="mt-6 max-w-xl text-lg leading-relaxed text-pretty text-fg-muted">ORBIT replaces paper registers and scattered spreadsheets with QR and location-aware check-in, shift schedules, leave workflows, and dashboards that tell administrators what needs a decision today.</p>

                <div class="mt-9 flex flex-wrap items-center gap-3">
                    <x-button :href="route('register')" size="lg">Register your organisation <x-icon name="arrow-right" class="size-4" /></x-button>
                    <x-button :href="route('about')" variant="secondary" size="lg">Explore how it works</x-button>
                </div>

                <p class="mt-6 flex max-w-xl items-start gap-2.5 text-[0.9375rem] text-fg-subtle">
                    <x-icon name="lock" class="mt-0.5 size-4 shrink-0 text-primary" />
                    <span>Organisation registration is for the responsible contact. Staff, students, and field officers are invited by their organisation's administrator.</span>
                </p>
            </div>

            {{-- Illustrative workspace preview. Sample layout only, no live data. --}}
            <div class="relative lg:col-span-6" aria-label="Illustrative example of an ORBIT organisation dashboard">
                <div aria-hidden="true" class="absolute -inset-4 -z-10 rounded-[1.75rem] bg-gradient-to-br from-primary/15 via-transparent to-accent/15 blur-2xl"></div>
                <div class="overflow-hidden rounded-2xl border border-edge bg-page shadow-lift">
                    <div class="flex items-center justify-between gap-3 border-b border-edge bg-surface px-4 py-2.5">
                        <div class="flex items-center gap-1.5" aria-hidden="true">
                            <span class="size-2.5 rounded-full bg-edge-strong"></span>
                            <span class="size-2.5 rounded-full bg-edge-strong"></span>
                            <span class="size-2.5 rounded-full bg-edge-strong"></span>
                        </div>
                        <p class="truncate text-xs font-medium text-fg-subtle">Organisation overview</p>
                        <x-badge tone="warning">Illustrative</x-badge>
                    </div>

                    <div class="grid grid-cols-[3.25rem_1fr]">
                        <div class="flex flex-col items-center gap-3 border-r border-edge bg-surface py-4" aria-hidden="true">
                            <img src="{{ asset('images/brand/orbit-icon.png') }}" alt="" width="28" height="28" class="size-7 rounded-md">
                            @foreach (['home', 'users', 'check-circle', 'calendar', 'document-check', 'chart'] as $glyph)
                                <span @class(['grid size-8 place-items-center rounded-md', 'bg-primary-soft text-primary' => $loop->first, 'text-fg-subtle' => ! $loop->first])><x-icon :name="$glyph" class="size-4" /></span>
                            @endforeach
                        </div>

                        <div class="min-w-0 space-y-3 p-3 sm:p-4">
                            <div class="briefing p-3">
                                <p class="flex items-center gap-1.5 text-[0.6875rem] font-semibold text-primary"><x-icon name="sparkles" class="size-3.5" /> Operational briefing</p>
                                <p class="mt-1 text-[0.8125rem] leading-snug font-semibold text-fg">Sample: 3 people have no covering record yet, and 2 leave requests are waiting.</p>
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    <span class="inline-flex items-center gap-1 rounded-md border border-edge bg-surface px-2 py-1 text-[0.6875rem] font-medium text-fg"><span class="size-1.5 rounded-full bg-danger"></span> Open attendance register</span>
                                    <span class="inline-flex items-center gap-1 rounded-md border border-edge bg-surface px-2 py-1 text-[0.6875rem] font-medium text-fg"><span class="size-1.5 rounded-full bg-warning"></span> Review leave</span>
                                </div>
                            </div>

                            <dl class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                @foreach ([['Present', '24', 'text-fg', 'bg-success w-4/5'], ['Late', '3', 'text-fg', 'bg-warning w-1/6'], ['On leave', '2', 'text-fg', 'bg-accent w-1/12'], ['Uncovered', '3', 'text-danger', 'bg-danger w-1/6']] as [$label, $figure, $ink, $bar])
                                    <div class="rounded-lg border border-edge bg-surface p-2.5">
                                        <dt class="text-[0.6875rem] text-fg-muted">{{ $label }}</dt>
                                        <dd class="mt-0.5 text-lg leading-none font-semibold tabular-nums {{ $ink }}">{{ $figure }}</dd>
                                        <dd class="mt-2 h-1 overflow-hidden rounded-full bg-surface-muted" aria-hidden="true"><span class="block h-full rounded-full {{ $bar }}"></span></dd>
                                    </div>
                                @endforeach
                            </dl>

                            <div class="grid gap-2 sm:grid-cols-5">
                                <div class="rounded-lg border border-edge bg-surface p-3 sm:col-span-3">
                                    <p class="text-[0.6875rem] font-semibold text-fg">Last 14 days</p>
                                    <div class="plot-grid mt-2 flex h-16 items-end gap-1 border-b border-edge" aria-hidden="true">
                                        @foreach ([60, 72, 68, 80, 76, 30, 20, 70, 84, 78, 82, 74, 88, 64] as $h)
                                            <span class="flex flex-1 flex-col justify-end" style="height: {{ $h }}%">
                                                <span class="block rounded-t-[2px] bg-warning" style="height: 14%"></span>
                                                <span class="block flex-1 bg-primary"></span>
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 rounded-lg border border-edge bg-surface p-3 sm:col-span-2">
                                    <svg viewBox="0 0 42 42" class="size-14 shrink-0" aria-hidden="true">
                                        <circle cx="21" cy="21" r="15.915" fill="none" stroke="var(--surface-muted)" stroke-width="5" />
                                        <circle cx="21" cy="21" r="15.915" fill="none" stroke="var(--primary)" stroke-width="5" stroke-dasharray="86 14" stroke-dashoffset="25" stroke-linecap="round" />
                                    </svg>
                                    <div>
                                        <p class="text-[0.6875rem] text-fg-muted">Schedule coverage</p>
                                        <p class="text-base font-semibold tabular-nums text-fg">86%</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="border-t border-edge bg-surface px-4 py-2 text-[0.6875rem] text-fg-subtle">Layout preview with sample values. No live data is shown on this page.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Audience band --}}
    <section aria-label="Who ORBIT is designed for" class="border-b border-edge bg-page">
        <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-6 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
            <p class="text-sm font-medium text-fg-muted">Designed for organisations that run on people being in the right place</p>
            <ul class="flex flex-wrap gap-2">
                @foreach ([['landmark', 'Ministries & agencies'], ['academic-cap', 'Universities & schools'], ['building', 'Businesses'], ['heart-hand', 'NGOs'], ['map-pin', 'Field teams']] as [$icon, $label])
                    <li class="inline-flex items-center gap-2 rounded-full border border-edge bg-surface px-3 py-1.5 text-sm font-medium text-fg"><x-icon :name="$icon" class="size-4 text-primary" /> {{ $label }}</li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- What ORBIT solves --}}
    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8 lg:py-24">
        <x-public.section-heading
            eyebrow="What ORBIT solves"
            title="From scattered registers to decisions you can act on"
            lead="Attendance, schedules, and leave usually live in different places. ORBIT keeps them on one organisation record, so the dashboard can say what needs attention."
        />
        <div class="mt-12 grid gap-5 lg:grid-cols-3">
            @foreach ([
                ['Paper registers and late spreadsheets', 'Every check-in is stored when it happens, with who, when, where, and how it was verified.', 'clock'],
                ['Schedules nobody compares with attendance', 'Published shifts are measured against attendance, so coverage gaps show up before the day ends.', 'calendar'],
                ['Leave decided by message thread', 'Requests, balances, and decisions sit on the same record, with an audit trail for every decision.', 'document-check'],
            ] as [$problem, $outcome, $icon])
                <article class="card flex flex-col p-6">
                    <span class="icon-well tone-primary size-10" aria-hidden="true"><x-icon :name="$icon" class="size-5" /></span>
                    <p class="mt-5 text-xs font-medium text-fg-subtle">Instead of</p>
                    <p class="mt-0.5 text-sm font-medium text-fg-muted">{{ $problem }}</p>
                    <p class="mt-4 border-t border-edge pt-4 text-lg leading-snug font-semibold text-fg">{{ $outcome }}</p>
                </article>
            @endforeach
        </div>
    </section>

    {{-- Capabilities --}}
    <section class="border-y border-edge bg-surface">
        <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8 lg:py-24">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <x-public.section-heading
                    eyebrow="Operational capabilities"
                    title="Everything an administrator needs to run the day"
                    lead="Each module writes to the same organisation record, so attendance, schedules, leave, and reports agree with each other."
                />
                <x-button :href="route('about')" variant="secondary" class="shrink-0">See how it works <x-icon name="arrow-right" class="size-4" /></x-button>
            </div>
            <ul class="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    ['qr', 'Attendance', 'Time-limited attendance sessions with a rotating QR code, and explicit location check-in for field officers. Static QR codes are never used.', 'Who is present, late, or uncovered right now'],
                    ['calendar', 'Scheduling', 'Shifts by department or location, assigned to people, so lateness and absence are measured against the right expectation.', 'Which shifts still need people'],
                    ['document-check', 'Leave', 'Members request leave with dates and reason. Authorised approvers decide, and balances update on the same record.', 'What to approve before it starts'],
                    ['bell', 'Notifications', 'Attendance confirmations, shift reminders, and leave outcomes in the inbox and by e-mail, with SMS once a provider is configured.', 'Whether people have been told'],
                    ['chart', 'Analytics', 'Trends, status share, schedule adherence, and leave use from stored records, filtered by department and location, exportable as CSV.', 'Where attendance is slipping and why'],
                    ['shield-check', 'Access and audit', 'Roles decide what each person sees. Department heads see their departments. Administrative actions are recorded.', 'Who changed what, and when'],
                ] as [$icon, $title, $text, $decision])
                    <li class="card card-hover flex flex-col p-6">
                        <div class="flex items-center gap-3">
                            <span class="icon-well tone-primary size-10" aria-hidden="true"><x-icon :name="$icon" class="size-5" /></span>
                            <h3 class="text-lg font-semibold text-fg">{{ $title }}</h3>
                        </div>
                        <p class="mt-4 flex-1 text-[0.9375rem] leading-relaxed text-fg-muted">{{ $text }}</p>
                        <p class="mt-5 flex items-start gap-2 border-t border-edge pt-4 text-sm text-fg"><x-icon name="arrow-right" class="mt-0.5 size-4 shrink-0 text-primary" /><span><span class="font-semibold">Answers:</span> {{ $decision }}</span></p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- How it works --}}
    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8 lg:py-24">
        <x-public.section-heading eyebrow="How it works" title="Live in four steps, without new hardware" align="center" class="mx-auto" />
        <ol class="relative mt-14 grid gap-8 md:grid-cols-4 md:gap-6">
            <span aria-hidden="true" class="absolute top-6 right-[12.5%] left-[12.5%] hidden h-px bg-gradient-to-r from-primary/20 via-primary/60 to-primary/20 md:block"></span>
            @foreach ([
                ['building', 'Register your organisation', 'The responsible contact creates the organisation and becomes its first administrator.'],
                ['settings', 'Verify and configure', 'Confirm your e-mail, set the time zone, then add departments and locations.'],
                ['user-plus', 'Invite your team', 'Administrators invite members by e-mail with the right role. There is no open sign-up.'],
                ['check-circle', 'Record and decide', 'Open attendance sessions, publish schedules, and work from the dashboard each day.'],
            ] as $index => [$icon, $title, $text])
                <li class="relative flex gap-4 md:flex-col md:items-center md:text-center">
                    <span class="relative z-10 grid size-12 shrink-0 place-items-center rounded-full border border-edge bg-surface text-primary shadow-card ring-8 ring-page" aria-hidden="true">
                        <x-icon :name="$icon" class="size-5" />
                    </span>
                    <div>
                        <p class="text-xs font-semibold text-primary md:mt-4">Step {{ $index + 1 }}</p>
                        <h3 class="mt-1 text-lg font-semibold text-fg">{{ $title }}</h3>
                        <p class="mt-1.5 text-[0.9375rem] leading-relaxed text-fg-muted">{{ $text }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- Institutional safeguards --}}
    <section class="border-y border-edge bg-surface">
        <div class="mx-auto grid max-w-7xl gap-12 px-4 py-20 sm:px-6 lg:grid-cols-12 lg:px-8 lg:py-24">
            <div class="lg:col-span-4">
                <x-public.section-heading eyebrow="Built for accountability" title="Safeguards an institution can rely on" lead="These describe how ORBIT works today. ORBIT does not claim certifications, endorsements, or customers it does not have." />
            </div>
            <ul class="grid gap-4 sm:grid-cols-2 lg:col-span-8">
                @foreach ([
                    ['shield-check', 'Separate by design', 'Each organisation has its own workspace. Every record carries its organisation and is scoped on every read and write.'],
                    ['eye-off', 'No background tracking', 'Location is captured only when a person chooses to check in, and only to verify that check-in.'],
                    ['key', 'Role-based access', 'Administrators, department heads, and members see what their role allows. Hiding a menu is never the only control.'],
                    ['document-check', 'Audited actions', 'Administrative changes and sign-ins are recorded with who, what, and when.'],
                    ['device-phone', 'Works in the browser', 'Phones your team already carry. No biometric terminals or special readers.'],
                    ['chart', 'Honest figures', 'Dashboards count stored records. A zero is a stored zero, and a missing record is never shown as present.'],
                ] as [$icon, $title, $text])
                    <li class="flex gap-4 rounded-xl border border-edge bg-page/60 p-5">
                        <span class="icon-well tone-primary" aria-hidden="true"><x-icon :name="$icon" class="size-4" /></span>
                        <div>
                            <h3 class="font-semibold text-fg">{{ $title }}</h3>
                            <p class="mt-1 text-sm leading-relaxed text-fg-muted">{{ $text }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Closing call to action on the brand panel --}}
    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8 lg:py-24">
        <div class="bg-brand-panel relative isolate overflow-hidden rounded-2xl px-6 py-14 text-white sm:px-12 lg:px-16">
            <div aria-hidden="true" class="absolute -right-24 -bottom-32 -z-10 size-[28rem] rounded-full border border-white/10"></div>
            <div aria-hidden="true" class="absolute -right-10 -bottom-20 -z-10 size-[20rem] rounded-full border border-white/10"></div>
            <div class="grid items-center gap-10 lg:grid-cols-12">
                <div class="lg:col-span-7">
                    <img src="{{ asset('images/brand/orbit-lockup-dark.png') }}" alt="ORBIT — Track · Manage · Empower. Real Team. Real Locations. Real Time." width="736" height="278" class="h-20 w-auto sm:h-24" loading="lazy" decoding="async">
                    <h2 class="mt-8 text-3xl font-semibold tracking-tight text-balance sm:text-4xl">Ready to bring your organisation's attendance into one place?</h2>
                    <p class="mt-4 max-w-xl text-lg text-white/80">Register as the responsible contact, verify your e-mail, and invite your team. Setup takes minutes, with no hardware to install.</p>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row lg:col-span-5 lg:flex-col lg:items-stretch">
                    <x-button :href="route('register')" variant="inverse" size="lg">Register your organisation <x-icon name="arrow-right" class="size-4" /></x-button>
                    <x-button :href="route('contact')" variant="outline-inverse" size="lg">Request a demo</x-button>
                </div>
            </div>
        </div>
    </section>
</x-layouts.public>
