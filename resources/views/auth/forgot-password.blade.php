<x-layouts.auth title="Reset your password" description="Enter your e-mail address. If an account exists, we will send a link to choose a new password.">
    <x-flash class="mb-5" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5" x-data="{ submitting: false }" @submit="submitting = true">
        @csrf
        <x-form.input name="email" label="E-mail" type="email" required autocomplete="username" autofocus />
        <x-button type="submit" class="w-full" x-bind:disabled="submitting">Send reset link</x-button>
    </form>

    <x-slot:footer>
        <a href="{{ route('login') }}" class="text-primary hover:underline">Back to sign in</a>
    </x-slot:footer>
</x-layouts.auth>
