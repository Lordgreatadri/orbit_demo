@props(['title'])

@php
    $user = auth()->user();
    $organization = $user?->organization;
    $navItems = [
        ['route' => 'app.dashboard', 'label' => 'Dashboard', 'can' => true,
            'icon' => 'M4 13h6V4H4v9Zm10 7h6v-9h-6v9ZM4 20h6v-5H4v5Zm10-9h6V4h-6v7Z'],
        ['route' => 'app.members.index', 'label' => 'Members', 'can' => $user?->can('viewAny', \App\Models\User::class),
            'icon' => 'M16 19a4 4 0 0 0-8 0M12 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm7 8a3 3 0 0 0-3-3m0-4a2.5 2.5 0 0 0 0-5M5 19a3 3 0 0 1 3-3m0-4a2.5 2.5 0 0 1 0-5'],
    ];
    if ($organization && $user->can('view', $organization)) {
        $navItems[] = ['route' => 'app.organizations.edit', 'params' => $organization, 'label' => 'Organisation settings', 'can' => true,
            'icon' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm7.4-3a7.4 7.4 0 0 0-.1-1l2-1.6-2-3.4-2.4 1a7.5 7.5 0 0 0-1.7-1l-.4-2.6H9.2L8.8 6a7.5 7.5 0 0 0-1.7 1l-2.4-1-2 3.4 2 1.6a7.4 7.4 0 0 0 0 2l-2 1.6 2 3.4 2.4-1a7.5 7.5 0 0 0 1.7 1l.4 2.6h5.6l.4-2.6a7.5 7.5 0 0 0 1.7-1l2.4 1 2-3.4-2-1.6c.1-.3.1-.7.1-1Z'];
    }
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-layouts.partials.head :title="$title" />
</head>
<body class="min-h-svh" x-data="{ sidebar: false }">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-primary focus:px-4 focus:py-2 focus:text-primary-fg">Skip to content</a>

    <div class="flex min-h-svh">
        {{-- Sidebar --}}
        <div x-show="sidebar" x-cloak @click="sidebar = false" class="fixed inset-0 z-30 bg-black/40 lg:hidden" aria-hidden="true"></div>
        <aside
            id="sidebar"
            class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col border-r border-edge bg-surface transition-transform lg:static lg:translate-x-0"
            :class="sidebar ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
            aria-label="Sidebar"
        >
            <div class="flex h-16 items-center justify-between border-b border-edge px-4">
                <a href="{{ route('app.dashboard') }}" class="rounded-lg focus-visible:outline-2"><x-logo /></a>
                <button type="button" class="rounded-lg p-2 text-fg-muted lg:hidden" @click="sidebar = false" aria-label="Close navigation">
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>

            @if ($organization)
                <div class="border-b border-edge px-4 py-3">
                    <p class="truncate text-sm font-semibold text-fg" title="{{ $organization->name }}">{{ $organization->name }}</p>
                    <p class="mt-0.5 text-xs text-fg-subtle">{{ $organization->type->label() }}</p>
                </div>
            @endif

            <nav class="flex-1 overflow-y-auto px-3 py-4" aria-label="Application">
                <ul class="space-y-1">
                    @foreach ($navItems as $item)
                        @continue(! $item['can'])
                        @php
                            $active = request()->routeIs($item['route']);
                            $href = route($item['route'], $item['params'] ?? []);
                        @endphp
                        <li>
                            <a href="{{ $href }}" @if ($active) aria-current="page" @endif class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors {{ $active ? 'bg-accent-soft text-fg' : 'text-fg-muted hover:bg-surface-muted hover:text-fg' }}">
                                <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5 shrink-0 {{ $active ? 'text-primary' : 'text-fg-subtle' }}" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $item['icon'] }}"/></svg>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-6 border-t border-edge pt-4">
                    <p class="px-3 text-xs font-semibold tracking-wide text-fg-subtle uppercase">Planned modules</p>
                    <ul class="mt-2 space-y-1 px-3 text-sm text-fg-subtle">
                        <li>Locations &amp; departments</li>
                        <li>Attendance &amp; QR sessions</li>
                        <li>Field monitoring</li>
                        <li>Schedules &amp; leave</li>
                    </ul>
                    <p class="mt-2 px-3 text-xs text-fg-subtle">Delivered in later stages of the plan.</p>
                </div>
            </nav>
        </aside>

        {{-- Main column --}}
        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-20 flex h-16 items-center justify-between gap-3 border-b border-edge bg-surface/90 px-4 backdrop-blur sm:px-6">
                <div class="flex items-center gap-3">
                    <button type="button" class="inline-flex size-9 items-center justify-center rounded-lg border border-edge text-fg-muted lg:hidden" @click="sidebar = true" aria-controls="sidebar" :aria-expanded="sidebar.toString()" aria-label="Open navigation">
                        <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    </button>
                    <h1 class="truncate text-base font-semibold text-fg">{{ $title }}</h1>
                </div>

                <div class="flex items-center gap-2">
                    <x-theme-toggle />
                    <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
                        <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="menu" class="flex h-9 items-center gap-2 rounded-lg border border-edge bg-surface px-2 text-sm hover:border-edge-strong">
                            <span aria-hidden="true" class="grid size-6 place-items-center rounded-full bg-primary-soft text-xs font-semibold text-primary">{{ $user->initials }}</span>
                            <span class="hidden max-w-32 truncate sm:inline">{{ $user->name }}</span>
                            <svg aria-hidden="true" viewBox="0 0 20 20" class="size-4 text-fg-subtle" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/></svg>
                        </button>
                        <div x-show="open" x-cloak @click.outside="open = false" role="menu" class="absolute right-0 mt-2 w-56 rounded-lg border border-edge bg-surface p-1 shadow-card">
                            <div class="px-3 py-2">
                                <p class="truncate text-sm font-medium text-fg">{{ $user->name }}</p>
                                <p class="truncate text-xs text-fg-subtle">{{ $user->email }}</p>
                            </div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" role="menuitem" class="block w-full rounded-md px-3 py-2 text-left text-sm text-fg-muted hover:bg-surface-muted hover:text-fg">Sign out</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <main id="main" class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-6xl space-y-6">
                    @if ($organization && ! $organization->isActive())
                        <x-alert type="warning" title="{{ $organization->status->label() }}">
                            @if ($organization->status === \App\Enums\OrganizationStatus::PendingVerification)
                                Your organisation becomes active once the responsible contact verifies their e-mail address.
                            @else
                                Some actions are unavailable while the organisation is {{ strtolower($organization->status->label()) }}. Contact support if you believe this is an error.
                            @endif
                        </x-alert>
                    @endif
                    <x-flash />
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>
</body>
</html>
