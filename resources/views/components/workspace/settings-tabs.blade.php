@props(['organization', 'current'])

@php
    $prefix = request()->routeIs('admin.*') ? 'admin' : 'org';
    $tabs = [
        'profile' => [
            'label' => $prefix === 'admin' ? 'Overview' : 'Profile',
            'route' => $prefix === 'admin' ? 'admin.organizations.show' : 'org.organizations.edit',
        ],
        'domains' => ['label' => 'Domains', 'route' => $prefix.'.organizations.domains'],
        'branding' => ['label' => 'Branding', 'route' => $prefix.'.organizations.branding'],
    ];
@endphp

<nav class="mb-6 flex flex-wrap gap-1 rounded-xl border border-edge bg-surface p-1" aria-label="Organisation settings">
    @foreach ($tabs as $key => $tab)
        <a
            href="{{ route($tab['route'], $organization) }}"
            @if ($current === $key) aria-current="page" @endif
            @class([
                'rounded-lg px-4 py-2 text-sm font-medium transition-colors',
                'bg-primary-soft text-fg' => $current === $key,
                'text-fg-muted hover:bg-surface-muted hover:text-fg' => $current !== $key,
            ])
        >{{ $tab['label'] }}</a>
    @endforeach
</nav>
