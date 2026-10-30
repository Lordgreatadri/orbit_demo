<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('orbit.product_name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ config('orbit.product_name') }} — {{ config('orbit.product_tagline') }}. {{ __('All rights reserved.') }}<br>
{{ __('This message was sent by :app. If you have questions, contact :email.', ['app' => config('orbit.product_name'), 'email' => config('orbit.support_email')]) }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
