{{-- Small inline form with a native confirm() guard, used for destructive row actions. --}}
@props(['action', 'method' => 'POST', 'confirm' => 'Are you sure?'])

<form method="POST" action="{{ $action }}" onsubmit="return confirm(@js($confirm));" {{ $attributes->merge(['class' => 'inline']) }}>
    @csrf
    @if (strtoupper($method) !== 'POST')
        @method($method)
    @endif
    {{ $slot }}
</form>
