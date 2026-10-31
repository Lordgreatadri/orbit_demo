@props(['title' => null, 'description' => null])

@php
    $branding = $workspaceBranding ?? \App\Tenancy\WorkspaceBranding::platform();
    $product = $branding->displayName;
@endphp

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ? $title.' · '.$product : $product.' — '.config('orbit.product_tagline') }}</title>
@if ($description)
    <meta name="description" content="{{ $description }}">
@endif
<meta name="application-name" content="{{ $product }}">
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="{{ $branding->primary }}" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0b1d21" media="(prefers-color-scheme: dark)">

@if ($branding->faviconUrl)
    <link rel="icon" href="{{ $branding->faviconUrl }}">
@else
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/brand/icon-16.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/brand/icon-32.png') }}">
    <link rel="icon" type="image/png" sizes="48x48" href="{{ asset('images/brand/icon-48.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/brand/icon-192.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/brand/apple-touch-icon.png') }}">
@endif
<link rel="manifest" href="{{ asset('site.webmanifest') }}">

{{-- Apply the stored theme before first paint to avoid a flash of the wrong theme. --}}
<script>
    (function () {
        try {
            var locked = @json($branding->themeMode->value);
            document.documentElement.dataset.themeLock = locked;
            if (locked === 'plum') { document.documentElement.setAttribute('data-theme', 'plum'); return; }
            if (locked === 'light') { document.documentElement.removeAttribute('data-theme'); return; }
            var t = window.localStorage.getItem('orbit-theme');
            if (t === 'plum') { document.documentElement.setAttribute('data-theme', 'plum'); }
        } catch (e) {}
    })();
</script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
{{-- After Vite so tenant tokens override the unlayered :root / [data-theme] rules. --}}
<style>:root, [data-theme='plum'] { {!! $branding->cssVariables() !!} }</style>
