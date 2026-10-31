@props(['title', 'wide' => false])

@php
    $user = auth()->user();
    $organization = $user?->organization;
    $isPlatform = $user?->isPlatformUser() ?? false;
    $sections = $user ? \App\Support\Navigation::for($user) : [];
    $homeRoute = $isPlatform ? route('admin.dashboard') : route('org.dashboard');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-layouts.partials.head :title="$title" />
</head>
<body class="min-h-svh bg-page" x-data="{ sidebar: false }" @keydown.escape.window="sidebar = false" x-effect="document.documentElement.classList.toggle('overflow-hidden', sidebar)">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-primary focus:px-4 focus:py-2 focus:text-primary-fg">Skip to content</a>

    {{-- Mobile overlay --}}
    <div x-show="sidebar" x-cloak x-transition.opacity @click="sidebar = false" class="fixed inset-0 z-30 bg-black/50 backdrop-blur-[2px] lg:hidden" aria-hidden="true"></div>

    {{--
        Sidebar: fixed to the viewport on every breakpoint, so it never scrolls
        with the page. The nav list scrolls internally when it is taller than
        the viewport. On small screens it slides in as a drawer.
    --}}
    <aside
        id="sidebar"
        class="fixed inset-y-0 left-0 z-40 flex w-[18rem] max-w-[85vw] -translate-x-full flex-col border-r border-edge bg-surface shadow-lift transition-transform duration-200 ease-out lg:translate-x-0 lg:shadow-none"
        :class="sidebar ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        aria-label="Sidebar"
    >
        <div class="flex h-16 shrink-0 items-center justify-between border-b border-edge px-4">
            <a href="{{ $homeRoute }}" class="rounded-lg focus-visible:outline-2" aria-label="{{ config('orbit.product_name') }} home"><x-logo /></a>
            <button type="button" class="rounded-lg p-2 text-fg-muted hover:bg-surface-muted hover:text-fg lg:hidden" @click="sidebar = false" aria-label="Close navigation">
                <x-icon name="x" class="size-5" />
            </button>
        </div>

        @if ($organization)
            <div class="shrink-0 border-b border-edge px-4 py-3">
                <p class="truncate text-sm font-semibold text-fg" title="{{ ($workspaceBranding ?? null)?->displayName ?? $organization->name }}">{{ ($workspaceBranding ?? null)?->displayName ?? $organization->name }}</p>
                <p class="mt-0.5 flex items-center gap-1.5 text-xs text-fg-subtle">
                    <span>{{ $organization->type->label() }}</span>
                    @if ($organization->isDemo())
                        <x-badge tone="warning" class="!px-1.5 !py-0 text-[0.65rem]">Demo data</x-badge>
                    @endif
                </p>
            </div>
        @elseif ($isPlatform)
            <div class="shrink-0 border-b border-edge px-4 py-3">
                <p class="flex items-center gap-2 text-sm font-semibold text-fg"><x-icon name="shield-check" class="size-4 text-primary" /> Platform console</p>
                <p class="mt-0.5 text-xs text-fg-subtle">Cross-organisation actions are audited.</p>
            </div>
        @endif

        <nav class="flex-1 overflow-y-auto overscroll-contain px-3 py-4" aria-label="Application">
            @foreach ($sections as $section)
                <div @class(['mt-5' => ! $loop->first])>
                    <p class="px-3 pb-1.5 text-[0.68rem] font-semibold tracking-[0.12em] text-fg-subtle uppercase">{{ $section['heading'] }}</p>
                    <ul class="space-y-0.5">
                        @foreach ($section['items'] as $item)
                            @php $active = \App\Support\Navigation::isActive($item); @endphp
                            @if (isset($item['children']))
                                <li x-data="{ open: {{ $active ? 'true' : 'false' }} }">
                                    <button
                                        type="button"
                                        @click="open = !open"
                                        :aria-expanded="open.toString()"
                                        class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm font-medium transition-colors {{ $active ? 'text-fg' : 'text-fg-muted hover:bg-surface-muted hover:text-fg' }}"
                                    >
                                        <x-icon :name="$item['icon'] ?? 'layers'" class="size-5 shrink-0 {{ $active ? 'text-primary' : 'text-fg-subtle' }}" />
                                        <span class="flex-1 truncate">{{ $item['label'] }}</span>
                                        <x-icon name="chevron-down" class="size-4 shrink-0 text-fg-subtle transition-transform duration-200" x-bind:class="open && 'rotate-180'" />
                                    </button>
                                    <ul x-show="open" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" class="mt-0.5 ml-[1.35rem] space-y-0.5 border-l border-edge pl-3">
                                        @foreach ($item['children'] as $child)
                                            @php $childActive = \App\Support\Navigation::isActive($child); @endphp
                                            <li>
                                                <a href="{{ route($child['route'], $child['params'] ?? []) }}" @if ($childActive) aria-current="page" @endif class="flex items-center gap-2 rounded-md px-2.5 py-1.5 text-sm transition-colors {{ $childActive ? 'bg-accent-soft font-medium text-fg' : 'text-fg-muted hover:bg-surface-muted hover:text-fg' }}">
                                                    <span class="flex-1 truncate">{{ $child['label'] }}</span>
                                                    @if ($child['soon'] ?? false)
                                                        <span class="rounded-full border border-edge px-1.5 text-[0.6rem] font-semibold tracking-wide text-fg-subtle uppercase">Soon</span>
                                                    @endif
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </li>
                            @else
                                <li>
                                    <a href="{{ route($item['route'], $item['params'] ?? []) }}" @if ($active) aria-current="page" @endif class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors {{ $active ? 'bg-accent-soft text-fg' : 'text-fg-muted hover:bg-surface-muted hover:text-fg' }}">
                                        <x-icon :name="$item['icon'] ?? 'layers'" class="size-5 shrink-0 {{ $active ? 'text-primary' : 'text-fg-subtle' }}" />
                                        <span class="flex-1 truncate">{{ $item['label'] }}</span>
                                        @if ($item['soon'] ?? false)
                                            <span class="rounded-full border border-edge px-1.5 text-[0.6rem] font-semibold tracking-wide text-fg-subtle uppercase" title="Planned module">Soon</span>
                                        @endif
                                    </a>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>

        <div class="shrink-0 border-t border-edge p-3">
            <a href="{{ route('app.profile') }}" class="flex items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-surface-muted {{ request()->routeIs('app.profile') ? 'bg-accent-soft' : '' }}">
                <span aria-hidden="true" class="grid size-9 shrink-0 place-items-center rounded-full bg-primary-soft text-xs font-semibold text-primary">{{ $user->initials }}</span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-medium text-fg">{{ $user->name }}</span>
                    <span class="block truncate text-xs text-fg-subtle">{{ $user->roles->first()?->label() ?? 'No role assigned' }}</span>
                </span>
                <x-icon name="chevron-right" class="size-4 shrink-0 text-fg-subtle" />
            </a>
        </div>
    </aside>

    {{-- Main column (offset by the sidebar width on large screens) --}}
    <div class="flex min-h-svh flex-col lg:pl-[18rem]">
        <header class="sticky top-0 z-20 flex h-16 items-center justify-between gap-3 border-b border-edge bg-surface/90 px-4 backdrop-blur sm:px-6 lg:px-8">
            <div class="flex min-w-0 items-center gap-3">
                <button type="button" class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg border border-edge text-fg-muted hover:bg-surface-muted lg:hidden" @click="sidebar = true" aria-controls="sidebar" :aria-expanded="sidebar.toString()" aria-label="Open navigation">
                    <x-icon name="menu" class="size-5" />
                </button>
                <div class="min-w-0">
                    <p class="truncate text-base font-semibold text-fg">{{ $title }}</p>
                    @if ($organization)
                        <p class="hidden truncate text-xs text-fg-subtle sm:block">{{ $organization->name }}</p>
                    @elseif ($isPlatform)
                        <p class="hidden truncate text-xs text-fg-subtle sm:block">Platform console</p>
                    @endif
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <x-theme-toggle label-class="hidden xl:inline" />
                <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
                    <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="menu" class="flex h-9 items-center gap-2 rounded-lg border border-edge bg-surface px-2 text-sm shadow-sm hover:border-edge-strong">
                        <span aria-hidden="true" class="grid size-6 place-items-center rounded-full bg-primary-soft text-xs font-semibold text-primary">{{ $user->initials }}</span>
                        <span class="hidden max-w-32 truncate sm:inline">{{ $user->first_name }}</span>
                        <x-icon name="chevron-down" class="size-4 text-fg-subtle" />
                    </button>
                    <div x-show="open" x-cloak x-transition.origin.top.right @click.outside="open = false" role="menu" class="absolute right-0 mt-2 w-60 rounded-xl border border-edge bg-surface p-1 shadow-lift">
                        <div class="px-3 py-2">
                            <p class="truncate text-sm font-medium text-fg">{{ $user->name }}</p>
                            <p class="truncate text-xs text-fg-subtle">{{ $user->email }}</p>
                        </div>
                        <div class="my-1 border-t border-edge"></div>
                        <a href="{{ route('app.profile') }}" role="menuitem" class="flex items-center gap-2 rounded-md px-3 py-2 text-sm text-fg-muted hover:bg-surface-muted hover:text-fg"><x-icon name="user" class="size-4" /> My account</a>
                        <a href="{{ route('guide') }}" role="menuitem" class="flex items-center gap-2 rounded-md px-3 py-2 text-sm text-fg-muted hover:bg-surface-muted hover:text-fg"><x-icon name="book" class="size-4" /> User guide</a>
                        <div class="my-1 border-t border-edge"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" role="menuitem" class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm text-fg-muted hover:bg-surface-muted hover:text-fg"><x-icon name="logout" class="size-4" /> Sign out</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main id="main" class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto space-y-6 {{ $wide ? 'max-w-[90rem]' : 'max-w-7xl' }}">
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

        <footer class="border-t border-edge px-4 py-4 text-xs text-fg-subtle sm:px-6 lg:px-8">
            <div class="mx-auto flex max-w-7xl flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <p>&copy; {{ date('Y') }} {{ config('orbit.product_name') }} · {{ config('orbit.product_tagline') }}</p>
                <p class="flex items-center gap-3"><a href="{{ route('terms') }}" class="hover:text-fg">Terms</a><a href="{{ route('privacy') }}" class="hover:text-fg">Privacy</a><a href="{{ route('contact') }}" class="hover:text-fg">Support</a></p>
            </div>
        </footer>
    </div>
    @stack('scripts')
</body>
</html>
