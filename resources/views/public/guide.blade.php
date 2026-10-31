<x-layouts.public title="User guide" description="Step-by-step guidance for organisation administrators and members using ORBIT.">
    <x-public.page-hero
        eyebrow="Help"
        title="User guide"
        lead="Practical steps for the features available today. Sections for later modules are marked as planned."
    />

    @php
        $sections = [
            'register' => ['id-card', 'Register your organisation'],
            'sign-in' => ['lock', 'Sign in and reset your password'],
            'verify' => ['mail', 'Verify e-mail and phone'],
            'settings' => ['building', 'Organisation settings'],
            'workspace' => ['globe', 'Workspace URL and branding'],
            'invite' => ['users', 'Invite members'],
            'theme' => ['sparkles', 'Change the theme'],
            'planned' => ['layers', 'Planned modules'],
        ];
    @endphp

    <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
        <div class="grid gap-12 lg:grid-cols-12">
            <aside class="lg:col-span-3">
                <nav aria-label="Guide sections" class="rounded-2xl border border-edge bg-surface p-5 shadow-float lg:sticky lg:top-28">
                    <p class="text-xs font-semibold tracking-[0.14em] text-fg uppercase">On this page</p>
                    <ul class="mt-4 space-y-1 text-[0.9375rem]">
                        @foreach ($sections as $id => [$icon, $label])
                            <li>
                                <a href="#{{ $id }}" class="flex items-center gap-2.5 rounded-lg px-3 py-1.5 text-fg-muted transition-colors hover:bg-surface-muted hover:text-fg">
                                    <x-icon :name="$icon" class="size-4 text-primary" /> {{ $label }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    <div class="mt-5 border-t border-edge pt-5">
                        <p class="text-sm text-fg-muted">Can't find what you need?</p>
                        <a href="{{ route('contact') }}" class="mt-2 inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline">Contact support <x-icon name="arrow-right" class="size-4" /></a>
                    </div>
                </nav>
            </aside>

            <div class="prose-orbit card p-6 sm:p-8 lg:col-span-9">
                <h2 id="register">Register your organisation</h2>
                <ol>
                    <li>Open <a href="{{ route('register') }}">Register your organisation</a>.</li>
                    <li>Enter the organisation name, type, and time zone.</li>
                    <li>Enter your own details as the responsible contact: name, work e-mail, phone, and a password of at least 10 characters with letters and numbers.</li>
                    <li>Accept the Terms and Privacy Policy and submit. You become the organisation's first administrator.</li>
                </ol>
                <p>Only the responsible contact registers. Employees, staff, students, and field officers do not sign up themselves — they are invited (see below).</p>

                <h2 id="sign-in">Sign in and reset your password</h2>
                <p>Organisation members sign in on their organisation's workspace URL (for example <code>epa.orbit.test</code> in local development), not on the public ORBIT site. Platform operators sign in on the central ORBIT host. Use your e-mail and password. If you forget your password, choose <em>Forgot your password?</em> on that same workspace; if an account exists for the address you enter, a time-limited reset link is e-mailed to it. Repeated failed attempts temporarily lock sign-in for that account. Sign-in cookies stay on that hostname and are not shared with other workspaces.</p>

                <h2 id="verify">Verify your e-mail and phone</h2>
                <p>After you sign in, the workspace stays closed until both checks on the verification page are complete. Confirm the link sent to your e-mail. Then confirm your phone with a one-time code. Your organisation becomes active once the responsible contact confirms their e-mail. Text messaging is not connected yet, so the phone code is written to the application log for your technical team to pass on.</p>

                <h2 id="settings">Organisation settings</h2>
                <p>Administrators can update the organisation name, type, time zone, and contact details from <em>Organisation settings → Profile</em>. The time zone determines how attendance times are displayed.</p>

                <h2 id="workspace">Workspace URL and branding</h2>
                <p>Each organisation has its own workspace hostname. The public ORBIT site is separate from those workspaces. After registration, ORBIT creates a primary hostname from an available subdomain. Administrators can add shorter aliases (for example <code>mcc.orbit.test</code> alongside <code>mobilecontent.orbit.test</code>). Both names open the same organisation. Custom domains belonging to the organisation can be added later; they stay inactive until a DNS TXT record proves the organisation controls the name.</p>
                <p>Under <em>Branding</em>, administrators set the display name, logo, favicon, brand colours, login heading, and welcome message. Save a draft, preview the login page, then publish. Until you publish, visitors see ORBIT's default presentation. Colours are six-digit hex values; logos must be PNG, JPG, WEBP, or ICO (not SVG). Arbitrary HTML and JavaScript are not accepted. ORBIT remains attributed as the platform.</p>
                <p>The <em>Student</em> role is shown only to educational institutions. Agencies such as an EPA workspace invite organisation administrators, managers, and members instead.</p>

                <h2 id="invite">Invite members</h2>
                <ol>
                    <li>Open <em>Users &amp; access → User accounts</em> and choose <em>Invite user</em>.</li>
                    <li>Enter the person's e-mail address and choose a role.</li>
                    <li>They receive an e-mail with a single-use link that expires after {{ config('orbit.invitation_ttl_days') }} days.</li>
                    <li>When they open the link they set their name and password and join your organisation with the role you chose.</li>
                </ol>
                <p>Pending invitations can be revoked at any time. A revoked or expired link cannot be used.</p>

                <h2 id="theme">Change the theme</h2>
                <p>Use the theme button in the header to switch between the default light theme and the deep teal theme. Your choice is remembered on this device.</p>

                <h2 id="planned">Planned modules</h2>
                <p>The staff register, user accounts, roles, locations, and departments are available now. Attendance sessions and QR check-in, field monitoring, schedules, leave, notifications, and analytics are delivered in later stages. This guide is updated as each module becomes available.</p>
            </div>
        </div>
    </div>
</x-layouts.public>
