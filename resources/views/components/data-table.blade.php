{{-- Horizontal-scrolling table shell with optional pagination footer. --}}
@props(['paginator' => null, 'caption' => null])

<div {{ $attributes->merge(['class' => 'card overflow-hidden']) }}>
    <div class="overflow-x-auto">
        <table class="data-table">
            @if ($caption)
                <caption class="sr-only">{{ $caption }}</caption>
            @endif
            {{ $slot }}
        </table>
    </div>
    @if ($paginator && $paginator->hasPages())
        <div class="border-t border-edge px-4 py-3">{{ $paginator->links() }}</div>
    @endif
</div>
