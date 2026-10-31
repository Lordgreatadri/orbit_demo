<x-layouts.auth title="This invitation is not valid" description="The link may have expired, already been used, or been revoked by the organisation.">
    <div class="space-y-4 text-sm text-fg-muted">
        <p>Ask your organisation administrator to send a new invitation. Invitations expire after {{ config('orbit.invitation_ttl_days') }} days and can only be used once.</p>
        <div class="flex flex-wrap gap-3">
            <x-button :href="route('login')" variant="secondary">Sign in</x-button>
            <x-button :href="\App\Tenancy\Origin::centralUrl('/contact')" variant="ghost">Contact support</x-button>
        </div>
    </div>
</x-layouts.auth>
