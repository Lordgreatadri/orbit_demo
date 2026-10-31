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
    @if ($paginator)
        <div class="flex flex-col gap-3 border-t border-edge px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-fg-subtle">
                {{ __('Showing :from–:to of :total', [
                    'from' => $paginator->total() === 0 ? 0 : $paginator->firstItem(),
                    'to' => $paginator->lastItem() ?? 0,
                    'total' => $paginator->total(),
                ]) }}
            </p>
            @if ($paginator->hasPages())
                <div>{{ $paginator->links() }}</div>
            @endif
        </div>
    @endif
</div>
