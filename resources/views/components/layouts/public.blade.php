@props(['title' => null, 'description' => null])

@php
    $primaryNav = [
        'home' => 'Home',
        'about' => 'How it works',
        'guide' => 'User guide',
        'contact' => 'Contact',
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-layouts.partials.head :title="$title" :description="$description" />
</head>
<body class="flex min-h-svh flex-col">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-primary focus:px-4 focus:py-2 focus:text-primary-fg">Skip to content</a>

    <header
        class="sticky top-0 z-40 border-b border-edge/80 bg-surface/85 backdrop-blur-md supports-[backdrop-filter]:bg-surface/70"
        x-data="{ open: false, scrolled: false }"
        @scroll.window.passive="scrolled = window.scrollY > 8"
        x-effect="document.documentElement.classList.toggle('overflow-hidden', open)"
        :class="scrolled && 'shadow-[0_1px_0_0_var(--edge),0_8px_24px_-16px_rgb(0_0_0/0.25)]'"
    >
        <div class="mx-auto flex h-[4.5rem] max-w-7xl items-center gap-2 px-4 sm:gap-3 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="shrink-0 rounded-lg focus-visible:outline-2" aria-label="{{ config('orbit.product_name') }} home">
                <x-logo />
            </a>

            {{-- Inline links from large tablets / laptops upward. Portrait tablets use the menu. --}}
            <nav aria-label="Primary" class="hidden min-w-0 flex-1 justify-center lg:flex">
                <div class="flex max-w-full items-center gap-0.5 overflow-x-auto rounded-full border border-edge/70 bg-surface-muted/60 p-1">
                    @foreach ($primaryNav as $routeName => $label)
                        <a
                            href="{{ route($routeName) }}"
                            @if (request()->routeIs($routeName)) aria-current="page" @endif
                            class="shrink-0 rounded-full px-2.5 py-1.5 text-sm font-medium whitespace-nowrap transition-colors xl:px-4 xl:text-[0.9375rem] {{ request()->routeIs($routeName) ? 'bg-surface text-fg shadow-sm' : 'text-fg-muted hover:text-fg' }}"
                        >{{ $label }}</a>
                    @endforeach
                </div>
            </nav>

            <div class="ml-auto flex shrink-0 items-center gap-1.5 sm:gap-2">
                <x-theme-toggle label-class="hidden xl:inline" />
                <div class="hidden items-center gap-2 lg:flex">
                    @auth
                        <x-button :href="route('app.dashboard')" variant="secondary" size="sm">Dashboard</x-button>
                    @else
                        <x-button :href="route('login')" variant="ghost" size="sm">Sign in</x-button>
                        <x-button :href="route('register')" size="sm">
                            <span class="xl:hidden">Register</span>
                            <span class="hidden xl:inline">Register your organisation</span>
                            <x-icon name="arrow-right" class="size-4" />
                        </x-button>
                    @endauth
                </div>
                <button
                    type="button"
                    class="inline-flex size-10 items-center justify-center rounded-lg border border-edge text-fg-muted hover:bg-surface-muted hover:text-fg lg:hidden"
                    @click="open = !open"
                    :aria-expanded="open.toString()"
                    aria-controls="mobile-nav"
                    aria-label="Toggle navigation"
                >
                    <svg x-show="!open" aria-hidden="true" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    <svg x-show="open" x-cloak aria-hidden="true" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>
        </div>

        <nav id="mobile-nav" aria-label="Mobile" x-show="open" x-cloak x-transition.opacity @keydown.escape.window="open = false" class="border-t border-edge bg-surface px-4 py-4 shadow-float lg:hidden">
            <ul class="space-y-1">
                @foreach ($primaryNav as $routeName => $label)
                    <li>
                        <a href="{{ route($routeName) }}" @if (request()->routeIs($routeName)) aria-current="page" @endif class="block rounded-lg px-3 py-2.5 text-base font-medium {{ request()->routeIs($routeName) ? 'bg-primary-soft text-primary' : 'text-fg-muted hover:bg-surface-muted hover:text-fg' }}">{{ $label }}</a>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4 flex flex-col gap-2 border-t border-edge pt-4">
                @auth
                    <x-button :href="route('app.dashboard')" variant="secondary">Open dashboard</x-button>
                @else
                    <x-button :href="route('login')" variant="secondary">Sign in</x-button>
                    <x-button :href="route('register')">Register your organisation</x-button>
                @endauth
            </div>
        </nav>
    </header>

    <main id="main" class="flex-1">
        {{ $slot }}
    </main>

    <footer class="relative border-t border-edge bg-surface">
        <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
            <div class="grid gap-12 lg:grid-cols-12">
                <div class="lg:col-span-5">
                    <x-logo size="lg" tagline />
                    <p class="mt-5 max-w-sm text-[0.9375rem] leading-relaxed text-fg-muted">{{ config('orbit.product_tagline') }}. Attendance, workforce scheduling, and operational visibility for institutions — real people, real locations, real time.</p>
                    <p class="mt-5 inline-flex items-center gap-2 rounded-full border border-edge bg-surface-muted/60 px-3 py-1 text-xs font-medium text-fg-muted">
                        <x-icon name="shield-check" class="size-3.5 text-primary" />
                        Organisation data kept separate by design
                    </p>
                </div>

                <nav aria-label="Footer" class="grid grid-cols-2 gap-8 sm:grid-cols-3 lg:col-span-7">
                    <div>
                        <p class="text-xs font-semibold tracking-[0.14em] text-fg uppercase">Platform</p>
                        <ul class="mt-4 space-y-2.5 text-[0.9375rem]">
                            <li><a href="{{ route('about') }}" class="text-fg-muted transition-colors hover:text-primary">How it works</a></li>
                            <li><a href="{{ route('guide') }}" class="text-fg-muted transition-colors hover:text-primary">User guide</a></li>
                            <li><a href="{{ route('register') }}" class="text-fg-muted transition-colors hover:text-primary">Register your organisation</a></li>
                            <li><a href="{{ route('login') }}" class="text-fg-muted transition-colors hover:text-primary">Sign in</a></li>
                        </ul>
                    </div>
                    <div>
                        <p class="text-xs font-semibold tracking-[0.14em] text-fg uppercase">Support</p>
                        <ul class="mt-4 space-y-2.5 text-[0.9375rem]">
                            <li><a href="{{ route('contact') }}" class="text-fg-muted transition-colors hover:text-primary">Contact</a></li>
                            <li><a href="{{ route('contact') }}" class="text-fg-muted transition-colors hover:text-primary">Request a demo</a></li>
                            <li><a href="mailto:{{ config('orbit.support_email') }}" class="text-fg-muted transition-colors hover:text-primary">{{ config('orbit.support_email') }}</a></li>
                        </ul>
                    </div>
                    <div>
                        <p class="text-xs font-semibold tracking-[0.14em] text-fg uppercase">Legal</p>
                        <ul class="mt-4 space-y-2.5 text-[0.9375rem]">
                            <li><a href="{{ route('terms') }}" class="text-fg-muted transition-colors hover:text-primary">Terms and Conditions</a></li>
                            <li><a href="{{ route('privacy') }}" class="text-fg-muted transition-colors hover:text-primary">Privacy Policy</a></li>
                        </ul>
                    </div>
                </nav>
            </div>
        </div>
        <div class="border-t border-edge">
            <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-5 text-sm text-fg-subtle sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                <p>&copy; {{ date('Y') }} {{ config('orbit.product_name') }}. All rights reserved.</p>
                <p>Online Real-Time Based Identification and Tracking System</p>
            </div>
        </div>
    </footer>
</body>
</html>
