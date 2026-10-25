<x-layouts.public title="Contact" description="Contact the ORBIT team or request a demonstration for your organisation.">
    <x-public.page-hero
        eyebrow="Contact"
        title="Talk to us"
        lead="Request a demonstration, ask about onboarding your organisation, or report a problem."
    />

    <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
        <div class="grid gap-10 lg:grid-cols-12">
            <div class="space-y-4 lg:col-span-4">
                <div class="rounded-2xl border border-edge bg-surface p-6">
                    <span class="grid size-11 place-items-center rounded-xl bg-primary-soft text-primary"><x-icon name="mail" class="size-5.5" /></span>
                    <h2 class="mt-4 text-lg font-semibold text-fg">E-mail</h2>
                    <a href="mailto:{{ config('orbit.support_email') }}" class="mt-1 inline-block text-[0.9375rem] font-medium text-primary hover:underline">{{ config('orbit.support_email') }}</a>
                </div>
                <div class="rounded-2xl border border-edge bg-surface p-6">
                    <span class="grid size-11 place-items-center rounded-xl bg-accent-soft text-accent"><x-icon name="chat" class="size-5.5" /></span>
                    <h2 class="mt-4 text-lg font-semibold text-fg">Response</h2>
                    <p class="mt-1 text-[0.9375rem] leading-relaxed text-fg-muted">We reply by e-mail to the address you provide. Messages are kept only as long as needed to respond.</p>
                </div>
                <div class="rounded-2xl border border-edge bg-surface p-6">
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
</x-layouts.public>
