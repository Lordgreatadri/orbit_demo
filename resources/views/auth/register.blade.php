<x-layouts.auth title="Register your organisation" description="Create your organisation's workspace. You will become its first administrator." wide>
    <x-alert type="info" class="mb-6">
        This form is for the person responsible for the organisation. Employees, staff, students, and field officers do not register here — the administrator invites them after setup.
    </x-alert>

    <form method="POST" action="{{ route('register.store') }}" class="space-y-8" x-data="{ submitting: false }" @submit="submitting = true">
        @csrf

        <fieldset class="space-y-5">
            <legend class="text-base font-semibold text-fg">Organisation</legend>
            <x-form.input name="organization_name" label="Organisation name" required autocomplete="organization" />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.select name="organization_type" label="Organisation type" :options="$organizationTypes" required placeholder="Select a type" />
                <x-form.select name="timezone" label="Time zone" :options="$timezones" :value="$defaultTimezone" required hint="Used to display attendance times." />
            </div>
        </fieldset>

        <fieldset class="space-y-5">
            <legend class="text-base font-semibold text-fg">Responsible contact (initial administrator)</legend>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="first_name" label="First name" required autocomplete="given-name" />
                <x-form.input name="last_name" label="Last name" required autocomplete="family-name" />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="email" label="Work e-mail" type="email" required autocomplete="email" hint="A verification link will be sent here." />
                <x-form.input name="phone" label="Phone" type="tel" required autocomplete="tel" placeholder="+233201234567" hint="International format." />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="password" label="Password" type="password" required autocomplete="new-password" hint="At least 10 characters with letters and numbers." />
                <x-form.input name="password_confirmation" label="Confirm password" type="password" required autocomplete="new-password" />
            </div>
        </fieldset>

        <x-form.checkbox name="terms" required>
            I confirm I am authorised to register this organisation and I accept the
            <a href="{{ route('terms') }}" class="text-primary hover:underline" target="_blank" rel="noopener">Terms and Conditions</a> and
            <a href="{{ route('privacy') }}" class="text-primary hover:underline" target="_blank" rel="noopener">Privacy Policy</a>.
        </x-form.checkbox>

        <x-button type="submit" class="w-full sm:w-auto" x-bind:disabled="submitting">Create organisation</x-button>
    </form>

    <x-slot:footer>
        Already registered? <a href="{{ route('login') }}" class="text-primary hover:underline">Sign in</a>.
    </x-slot:footer>
</x-layouts.auth>
