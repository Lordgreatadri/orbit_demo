@props(['title' => null, 'description' => null])

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ? $title.' · '.config('app.name') : config('app.name').' — '.config('orbit.product_tagline') }}</title>
@if ($description)
    <meta name="description" content="{{ $description }}">
@endif
<meta name="application-name" content="{{ config('orbit.product_name') }}">
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#0f766e" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0b1d21" media="(prefers-color-scheme: dark)">

{{-- ORBIT brand icon in every browser tab, home-screen and task switcher. --}}
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/brand/icon-16.png') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/brand/icon-32.png') }}">
<link rel="icon" type="image/png" sizes="48x48" href="{{ asset('images/brand/icon-48.png') }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/brand/icon-192.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/brand/apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">

{{-- Apply the stored theme before first paint to avoid a flash of the wrong theme. --}}
<script>
    (function () {
        try {
            var t = window.localStorage.getItem('orbit-theme');
            if (t === 'plum') { document.documentElement.setAttribute('data-theme', 'plum'); }
        } catch (e) {}
    })();
</script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
