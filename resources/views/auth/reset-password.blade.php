<x-layouts.auth title="Choose a new password">
    <form method="POST" action="{{ route('password.update') }}" class="space-y-5" x-data="{ submitting: false }" @submit="submitting = true">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <x-form.input name="email" label="E-mail" type="email" :value="$email" required autocomplete="username" />
        <x-form.input name="password" label="New password" type="password" required autocomplete="new-password" hint="At least 10 characters with letters and numbers." />
        <x-form.input name="password_confirmation" label="Confirm new password" type="password" required autocomplete="new-password" />

        <x-button type="submit" class="w-full" x-bind:disabled="submitting">Reset password</x-button>
    </form>
</x-layouts.auth>
