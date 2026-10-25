<x-layouts.auth title="Verify your e-mail address" description="We sent a verification link to {{ auth()->user()->email }}. Your organisation becomes active once you confirm it.">
    <x-flash class="mb-5" />

    <div class="space-y-4">
        <p class="text-sm text-fg-muted">Didn't receive the e-mail? Check your spam folder, or request another link below.</p>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <x-button type="submit">Resend verification e-mail</x-button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-button type="submit" variant="ghost">Sign out</x-button>
            </form>
        </div>
    </div>
</x-layouts.auth>
