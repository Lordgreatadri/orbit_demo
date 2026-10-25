@props(['code', 'title', 'message'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-layouts.partials.head :title="$code.' '.$title" />
</head>
<body class="min-h-svh">
    <div class="relative isolate mx-auto flex min-h-svh max-w-2xl flex-col px-4 py-6 sm:px-6">
        <div class="bg-mesh absolute inset-0 -z-10" aria-hidden="true"></div>
        <div class="bg-grid absolute inset-0 -z-10" aria-hidden="true"></div>

        <header class="flex items-center justify-between">
            <a href="{{ url('/') }}" class="rounded-lg focus-visible:outline-2" aria-label="{{ config('orbit.product_name') }} home"><x-logo /></a>
            <x-theme-toggle />
        </header>
        <main id="main" class="flex flex-1 flex-col items-center justify-center py-16 text-center">
            <p class="text-gradient-brand text-7xl font-bold tracking-tight tabular-nums sm:text-8xl" aria-hidden="true">{{ $code }}</p>
            <p class="sr-only">Error {{ $code }}</p>
            <h1 class="mt-4 text-3xl font-semibold tracking-tight text-fg sm:text-4xl">{{ $title }}</h1>
            <p class="mt-4 max-w-md text-lg text-fg-muted">{{ $message }}</p>
            <div class="mt-10 flex flex-wrap justify-center gap-3">
                <x-button href="{{ url('/') }}" size="lg">Go to home page</x-button>
                <x-button href="{{ url('/contact') }}" variant="secondary" size="lg">Contact support</x-button>
            </div>
        </main>
    </div>
</body>
</html>
