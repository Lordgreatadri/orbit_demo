@php
    $branding = $workspaceBranding ?? \App\Tenancy\WorkspaceBranding::platform();
    $onTenant = $branding->organization !== null;
@endphp

<x-layouts.auth :title="$branding->loginHeading" :description="$branding->loginMessage">
    @if (! empty($preview))
        <p class="mb-4 rounded-lg bg-warning-soft px-3 py-2 text-sm text-warning">Preview — this draft is not live yet.</p>
    @endif
    <x-flash class="mb-5" />

    <form method="POST" action="{{ route('login.store') }}" class="space-y-5" x-data="{ submitting: false }" @submit="submitting = true">
        @csrf

        <x-form.input name="email" label="E-mail" type="email" required autocomplete="username" autofocus />

        <div class="space-y-1.5">
            <x-form.input name="password" label="Password" type="password" required autocomplete="current-password" />
            <div class="text-right">
                <a href="{{ route('password.request') }}" class="text-sm text-primary hover:underline">Forgot your password?</a>
            </div>
        </div>

        <x-form.checkbox name="remember">Keep me signed in on this device</x-form.checkbox>

        <x-button type="submit" class="w-full" x-bind:disabled="submitting">Sign in</x-button>
    </form>

    <x-slot:footer>
        @if ($onTenant)
            {{ $branding->footerText }}
            @if ($branding->supportEmail)
                <br><a href="mailto:{{ $branding->supportEmail }}" class="text-primary hover:underline">{{ $branding->supportEmail }}</a>
            @endif
        @else
            Need an ORBIT workspace for your organisation? <a href="{{ route('register') }}" class="text-primary hover:underline">Register your organisation</a>.
            <br><span class="text-fg-subtle">Staff and students join through an invitation from their administrator.</span>
        @endif
    </x-slot:footer>
</x-layouts.auth>
