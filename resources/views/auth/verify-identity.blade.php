<x-layouts.auth title="Verify your account" description="Confirm your e-mail and phone before the workspace opens. Both checks are on this page.">
    <x-flash class="mb-5" />

    @php
        $account = auth()->user();
        $emailRequired = (bool) config('orbit.require_email_verification');
        $phoneRequired = (bool) config('orbit.require_phone_verification');
    @endphp

    <div class="space-y-4">
        <section class="rounded-xl border border-edge bg-surface p-5" aria-labelledby="verify-email-heading">
            <h2 id="verify-email-heading" class="text-base font-semibold text-fg">Verify your e-mail address</h2>
            @if (! $emailRequired || $account->hasVerifiedEmail())
                <p class="mt-2 text-sm text-fg-muted">{{ $account->email }} is confirmed.</p>
            @else
                <p class="mt-2 text-sm text-fg-muted">We sent a verification link to {{ $account->email }}. Your organisation becomes active once the responsible contact confirms it.</p>
                <p class="mt-3 text-sm text-fg-muted">Didn't receive the e-mail? Check your spam folder, or request another link.</p>
                <form method="POST" action="{{ route('verification.send') }}" class="mt-4">
                    @csrf
                    <x-button type="submit" class="w-full">Resend verification e-mail</x-button>
                </form>
            @endif
        </section>

        <section class="rounded-xl border border-edge bg-surface p-5" aria-labelledby="verify-phone-heading">
            <h2 id="verify-phone-heading" class="text-base font-semibold text-fg">Verify your phone</h2>
            @if (! $phoneRequired || $account->hasVerifiedPhone())
                <p class="mt-2 text-sm text-fg-muted">{{ $account->phone ?: 'No phone number' }} is confirmed.</p>
            @else
                <p class="mt-2 text-sm text-fg-muted">Text messaging is not connected yet. Request a code, then ask your technical team for the value written to the application log.</p>

                <form method="POST" action="{{ route('verification.phone.update') }}" class="mt-4 space-y-3">
                    @csrf
                    @method('PUT')
                    <x-form.input name="phone" label="Phone" type="tel" :value="$account->phone" required autocomplete="tel" placeholder="+233201234567" hint="International format." />
                    <x-button type="submit" variant="secondary" class="w-full">Save number and issue a code</x-button>
                </form>

                @if ($account->phone)
                    <form method="POST" action="{{ route('verification.phone.send') }}" class="mt-3">
                        @csrf
                        <x-button type="submit" variant="ghost">Issue a new code</x-button>
                    </form>

                    <form method="POST" action="{{ route('verification.phone.confirm') }}" class="mt-4 space-y-3 border-t border-edge pt-4">
                        @csrf
                        <x-form.input name="code" label="Verification code" required autocomplete="one-time-code" inputmode="numeric" maxlength="6" placeholder="6-digit code" />
                        <x-button type="submit" class="w-full">Confirm phone</x-button>
                    </form>
                @endif
            @endif
        </section>
    </div>

    <form method="POST" action="{{ route('logout') }}" class="mt-5">
        @csrf
        <x-button type="submit" variant="ghost">Sign out</x-button>
    </form>
</x-layouts.auth>
