<x-layouts.public title="Contact" description="Contact the ORBIT team or request a demonstration for your organisation.">
    <x-public.page-hero
        eyebrow="Contact"
        title="Talk to us"
        lead="Request a demonstration, ask about onboarding your organisation, or report a problem."
    />

    <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
        <div class="grid gap-10 lg:grid-cols-12">
            <div class="space-y-4 lg:col-span-4">
                <div class="card-hover rounded-2xl border border-edge bg-surface p-6">
                    <span class="grid size-11 place-items-center rounded-xl bg-primary-soft text-primary"><x-icon name="mail" class="size-5.5" /></span>
                    <h2 class="mt-4 text-lg font-semibold text-fg">E-mail</h2>
                    <a href="mailto:{{ config('orbit.support_email') }}" class="mt-1 inline-block text-[0.9375rem] font-medium text-primary hover:underline">{{ config('orbit.support_email') }}</a>
                </div>
                <div class="card-hover rounded-2xl border border-edge bg-surface p-6">
                    <span class="grid size-11 place-items-center rounded-xl bg-accent-soft text-accent"><x-icon name="chat" class="size-5.5" /></span>
                    <h2 class="mt-4 text-lg font-semibold text-fg">Response</h2>
                    <p class="mt-1 text-[0.9375rem] leading-relaxed text-fg-muted">We reply by e-mail to the address you provide. Messages are kept only as long as needed to respond.</p>
                </div>
                <div class="card-hover rounded-2xl border border-edge bg-surface p-6">
                    <span class="grid size-11 place-items-center rounded-xl bg-success-soft text-success"><x-icon name="sparkles" class="size-5.5" /></span>
                    <h2 class="mt-4 text-lg font-semibold text-fg">Request a demo</h2>
                    <p class="mt-1 text-[0.9375rem] leading-relaxed text-fg-muted">Tell us about your organisation — campus, offices, or field teams — and we will walk you through ORBIT.</p>
                </div>
            </div>

            <div class="lg:col-span-8">
                <x-card title="Send a message" description="All fields except organisation are required." class="rounded-2xl">
                    <x-flash class="mb-5" />

                    <form method="POST" action="{{ route('contact.store') }}" class="space-y-5" x-data="{ submitting: false }" @submit="submitting = true">
                        @csrf

                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-form.input name="name" label="Your name" required autocomplete="name" />
                            <x-form.input name="email" label="E-mail" type="email" required autocomplete="email" />
                        </div>
                        <x-form.input name="organization_name" label="Organisation" autocomplete="organization" hint="Optional" />
                        <x-form.input name="subject" label="Subject" required />
                        <x-form.textarea name="message" label="Message" required rows="6" hint="At least 20 characters." />

                        {{-- Honeypot: hidden from people, attractive to bots. --}}
                        <div class="hidden" aria-hidden="true">
                            <label for="website">Website</label>
                            <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="flex flex-col gap-4 border-t border-edge pt-5 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-sm text-fg-subtle">By sending, you agree to our <a href="{{ route('privacy') }}" class="text-primary hover:underline">Privacy Policy</a>.</p>
                            <x-button type="submit" size="lg" x-bind:disabled="submitting">Send message <x-icon name="arrow-right" class="size-4" /></x-button>
                        </div>
                    </form>
                </x-card>
            </div>
        </div>
    </div>

    @php
        $faqs = [
            'Getting started' => [
                ['Who can register an organisation?', 'Only the responsible contact for the organisation, such as a registrar, head of administration, or operations lead. They become the first administrator. Staff, students, and field officers do not register themselves; an administrator invites them.'],
                ['How long does setup take?', 'Registration takes a few minutes. After you verify your e-mail and phone, add departments and locations, add people to the staff register, and invite them. You can open your first attendance session the same day.'],
                ['Do we need special hardware?', 'No. ORBIT runs in the web browser on the phones and computers your people already use. There are no biometric terminals or card readers to install.'],
                ['Can we see ORBIT before registering?', 'Yes. Use the form above to request a demonstration. Tell us whether you run a campus, offices, or field teams, and we will walk you through the parts that matter to you.'],
            ],
            'Attendance and location' => [
                ['How does QR check-in prevent cheating?', 'Codes are shown at an open session and change on a short timer, between 10 and 120 seconds, set by the session. A photo or forwarded screenshot stops working quickly. Codes are tied to your organisation and session, and each person can check in only once per period.'],
                ['Does ORBIT track where people are all day?', 'No. Location is read only when a person chooses to check in with GPS, and only to confirm they are inside an approved location. There is no background tracking.'],
                ['What happens if someone cannot check in?', 'An authorised administrator or manager can add an authorised correction on the attendance register. Every correction records who made it and appears in the audit trail.'],
                ['How is lateness decided?', 'A check-in after the start of the person\'s scheduled period plus the organisation\'s grace period is recorded as late. The grace period is 10 minutes unless your organisation changes it.'],
            ],
            'Data, privacy, and security' => [
                ['Is our data kept separate from other organisations?', 'Yes. Every organisation has its own workspace address, and every record carries its organisation. Each request is checked against the signed-in person\'s organisation and role.'],
                ['Who can see the dashboard and analytics?', 'Administrators see the whole organisation. A manager who heads a department sees only that department. Members and students see only their own records. The platform team sees aggregate counts, not your people\'s attendance.'],
                ['Are the insights written by AI?', 'No. The notes on the analytics page are calculated from your stored records with fixed rules, and they quote the figures they use. They do not change any record.'],
                ['Can we export our records?', 'Yes. Analytics can export the attendance records for the selected range as a CSV file, limited to what your role is allowed to see.'],
            ],
            'Schools, leave, and notifications' => [
                ['Does ORBIT work for schools and universities?', 'Yes. Educational institutions get school wording, a Student role, and a student workspace. Attendance is recorded per person and period; per-course or per-lecture attendance is not part of the current release.'],
                ['How does leave approval work?', 'People request leave with dates, a reason, and an optional document. The approver is any approver, the line manager, or the department head, depending on the leave type. Approved leave appears on the attendance register as on leave.'],
                ['Will people get SMS messages?', 'Notices reach people in the app and by e-mail. SMS is used once a messaging provider is configured for the platform, and each person can choose which optional notices they receive by SMS.'],
                ['Where can I learn more?', 'The user guide walks through each feature step by step, and How it works explains the product in plain language.'],
            ],
        ];
    @endphp

    <section id="faq" aria-labelledby="faq-heading" class="border-t border-edge bg-surface">
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <p class="eyebrow">Questions and answers</p>
                    <h2 id="faq-heading" class="mt-3 text-3xl font-semibold tracking-tight text-balance text-fg sm:text-4xl">Frequently asked questions</h2>
                    <p class="mt-4 text-lg text-pretty text-fg-muted">Select a question to read the answer. Select it again to close it.</p>
                </div>
                <div class="flex shrink-0 flex-wrap gap-3">
                    <x-button :href="route('guide')" variant="secondary"><x-icon name="book" class="size-4" /> User guide</x-button>
                    <x-button :href="route('about')" variant="ghost">How it works</x-button>
                </div>
            </div>

            <div class="mt-12 grid gap-x-10 gap-y-10 lg:grid-cols-2">
                @foreach ($faqs as $topic => $items)
                    <div>
                        <h3 class="text-sm font-semibold text-primary">{{ $topic }}</h3>
                        <div class="mt-3 divide-y divide-edge overflow-hidden rounded-xl border border-edge bg-page/50">
                            @foreach ($items as [$question, $answer])
                                <details class="group">
                                    <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 text-left font-medium text-fg transition-colors hover:bg-surface-muted focus-visible:outline-2 focus-visible:-outline-offset-2 [&::-webkit-details-marker]:hidden">
                                        <span>{{ $question }}</span>
                                        <span class="grid size-7 shrink-0 place-items-center rounded-full border border-edge bg-surface text-fg-muted transition-transform duration-200 group-open:rotate-45 group-open:border-primary group-open:text-primary" aria-hidden="true">
                                            <x-icon name="plus" class="size-4" />
                                        </span>
                                    </summary>
                                    <div class="px-5 pb-5 text-[0.9375rem] leading-relaxed text-fg-muted">
                                        <p>{{ $answer }}</p>
                                        @if ($question === 'Where can I learn more?')
                                            <p class="mt-2 flex flex-wrap gap-x-4 gap-y-1"><a href="{{ route('guide') }}" class="font-medium text-primary hover:underline">Open the user guide</a><a href="{{ route('about') }}" class="font-medium text-primary hover:underline">How it works</a></p>
                                        @endif
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
</x-layouts.public>
