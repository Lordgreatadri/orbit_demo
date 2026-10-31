@props(['organization', 'domains', 'baseDomain', 'types'])

@php
    $prefix = request()->routeIs('admin.*') ? 'admin' : 'org';
@endphp

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-4 lg:col-span-2">
        <x-data-table caption="Registered hostnames">
            <thead>
                <tr>
                    <th scope="col">Hostname</th>
                    <th scope="col">Type</th>
                    <th scope="col">Status</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($domains as $domain)
                    <tr>
                        <td>
                            <p class="font-mono text-sm text-fg">{{ $domain->domain }}</p>
                            @if ($domain->is_primary)
                                <x-badge tone="accent" class="mt-1">Primary</x-badge>
                            @endif
                        </td>
                        <td>{{ $domain->type->label() }}</td>
                        <td>
                            @if ($domain->is_verified && $domain->is_active)
                                <x-badge tone="success">Active</x-badge>
                            @elseif ($domain->type->requiresVerification() && ! $domain->is_verified)
                                <x-badge tone="warning">Awaiting DNS</x-badge>
                            @else
                                <x-badge tone="neutral">Inactive</x-badge>
                            @endif
                        </td>
                        <td class="text-right">
                            <div class="flex flex-wrap justify-end gap-1">
                                @if (! $domain->is_primary && $domain->isResolvable())
                                    <form method="POST" action="{{ route($prefix.'.organizations.domains.primary', [$organization, $domain]) }}">
                                        @csrf
                                        <x-button type="submit" variant="ghost" size="sm">Make primary</x-button>
                                    </form>
                                @endif
                                @if ($domain->type->requiresVerification() && ! $domain->is_verified)
                                    <form method="POST" action="{{ route($prefix.'.organizations.domains.verify', [$organization, $domain]) }}">
                                        @csrf
                                        <x-button type="submit" variant="secondary" size="sm">Check DNS</x-button>
                                    </form>
                                @endif
                                @if (! $domain->is_primary && $domain->is_active)
                                    <form method="POST" action="{{ route($prefix.'.organizations.domains.deactivate', [$organization, $domain]) }}">
                                        @csrf
                                        <x-button type="submit" variant="ghost" size="sm">Deactivate</x-button>
                                    </form>
                                @elseif (! $domain->is_primary && ! $domain->is_active)
                                    <form method="POST" action="{{ route($prefix.'.organizations.domains.activate', [$organization, $domain]) }}">
                                        @csrf
                                        <x-button type="submit" variant="ghost" size="sm">Activate</x-button>
                                    </form>
                                @endif
                                @if (! $domain->is_primary)
                                    <form method="POST" action="{{ route($prefix.'.organizations.domains.destroy', [$organization, $domain]) }}" onsubmit="return confirm('Remove {{ $domain->domain }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <x-button type="submit" variant="ghost" size="sm">Remove</x-button>
                                    </form>
                                @endif
                            </div>
                            @if ($domain->type->requiresVerification() && $domain->verification_token)
                                <p class="mt-2 text-left text-xs text-fg-subtle">TXT <span class="font-mono">_orbit-verify.{{ $domain->domain }}</span> = <span class="font-mono">{{ $domain->verification_token }}</span></p>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-data-table>
    </div>

    <div class="space-y-6">
        <x-card title="Add a hostname" description="Aliases under *.{{ $baseDomain }} are verified immediately. Custom domains need a DNS TXT record first.">
            <form method="POST" action="{{ route($prefix.'.organizations.domains.store', $organization) }}" class="space-y-4">
                @csrf
                <x-form.input name="domain" label="Hostname" placeholder="mcc.{{ $baseDomain }}" required hint="Full hostname, no https://." />
                <x-form.select name="type" label="Type" :options="$types" required />
                <x-form.checkbox name="make_primary">Make this the primary workspace URL (aliases only)</x-form.checkbox>
                <x-button type="submit" class="w-full">Register hostname</x-button>
            </form>
        </x-card>
        <x-card title="Reserved names">
            <p class="text-sm text-fg-muted">www, api, admin, app, mail, and other infrastructure names cannot be registered as tenant subdomains.</p>
        </x-card>
    </div>
</div>
