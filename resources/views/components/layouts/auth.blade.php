@props(['title', 'description' => null, 'wide' => false])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-layouts.partials.head :title="$title" />
</head>
<body class="min-h-svh">
    <div class="grid min-h-svh lg:grid-cols-12">
        {{-- Brand panel: fixed brand colour so the official lockup sits seamlessly in both themes. --}}
        <aside class="bg-brand-panel relative hidden flex-col justify-between overflow-hidden p-10 text-white lg:col-span-5 lg:flex xl:col-span-5" aria-label="About ORBIT">
            <div aria-hidden="true" class="absolute -right-32 -bottom-40 size-[36rem] rounded-full border border-white/10"></div>
            <div aria-hidden="true" class="absolute -right-16 -bottom-24 size-[26rem] rounded-full border border-white/10"></div>
            <div aria-hidden="true" class="absolute right-10 -bottom-6 size-[16rem] rounded-full border border-white/10"></div>

            @php $branding = $workspaceBranding ?? \App\Tenancy\WorkspaceBranding::platform(); @endphp
            <a href="{{ $branding->organization ? route('login') : route('home') }}" class="inline-flex rounded-lg focus-visible:outline-2 focus-visible:outline-white" aria-label="{{ $branding->displayName }}">
                @if ($branding->logoDarkUrl || $branding->logoUrl)
                    <img src="{{ $branding->logoDarkUrl ?? $branding->logoUrl }}" alt="{{ $branding->displayName }}" class="h-20 w-auto object-contain" decoding="async">
                @else
                    <img src="{{ asset('images/brand/orbit-lockup-dark.png') }}" alt="ORBIT — Track · Manage · Empower" width="736" height="278" class="h-20 w-auto" decoding="async">
                @endif
            </a>

            <div class="relative max-w-md">
                <p class="text-xs font-semibold tracking-[0.18em] text-brand-mint uppercase">{{ config('orbit.product_tagline') }}</p>
                <h2 class="mt-4 text-3xl font-semibold tracking-tight text-balance xl:text-4xl">{{ $branding->loginHeading === 'Sign in' ? 'One clear view of attendance, schedules, and where work happens.' : $branding->loginHeading }}</h2>
                <ul class="mt-8 space-y-4 text-[0.9375rem] text-white/80">
                    @foreach ([
                        ['qr', 'Time-limited QR sessions; static codes are never used.'],
                        ['map-pin', 'Location verified only on explicit check-in — no background tracking.'],
                        ['shield-check', 'Each organisation\'s records are kept separate by design.'],
                    ] as [$icon, $text])
                        <li class="flex items-start gap-3">
                            <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-white/10 text-brand-mint"><x-icon :name="$icon" class="size-4.5" /></span>
                            <span class="pt-1">{{ $text }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <p class="relative text-xs text-white/50">{{ $branding->footerText }}</p>
        </aside>

        {{-- Form column --}}
        <div class="relative isolate flex flex-col px-4 py-6 sm:px-8 lg:col-span-7">
            <div class="bg-mesh absolute inset-0 -z-10 opacity-60" aria-hidden="true"></div>

            <header class="flex items-center justify-between">
                <a href="{{ route('home') }}" class="rounded-lg focus-visible:outline-2" aria-label="{{ config('orbit.product_name') }} home"><x-logo /></a>
                <div class="flex items-center gap-2">
                    <x-button :href="route('home')" variant="ghost" size="sm" class="hidden sm:inline-flex">Back to site</x-button>
                    <x-theme-toggle />
                </div>
            </header>

            <main id="main" class="flex flex-1 items-center justify-center py-10">
                <div @class(['w-full', 'max-w-md' => ! $wide, 'max-w-2xl' => $wide])>
                    <div class="mb-7">
                        <h1 class="text-3xl font-semibold tracking-tight text-fg">{{ $title }}</h1>
                        @if ($description)
                            <p class="mt-2 text-[0.9375rem] text-fg-muted">{{ $description }}</p>
                        @endif
                    </div>
                    <div class="rounded-2xl border border-edge bg-surface p-6 shadow-lift sm:p-8">
                        {{ $slot }}
                    </div>
                    @isset($footer)
                        <div class="mt-5 text-center text-[0.9375rem] text-fg-muted">{{ $footer }}</div>
                    @endisset
                </div>
            </main>

            <footer class="flex flex-wrap justify-center gap-x-6 gap-y-2 text-sm text-fg-subtle">
                <a href="{{ route('terms') }}" class="hover:text-fg">Terms</a>
                <a href="{{ route('privacy') }}" class="hover:text-fg">Privacy</a>
                <a href="{{ route('contact') }}" class="hover:text-fg">Contact</a>
            </footer>
        </div>
    </div>
</body>
</html>
