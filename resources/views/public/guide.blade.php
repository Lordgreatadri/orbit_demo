<x-layouts.public title="User guide" description="Step-by-step guidance for organisation administrators, managers, members, and students using ORBIT.">
    <x-public.page-hero
        eyebrow="Help"
        title="User guide"
        lead="Practical steps for every part of ORBIT that is available today, from registering your organisation to reading the dashboard. What you see in your workspace depends on your role."
    />

    @php
        $groups = [
            'Getting started' => [
                'register' => ['id-card', 'Register your organisation'],
                'sign-in' => ['lock', 'Sign in and reset your password'],
                'verify' => ['mail', 'Verify e-mail and phone'],
                'roles' => ['key', 'Roles and what they see'],
            ],
            'Set up the organisation' => [
                'settings' => ['building', 'Organisation settings'],
                'workspace' => ['globe', 'Workspace URL and branding'],
                'structure' => ['map-pin', 'Departments and locations'],
                'staff' => ['users', 'The staff register'],
                'invite' => ['user-plus', 'Invite and manage accounts'],
            ],
            'Daily work' => [
                'attendance' => ['qr', 'Check in and out'],
                'sessions' => ['check-circle', 'Run QR sessions and records'],
                'schedules' => ['calendar', 'Schedules'],
                'leave' => ['document-check', 'Leave'],
                'notifications' => ['bell', 'Notifications'],
            ],
            'Insight and oversight' => [
                'dashboard' => ['home', 'Your dashboard'],
                'analytics' => ['chart', 'Analytics and export'],
                'audit' => ['shield-check', 'Audit and sign-in history'],
            ],
            'More' => [
                'education' => ['academic-cap', 'Schools and universities'],
                'account' => ['user', 'Your account and theme'],
                'scope' => ['layers', 'Not in this release'],
            ],
        ];
    @endphp

    <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
        <div class="grid gap-12 lg:grid-cols-12">
            <aside class="lg:col-span-3">
                <nav aria-label="Guide sections" class="rounded-2xl border border-edge bg-surface p-5 shadow-float lg:sticky lg:top-28 lg:max-h-[calc(100svh-8rem)] lg:overflow-y-auto">
                    <p class="text-xs font-semibold tracking-[0.14em] text-fg uppercase">On this page</p>
                    @foreach ($groups as $group => $sections)
                        <p class="mt-5 px-3 text-xs font-semibold text-fg-subtle">{{ $group }}</p>
                        <ul class="mt-1.5 space-y-0.5 text-[0.9375rem]">
                            @foreach ($sections as $id => [$icon, $label])
                                <li>
                                    <a href="#{{ $id }}" class="flex items-center gap-2.5 rounded-lg px-3 py-1.5 text-fg-muted transition-colors hover:bg-surface-muted hover:text-fg">
                                        <x-icon :name="$icon" class="size-4 shrink-0 text-primary" /> {{ $label }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endforeach
                    <div class="mt-5 border-t border-edge pt-5">
                        <p class="text-sm text-fg-muted">Can't find what you need?</p>
                        <a href="{{ route('contact') }}#faq" class="mt-2 inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline">Questions and contact <x-icon name="arrow-right" class="size-4" /></a>
                    </div>
                </nav>
            </aside>

            <div class="prose-orbit card p-6 sm:p-8 lg:col-span-9">
                {{-- Getting started --}}
                <h2 id="register">Register your organisation</h2>
                <ol>
                    <li>Open <a href="{{ route('register') }}">Register your organisation</a>.</li>
                    <li>Enter the organisation name, type, and time zone. The type matters: an <em>Educational institution</em> gets school wording and the Student role.</li>
                    <li>Enter your own details as the responsible contact: name, work e-mail, phone, and a password of at least 10 characters with letters and numbers. Use the eye button in a password field to check what you typed.</li>
                    <li>Accept the Terms and Privacy Policy and choose <em>Create organisation</em>. You become the organisation's first administrator.</li>
                </ol>
                <p>Only the responsible contact registers. Employees, staff, students, and field officers do not sign up themselves. They are invited by an administrator (see <a href="#invite">Invite and manage accounts</a>).</p>

                <h2 id="sign-in">Sign in and reset your password</h2>
                <p>Organisation members sign in on their organisation's workspace address (for example <code>epa.orbit.test</code> in local development), not on the public ORBIT site. Platform operators sign in on the central ORBIT address. Use your e-mail and password. If you forget your password, choose <em>Forgot your password?</em> on that same workspace. If an account exists for the address you enter, a time-limited reset link is e-mailed to it. Repeated failed attempts temporarily lock sign-in for that account. Sign-in cookies stay on that address and are not shared with other workspaces.</p>

                <h2 id="verify">Verify your e-mail and phone</h2>
                <p>After you sign in, the workspace stays closed until both checks on the verification page are complete. Confirm the link sent to your e-mail, then confirm your phone with a one-time code that is valid for 10 minutes. Your organisation becomes active once the responsible contact confirms their e-mail. Text messaging for verification codes is not connected yet, so the code is written to the application log for your technical team to pass on. This is separate from the workforce SMS notices described under <a href="#notifications">Notifications</a>.</p>

                <h2 id="roles">Roles and what they see</h2>
                <p>ORBIT shows each person only the menus and figures their role allows. Hiding a menu is never the only control: the server checks every request.</p>
                <ul>
                    <li><strong>Organisation administrator</strong> runs the whole workspace: settings, branding, staff register, departments, locations, user accounts, roles, attendance, schedules, leave, notifications policy, analytics, and the audit trail.</li>
                    <li><strong>Organisation manager</strong> runs day-to-day operations: staff, attendance, schedules, leave decisions, and analytics. A manager who heads one or more departments sees only those departments in registers, approvals, analytics, and exports.</li>
                    <li><strong>Member</strong> uses the personal workspace: <em>My attendance</em>, their own schedule, leave requests, and notifications. Members never see organisation totals.</li>
                    <li><strong>Student</strong> is offered only to educational institutions and has the same personal workspace as a member.</li>
                </ul>
                <p>Administrators can see the permissions behind each role under <em>Users &amp; access → Roles &amp; permissions</em>.</p>

                {{-- Set up --}}
                <h2 id="settings">Organisation settings</h2>
                <p>Administrators update the organisation name, type, time zone, and contact details from <em>Organisation settings → Profile</em>. The time zone decides what "today" means for attendance, which dates appear on the dashboard, and how check-in times are displayed.</p>

                <h2 id="workspace">Workspace URL and branding</h2>
                <p>Each organisation has its own workspace address, separate from the public ORBIT site. After registration, ORBIT creates a primary address from an available subdomain. Administrators can add shorter aliases (for example <code>mcc.orbit.test</code> alongside <code>mobilecontent.orbit.test</code>). Both names open the same organisation. Custom domains stay inactive until a DNS TXT record proves the organisation controls the name.</p>
                <p>Under <em>Branding</em>, administrators set the display name, logo, favicon, brand colours, login heading, and welcome message. Save a draft, preview the login page, then publish. Until you publish, visitors see ORBIT's default presentation. Colours are six-digit hex values; logos must be PNG, JPG, WEBP, or ICO (not SVG). HTML and JavaScript are not accepted. ORBIT remains attributed as the platform.</p>

                <h2 id="structure">Departments and locations</h2>
                <p><em>Departments</em> group people for registers, approvals, and analytics. Each department can name a <em>department head</em> from the staff register; a manager who heads a department is limited to that department's people.</p>
                <p><em>Locations</em> are the places attendance is recorded against: an <em>Office</em>, a <em>Field area</em>, or a <em>Remote</em> location. Give a location map coordinates and a radius to make it a geofence for GPS check-in. Inactive locations stay on past records but cannot be chosen for new ones.</p>

                <h2 id="staff">The staff register</h2>
                <ol>
                    <li>Open <em>Staff register</em> and choose <em>Add staff member</em>.</li>
                    <li>Enter the staff or student number, name, contact details, job title, department, location, work type (office, field, remote, or hybrid), and status.</li>
                    <li>Save. The person now appears in registers, schedules, and attendance figures.</li>
                </ol>
                <p>A staff record is not a login. It is linked to a user account when the person accepts an invitation or an administrator links them. The dashboard tells you how many active people have no account yet. Status matters: only <em>Active</em> people count towards today's attendance, and <em>On leave</em> on the register is separate from leave recorded on a given day.</p>

                <h2 id="invite">Invite and manage accounts</h2>
                <ol>
                    <li>Open <em>Users &amp; access → User accounts</em> and choose <em>Invite user</em>.</li>
                    <li>Enter the person's e-mail address and choose a role.</li>
                    <li>They receive an e-mail with a single-use link that expires after {{ config('orbit.invitation_ttl_days') }} days.</li>
                    <li>When they open the link they set their name and password and join your organisation with the role you chose.</li>
                </ol>
                <p>Pending invitations can be resent or revoked. A revoked or expired link cannot be used. Administrators can change a person's role or suspend an account; a suspended person cannot sign in, and the change is written to the audit trail.</p>

                {{-- Daily work --}}
                <h2 id="attendance">Check in and out</h2>
                <ol>
                    <li>Open <em>My attendance</em> on your phone or computer.</li>
                    <li>Choose how to check in. Your organisation decides which methods are enabled:
                        <ul>
                            <li><strong>Dynamic QR</strong>: scan the code shown at an open attendance session. The code changes on a short timer, so a photo of it soon stops working.</li>
                            <li><strong>GPS geofence</strong>: allow your browser to share your position once. The server checks it against an approved location. ORBIT does not track you in the background.</li>
                            <li><strong>One-time code</strong>, <strong>Beacon evidence</strong>, and <strong>Device binding</strong> where your organisation has set them up. <strong>Wi-Fi corroboration</strong> can support another method but cannot confirm attendance on its own.</li>
                        </ul>
                    </li>
                    <li>Check out at the end of the period from the same page.</li>
                </ol>
                <p>You are <em>Late</em> when you check in after the start of your scheduled period plus your organisation's grace period (10 minutes unless your organisation changes it). A record that is missing a step is <em>Incomplete</em>. Approved leave appears as <em>On leave</em>. You cannot check in twice for the same period. Field officers can use <em>Field monitoring</em> to record work at a field site.</p>

                <h2 id="sessions">Run QR sessions and keep records</h2>
                <p>Administrators and managers open a session from <em>QR sessions</em>: name it, choose the location and time window, and set how long each code lasts (10 to 120 seconds). Display the session screen where people arrive. You can pause or close a session at any time; a closed session stops accepting check-ins.</p>
                <p><em>Records</em> shows the attendance register for a date, filtered by status or department. The summary at the top shows present, late, and on leave, plus <em>Uncovered</em>: active people with no covering record yet. Use <em>Authorised correction</em> to add or fix a record when someone could not check in. Every correction records who made it.</p>

                <h2 id="schedules">Schedules</h2>
                <ol>
                    <li>Open <em>Schedules</em> and create a <em>New shift</em> with a name, work arrangement, department, expected location, start and end times (a shift may cross midnight), and the dates it applies.</li>
                    <li>Shifts start as drafts. Assign people, then <em>publish</em>. Draft shifts do not count towards coverage or lateness.</li>
                    <li>Use a <em>Working pattern</em> to assign a department on chosen weekdays in one step. The pattern skips days on the non-working calendar.</li>
                    <li>Add public holidays and closures to the <em>non-working calendar</em>.</li>
                </ol>
                <p>Published assignments decide when a person is expected, so lateness and schedule coverage are measured against the right shift. The dashboard warns you about published shifts in the next 7 days that still have nobody assigned. A person can have more than one period on the same date.</p>

                <h2 id="leave">Leave</h2>
                <ol>
                    <li>Open <em>Leave</em>. Your balances for the open leave year are shown at the top: days available out of your entitlement.</li>
                    <li>Choose <em>Request leave</em>, pick the leave type and dates, give a reason, and attach a supporting document if needed (PDF, JPG, or PNG, up to 2 MB).</li>
                    <li>Your request shows as <em>Submitted</em> under <em>Pending</em>. While it is pending you can amend or withdraw it.</li>
                </ol>
                <p>Approvers see <em>Requests for my approval</em> and decide with a short note. Depending on the leave type, the approver is any approver, the person's line manager, or their department head. People cannot approve their own requests unless the organisation allows it. Approved leave appears on the attendance register as <em>On leave</em>, and an approver can cancel approved leave, which restores the days.</p>
                <p>Administrators manage <em>Leave types</em>: whether a type uses a balance, its default days, and its approval mode. Balance adjustments are recorded with a reason.</p>

                <h2 id="notifications">Notifications</h2>
                <p>The bell in the top bar shows your latest notices; <em>Notifications</em> holds the full inbox, newest first, filtered by leave, schedule, attendance, or announcements. Opening a notice marks it read, and <em>Mark all as read</em> clears the rest.</p>
                <p>Under <em>Your choices</em> you decide how optional notices reach you: in the app, by e-mail, or by SMS. Approval results, schedule assignments, and attendance problems always reach you while your organisation allows that channel. Administrators set the organisation's channel policy and message templates. SMS is sent through the platform's configured provider; if none is active, the message is logged instead. ORBIT sends a daily reminder at 17:00 and checks hourly for delayed field activity.</p>

                {{-- Insight --}}
                <h2 id="dashboard">Your dashboard</h2>
                <p>The dashboard adapts to your role:</p>
                <ul>
                    <li><strong>Organisation overview</strong> (administrators) and <strong>Department overview</strong> (department heads) open with an <em>Operational briefing</em>: how many active people are covered today and the decisions waiting, each with a button that takes you to the right page. Below it are today's position, the last 14 days of attendance, today's coverage, schedule coverage, leave decisions, workforce composition, notifications, and administration links.</li>
                    <li><strong>Your workspace</strong> (members and students) shows your status today, your next scheduled period, unread notices, open requests, leave balances, and recent attendance. It never shows organisation figures.</li>
                </ul>
                <p><em>Uncovered</em> means active people with no covering record today (present, late, on leave, incomplete, or excused). It is calculated, so early in the day it is naturally high. Every chart states the decision it supports under <em>Informs</em>. Figures may be up to 60 seconds old.</p>

                <h2 id="analytics">Analytics and export</h2>
                <p><em>Analytics</em> lets administrators, managers, and department heads look at any range of up to 62 days, filtered by department and location. A longer or reversed range is replaced by the latest 30 days, and the page says so. The page shows:</p>
                <ul>
                    <li><strong>Operational intelligence</strong>: calculated notes such as thin coverage, a drop in attendance, a rise in incomplete records, unassigned shifts, or leave concentrated in one department. They are fixed rules that quote your stored figures. They are not written by an AI model and they never change a record.</li>
                    <li>Today's figures, the daily trend, status share, verification methods, work types, departments, schedule adherence, leave decisions and utilisation, scheduled arrangements, and notification read and delivery counts.</li>
                </ul>
                <p>Choose <em>Export CSV</em> to download the attendance records for the same range and filters. The export follows the same scope as the page, so a department head exports only their departments.</p>

                <h2 id="audit">Audit and sign-in history</h2>
                <p><em>Audit &amp; security → Audit trail</em> lists administrative actions with who did what and when, such as staff created, invitations sent, roles changed, corrections made, and sessions opened. <em>Sign-in history</em> lists successful and failed sign-ins and lockouts for your organisation. Use both to answer "who changed this?" and "is someone trying to get into an account?".</p>

                {{-- More --}}
                <h2 id="education">Schools and universities</h2>
                <p>When the organisation type is <em>Educational institution</em>, ORBIT uses school wording. The Student role becomes available, students get a <em>Student workspace</em>, and role descriptions mention lecturers instead of field officers. The administrator's dashboard shows how many students, lecturers, and other people are active on the register, taken from register titles. This is a register count, not a class attendance roll: ORBIT does not yet record attendance per course or lecture.</p>
                <p>Students check in with <em>My attendance</em> in the same way as staff, see their own leave balances and requests, and receive notices in their inbox.</p>

                <h2 id="account">Your account and theme</h2>
                <p>Open <em>My account</em> from the menu under your name to see your role, verification status, account details, the permissions your role grants, and your last 10 sign-in events. Changes to your access are made by an administrator.</p>
                <p>Use the theme button in the header to switch between the default light theme and the deep teal theme. Your choice is remembered on this device unless your organisation fixes the theme.</p>

                <h2 id="scope">Not in this release</h2>
                <p>This guide describes ORBIT as it works today. The following are not part of the current release: PDF or Excel reports and scheduled report delivery (CSV export is available), a dashboard builder, AI-written analysis, attendance per course or class, face, fingerprint, or NFC check-in, background location tracking, a native mobile app, and payments.</p>
            </div>
        </div>
    </div>
</x-layouts.public>
