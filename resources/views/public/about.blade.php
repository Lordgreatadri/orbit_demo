<x-layouts.public title="How it works" description="How ORBIT records attendance, verifies location, and keeps organisations' data separate.">
    <x-public.page-hero
        eyebrow="About ORBIT"
        title="How ORBIT works"
        lead="ORBIT is one multi-tenant platform. Each organisation has its own administrators, members, locations, and records, kept separate from every other organisation."
    >
        <x-button :href="route('register')">Register your organisation <x-icon name="arrow-right" class="size-4" /></x-button>
        <x-button :href="route('guide')" variant="secondary">Read the user guide</x-button>
    </x-public.page-hero>

    @php
        $sections = [
            'roles' => '1. Organisations and roles',
            'qr' => '2. Office attendance with QR sessions',
            'field' => '3. Field attendance with location verification',
            'schedules' => '4. Schedules, leave, and notifications',
            'reporting' => '5. Reporting',
            'security' => '6. Data separation and security',
            'stages' => 'Delivery stages',
        ];
    @endphp

    <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
        <div class="grid gap-12 lg:grid-cols-12">
            <aside class="lg:col-span-3">
                <nav aria-label="On this page" class="rounded-2xl border border-edge bg-surface p-5 lg:sticky lg:top-28">
                    <p class="text-xs font-semibold tracking-[0.14em] text-fg uppercase">On this page</p>
                    <ul class="mt-4 space-y-1 text-[0.9375rem]">
                        @foreach ($sections as $id => $label)
                            <li><a href="#{{ $id }}" class="block rounded-lg px-3 py-1.5 text-fg-muted transition-colors hover:bg-surface-muted hover:text-fg">{{ $label }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            </aside>

            <div class="lg:col-span-9">
                <div class="prose-orbit">
                    <h2 id="roles">1. Organisations and roles</h2>
                    <p>An organisation is registered once by its responsible contact, who becomes the initial organisation administrator. Administrators invite managers and members. Platform operators are a separate, audited role boundary and are never granted by an organisation role.</p>
                </div>
                <ul class="mt-6 grid gap-4 sm:grid-cols-3">
                    @foreach ([
                        ['shield-check', 'Organisation administrator', 'Organisation settings, members, invitations, and all operational modules.'],
                        ['document-check', 'Manager / approver', 'Permissions for defined workflows such as leave approval.'],
                        ['users', 'Member', 'An employee, staff member, student, lecturer, or field officer, depending on your institution.'],
                    ] as [$icon, $title, $text])
                        <li class="rounded-2xl border border-edge bg-surface p-5">
                            <span class="grid size-10 place-items-center rounded-lg bg-primary-soft text-primary"><x-icon :name="$icon" class="size-5" /></span>
                            <h3 class="mt-4 font-semibold text-fg">{{ $title }}</h3>
                            <p class="mt-1.5 text-[0.9375rem] leading-relaxed text-fg-muted">{{ $text }}</p>
                        </li>
                    @endforeach
                </ul>

                <div class="prose-orbit">
                    <h2 id="qr">2. Office attendance with time-limited QR sessions</h2>
                </div>
                <ol class="mt-6 grid gap-4 sm:grid-cols-2">
                    @foreach ([
                        'An authorised administrator opens an attendance session for a location and time window.',
                        'ORBIT generates a securely signed, short-lived code that rotates automatically. Static or printed QR codes are not used.',
                        'The member signs in on their phone browser and scans the code.',
                        'The server verifies the session, expiry, organisation, location, member eligibility, and duplicate rules before recording attendance.',
                    ] as $i => $step)
                        <li class="flex gap-4 rounded-2xl border border-edge bg-surface p-5">
                            <span aria-hidden="true" class="grid size-8 shrink-0 place-items-center rounded-full bg-primary text-sm font-semibold text-primary-fg">{{ $i + 1 }}</span>
                            <p class="text-[0.9375rem] leading-relaxed text-fg-muted">{{ $step }}</p>
                        </li>
                    @endforeach
                </ol>

                <div class="prose-orbit">
                    <h2 id="field">3. Field attendance with explicit location verification</h2>
                </div>
                <ol class="mt-6 grid gap-4 sm:grid-cols-2">
                    @foreach ([
                        'A field member explicitly starts a field attendance action.',
                        'The browser asks for location permission. Nothing is collected in the background.',
                        'Coordinates and reported accuracy are sent once, and the server checks them against the approved geofence.',
                        'The result — accepted or rejected with a clear reason — is recorded.',
                    ] as $i => $step)
                        <li class="flex gap-4 rounded-2xl border border-edge bg-surface p-5">
                            <span aria-hidden="true" class="grid size-8 shrink-0 place-items-center rounded-full bg-accent text-sm font-semibold text-accent-fg">{{ $i + 1 }}</span>
                            <p class="text-[0.9375rem] leading-relaxed text-fg-muted">{{ $step }}</p>
                        </li>
                    @endforeach
                </ol>
                <p class="mt-5 flex items-start gap-2.5 rounded-2xl border border-info/30 bg-info-soft p-4 text-[0.9375rem] text-fg-muted">
                    <x-icon name="eye-off" class="mt-0.5 size-5 shrink-0 text-info" />
                    <span>Location verification reduces risk; it is not presented as absolute proof. ORBIT does not perform continuous tracking.</span>
                </p>

                <div class="prose-orbit">
                    <h2 id="schedules">4. Schedules, leave, and notifications</h2>
                    <p>Shifts are defined per department or location and assigned to members. Leave requests carry a type, date range, and reason, and are approved or rejected by authorised approvers with the decision recorded. Notifications confirm attendance, remind members of shifts, and communicate leave outcomes.</p>

                    <h2 id="reporting">5. Reporting</h2>
                    <p>Administrators filter attendance by date, location, department, member, status, and work type, and review daily and monthly summaries. Exports are planned as part of the reporting module.</p>

                    <h2 id="security">6. Data separation and security</h2>
                </div>
                <ul class="mt-6 grid gap-4 sm:grid-cols-2">
                    @foreach ([
                        ['layers', 'Every organisation-owned record is linked to that organisation and is only accessible to its authorised users.'],
                        ['lock', 'Passwords are hashed; sign-in, registration, password reset, and invitations are rate-limited.'],
                        ['id-card', 'Public links use non-sequential identifiers.'],
                        ['document-check', 'Administrative actions are recorded in an audit trail.'],
                    ] as [$icon, $text])
                        <li class="flex gap-4 rounded-2xl border border-edge bg-surface p-5">
                            <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-success-soft text-success"><x-icon :name="$icon" class="size-5" /></span>
                            <p class="text-[0.9375rem] leading-relaxed text-fg-muted">{{ $text }}</p>
                        </li>
                    @endforeach
                </ul>

                <div class="prose-orbit">
                    <h2 id="stages">Delivery stages</h2>
                    <p>ORBIT is delivered in stages: public pages and organisation onboarding first, then locations, attendance, field monitoring, schedules, leave, notifications, and analytics. Your dashboard shows which modules are currently enabled; nothing is presented as live before it is.</p>
                </div>

                <div class="mt-12 flex flex-wrap gap-3 border-t border-edge pt-10">
                    <x-button :href="route('register')">Register your organisation</x-button>
                    <x-button :href="route('guide')" variant="secondary">Read the user guide</x-button>
                </div>
            </div>
        </div>
    </div>
</x-layouts.public>
