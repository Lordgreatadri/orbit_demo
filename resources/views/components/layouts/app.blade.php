@props(['title', 'wide' => false])

@php
    $user = auth()->user();
    $organization = $user?->organization;
    $isPlatform = $user?->isPlatformUser() ?? false;
    $sections = $user ? \App\Support\Navigation::for($user) : [];
    $homeRoute = $isPlatform ? route('admin.dashboard') : route('org.dashboard');
    $workspaceName = $organization ? ((($workspaceBranding ?? null)?->displayName) ?: $organization->name) : null;
    $unreadNotifications = ($user && $organization) ? \App\Workforce\Communication\Inbox::unreadCount($user) : 0;
    $recentNotifications = ($user && $organization) ? \App\Workforce\Communication\Inbox::recent($user) : collect();
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
        class="fixed inset-y-0 left-0 z-40 flex w-[17rem] max-w-[85vw] -translate-x-full flex-col border-r border-edge bg-surface shadow-lift transition-transform duration-200 ease-out lg:translate-x-0 lg:shadow-none"
        :class="sidebar ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        aria-label="Sidebar"
    >
        <div class="flex h-16 shrink-0 items-center justify-between border-b border-edge px-4">
            <a href="{{ $homeRoute }}" class="inline-flex min-w-0 items-center gap-2.5 rounded-lg focus-visible:outline-2" aria-label="{{ config('orbit.product_name') }} home">
                <img src="{{ asset('images/brand/orbit-icon.png') }}" alt="" width="36" height="36" class="size-9 shrink-0 rounded-lg object-contain">
                <span class="truncate text-lg font-semibold tracking-wide text-fg">{{ config('orbit.product_name') }}</span>
            </a>
            <button type="button" class="inline-flex size-11 items-center justify-center rounded-lg text-fg-muted hover:bg-surface-muted hover:text-fg lg:hidden" @click="sidebar = false" aria-label="Close navigation">
                <x-icon name="x" class="size-5" />
            </button>
        </div>

        @if ($organization)
            @php
                $orgMonogram = \App\Support\Monogram::for($workspaceName);
            @endphp
            <div class="shrink-0 border-b border-edge px-3 py-3">
                <div class="flex items-center gap-3 rounded-lg bg-surface-muted/70 px-3 py-2.5">
                    <span aria-hidden="true" class="grid size-9 shrink-0 place-items-center rounded-lg bg-brand-deep text-xs font-semibold tracking-wide text-white">{{ $orgMonogram ?: 'O' }}</span>
                    <div class="min-w-0">
                        <p class="text-[0.6875rem] font-medium text-fg-subtle">Workspace</p>
                        <p class="line-clamp-2 text-sm leading-snug font-semibold text-fg" title="{{ $workspaceName }}">{{ $workspaceName }}</p>
                        <p class="mt-1 flex flex-wrap items-center gap-1.5 text-[0.6875rem] text-fg-subtle">
                            <span class="truncate">{{ $organization->type->label() }}</span>
                            @if ($organization->isDemo())
                                <x-badge tone="warning" class="!px-1.5 !py-0 text-[0.625rem]">Demo data</x-badge>
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        @elseif ($isPlatform)
            <div class="shrink-0 border-b border-edge px-3 py-3">
                <div class="flex items-center gap-3 rounded-lg bg-surface-muted/70 px-3 py-2.5">
                    <span aria-hidden="true" class="grid size-9 shrink-0 place-items-center rounded-lg bg-brand-deep text-white"><x-icon name="shield-check" class="size-4.5" /></span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-fg">Platform console</p>
                        <p class="text-[0.6875rem] leading-snug text-fg-subtle">Cross-organisation actions are audited.</p>
                    </div>
                </div>
            </div>
        @endif

        <nav class="flex-1 overflow-y-auto overscroll-contain px-3 py-4" aria-label="Application">
            @foreach ($sections as $section)
                <div @class(['mt-5' => ! $loop->first])>
                    <p class="px-3 pb-1.5 text-[0.6875rem] font-semibold tracking-wide text-fg-subtle">{{ $section['heading'] }}</p>
                    <ul class="space-y-0.5">
                        @foreach ($section['items'] as $item)
                            @php $active = \App\Support\Navigation::isActive($item); @endphp
                            @if (isset($item['children']))
                                <li x-data="{ open: {{ $active ? 'true' : 'false' }} }">
                                    <button
                                        type="button"
                                        @click="open = !open"
                                        :aria-expanded="open.toString()"
                                        class="relative flex min-h-11 w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm font-medium transition-colors {{ $active ? 'bg-primary-soft text-fg' : 'text-fg-muted hover:bg-surface-muted hover:text-fg' }}"
                                    >
                                        <x-icon :name="$item['icon'] ?? 'layers'" class="size-5 shrink-0 {{ $active ? 'text-primary' : 'text-fg-subtle' }}" />
                                        <span class="flex-1 truncate">{{ $item['label'] }}</span>
                                        <x-icon name="chevron-down" class="size-4 shrink-0 text-fg-subtle transition-transform duration-200" x-bind:class="open && 'rotate-180'" />
                                    </button>
                                    <ul x-show="open" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" class="mt-0.5 ml-[1.35rem] space-y-0.5 border-l border-edge pl-3">
                                        @foreach ($item['children'] as $child)
                                            @php $childActive = \App\Support\Navigation::isActive($child); @endphp
                                            <li>
                                                <a href="{{ route($child['route'], $child['params'] ?? []) }}" @if ($childActive) aria-current="page" @endif class="flex min-h-11 items-center gap-2 rounded-md px-2.5 text-sm transition-colors {{ $childActive ? 'bg-primary-soft font-medium text-fg' : 'text-fg-muted hover:bg-surface-muted hover:text-fg' }}">
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
                                    <a href="{{ route($item['route'], $item['params'] ?? []) }}" @if ($active) aria-current="page" @endif class="relative flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors {{ $active ? 'bg-primary-soft text-fg before:absolute before:top-2.5 before:bottom-2.5 before:-left-3 before:w-[3px] before:rounded-r-full before:bg-primary' : 'text-fg-muted hover:bg-surface-muted hover:text-fg' }}">
                                        <x-icon :name="$item['icon'] ?? 'layers'" class="size-5 shrink-0 {{ $active ? 'text-primary' : 'text-fg-subtle' }}" />
                                        <span class="flex-1 truncate">{{ $item['label'] }}</span>
                                        @if (($item['route'] ?? null) === 'org.notifications.index' && $unreadNotifications > 0)
                                            <span class="rounded-full bg-primary px-1.5 text-[0.65rem] font-semibold text-primary-fg tabular-nums" aria-label="{{ $unreadNotifications }} unread">{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>
                                        @endif
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
            <a href="{{ route('app.profile') }}" class="flex min-h-11 items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-surface-muted {{ request()->routeIs('app.profile') ? 'bg-primary-soft' : '' }}">
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
    <div class="flex min-h-svh min-w-0 flex-col lg:pl-[17rem]">
        <header class="sticky top-0 z-20 flex h-16 items-center justify-between gap-3 border-b border-edge bg-surface/90 px-4 backdrop-blur sm:px-6 lg:px-8">
            <div class="flex min-w-0 items-center gap-3">
                <button type="button" class="inline-flex size-11 shrink-0 items-center justify-center rounded-lg text-fg-muted hover:bg-surface-muted lg:hidden" @click="sidebar = true" aria-controls="sidebar" :aria-expanded="sidebar.toString()" aria-label="Open navigation">
                    <x-icon name="menu" class="size-5" />
                </button>
                <div class="min-w-0">
                    @if ($organization)
                        <p class="truncate text-sm font-semibold text-fg lg:hidden">{{ $workspaceName }}</p>
                        <p class="hidden truncate text-sm text-fg-muted lg:block">{{ now($organization->timezone ?: 'UTC')->format('l j F Y') }}</p>
                    @elseif ($isPlatform)
                        <p class="truncate text-sm font-semibold text-fg">Platform console</p>
                    @endif
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                @if ($organization)
                    <div class="relative" x-data="{ notices: false }" @keydown.escape.window="notices = false">
                        <button
                            type="button"
                            @click="notices = !notices"
                            :aria-expanded="notices.toString()"
                            aria-haspopup="dialog"
                            aria-controls="notification-bell"
                            class="relative inline-flex size-11 items-center justify-center rounded-lg text-fg-muted hover:bg-surface-muted focus-visible:outline-2"
                            aria-label="{{ $unreadNotifications > 0 ? $unreadNotifications.' unread notifications' : 'Notifications, none unread' }}"
                        >
                            <x-icon name="bell" class="size-5" />
                            @if ($unreadNotifications > 0)
                                <span class="absolute -top-1 -right-1 min-w-5 rounded-full bg-primary px-1 text-center text-[0.65rem] font-semibold text-primary-fg tabular-nums">{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>
                            @endif
                        </button>
                        <div
                            id="notification-bell"
                            x-show="notices"
                            x-cloak
                            x-transition.origin.top.right
                            @click.outside="notices = false"
                            role="dialog"
                            aria-label="Recent notifications"
                            class="absolute right-0 z-30 mt-2 w-80 max-w-[calc(100vw-2rem)] rounded-xl border border-edge bg-surface shadow-lift"
                        >
                            <div class="flex items-center justify-between gap-3 border-b border-edge px-3 py-2">
                                <p class="text-sm font-semibold text-fg">Notifications</p>
                                <p class="text-xs text-fg-muted">{{ $unreadNotifications === 1 ? '1 unread' : $unreadNotifications.' unread' }}</p>
                            </div>
                            @if ($recentNotifications->isEmpty())
                                <p class="px-3 py-4 text-sm text-fg-muted">No notifications yet.</p>
                            @else
                                <ul class="max-h-96 divide-y divide-edge overflow-y-auto">
                                    @foreach ($recentNotifications as $notification)
                                        @php $unreadRow = $notification->read_at === null; @endphp
                                        <li>
                                            <form method="POST" action="{{ route('org.notifications.open', $notification->uuid) }}">
                                                @csrf
                                                <button type="submit" class="flex w-full flex-col gap-1 px-3 py-2 text-left hover:bg-surface-muted focus-visible:outline-2 {{ $unreadRow ? 'bg-accent-soft/40' : '' }}">
                                                    <span class="flex items-center gap-2">
                                                        <span class="min-w-0 flex-1 truncate text-sm {{ $unreadRow ? 'font-semibold text-fg' : 'font-medium text-fg-muted' }}">{{ $notification->data['title'] ?? 'Notice' }}</span>
                                                        <span class="shrink-0 text-[0.65rem] font-semibold tracking-wide uppercase {{ $unreadRow ? 'text-fg' : 'text-fg-subtle' }}">{{ $unreadRow ? 'Unread' : 'Read' }}</span>
                                                    </span>
                                                    <span class="line-clamp-2 text-xs text-fg-muted">{{ $notification->data['body'] ?? '' }}</span>
                                                    <time class="text-[0.65rem] text-fg-subtle" datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->timezone($organization->timezone ?: 'UTC')->format('D j M Y, H:i') }}</time>
                                                </button>
                                            </form>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            <div class="border-t border-edge px-3 py-2">
                                <a href="{{ route('org.notifications.index') }}" class="text-sm font-medium text-primary hover:underline focus-visible:outline-2">View all</a>
                            </div>
                        </div>
                    </div>
                @endif
                <x-theme-toggle label-class="hidden xl:inline" />
                <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
                    <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="menu" class="flex min-h-11 items-center gap-2 rounded-lg px-2 text-sm hover:bg-surface-muted">
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
                        <a href="{{ \App\Tenancy\Origin::centralUrl('/user-guide') }}" role="menuitem" class="flex items-center gap-2 rounded-md px-3 py-2 text-sm text-fg-muted hover:bg-surface-muted hover:text-fg"><x-icon name="book" class="size-4" /> User guide</a>
                        <div class="my-1 border-t border-edge"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" role="menuitem" class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm text-fg-muted hover:bg-surface-muted hover:text-fg"><x-icon name="logout" class="size-4" /> Sign out</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main id="main" class="min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <div class="mx-auto space-y-8 {{ $wide ? 'max-w-[90rem]' : 'max-w-6xl' }}">
                @if ($organization && ! $organization->isActive())
                    <x-alert type="warning" title="{{ $organization->status->label() }}">
                        @if ($organization->status === \App\Enums\OrganizationStatus::PendingVerification)
                            Your organisation becomes active once the responsible contact verifies their e-mail address.
                        @else
                            Some actions are unavailable while the organisation is {{ strtolower($organization->status->label()) }}. Contact support if you believe this is an error.
                        @endif
                    </x-alert>
                @endif
                <x-breadcrumbs :items="\App\Support\Breadcrumbs::trail()" />
                <x-flash />
                {{ $slot }}
            </div>
        </main>

        <footer class="border-t border-edge px-4 py-4 text-xs text-fg-subtle sm:px-6 lg:px-8">
            <div class="mx-auto flex max-w-7xl flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <p>&copy; {{ date('Y') }} {{ config('orbit.product_name') }} · {{ config('orbit.product_tagline') }}</p>
                <p class="flex items-center gap-3"><a href="{{ \App\Tenancy\Origin::centralUrl('/terms') }}" class="hover:text-fg">Terms</a><a href="{{ \App\Tenancy\Origin::centralUrl('/privacy') }}" class="hover:text-fg">Privacy</a><a href="{{ \App\Tenancy\Origin::centralUrl('/contact') }}" class="hover:text-fg">Support</a></p>
            </div>
        </footer>
    </div>
    @stack('scripts')
</body>
</html>
