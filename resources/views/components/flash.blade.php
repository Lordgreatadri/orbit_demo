@if (session('status'))
    <x-alert type="success" dismissible {{ $attributes }}>{{ session('status') }}</x-alert>
@endif
@if (session('error'))
    <x-alert type="danger" dismissible {{ $attributes }}>{{ session('error') }}</x-alert>
@endif
@if (session('warning'))
    <x-alert type="warning" dismissible {{ $attributes }}>{{ session('warning') }}</x-alert>
@endif
