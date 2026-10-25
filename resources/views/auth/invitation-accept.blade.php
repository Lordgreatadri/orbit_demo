<x-layouts.auth title="Join {{ $invitation->organization->name }}" description="You have been invited as {{ $roleLabel }}. Complete your details to create your account.">
    <form method="POST" action="{{ route('invitations.accept', ['token' => $token]) }}" class="space-y-5" x-data="{ submitting: false }" @submit="submitting = true">
        @csrf

        <div class="rounded-lg border border-edge bg-surface-muted px-4 py-3 text-sm">
            <p class="text-fg-muted">Account e-mail</p>
            <p class="font-medium text-fg">{{ $invitation->email }}</p>
            <p class="mt-1 text-xs text-fg-subtle">The invitation is tied to this address. If it is not yours, do not continue.</p>
        </div>

        @error('token')
            <x-alert type="danger">{{ $message }}</x-alert>
        @enderror

        <div class="grid gap-5 sm:grid-cols-2">
            <x-form.input name="first_name" label="First name" required autocomplete="given-name" />
            <x-form.input name="last_name" label="Last name" required autocomplete="family-name" />
        </div>
        <x-form.input name="phone" label="Phone" type="tel" autocomplete="tel" placeholder="+233201234567" hint="Optional, international format." />
        <x-form.input name="password" label="Password" type="password" required autocomplete="new-password" hint="At least 10 characters with letters and numbers." />
        <x-form.input name="password_confirmation" label="Confirm password" type="password" required autocomplete="new-password" />

        <x-form.checkbox name="terms" required>
            I accept the <a href="{{ route('terms') }}" class="text-primary hover:underline" target="_blank" rel="noopener">Terms and Conditions</a> and
            <a href="{{ route('privacy') }}" class="text-primary hover:underline" target="_blank" rel="noopener">Privacy Policy</a>.
        </x-form.checkbox>

        <x-button type="submit" class="w-full" x-bind:disabled="submitting">Create my account</x-button>
    </form>
</x-layouts.auth>
