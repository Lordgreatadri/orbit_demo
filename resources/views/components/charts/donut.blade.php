{{--
    Donut share chart. Same contract as before (segments: key, label, total, ink).
    The ring answers "what share", the centre states the total, and the legend
    lists every category, including stored zeros. Nothing in range is a sentence.
--}}
@props(['segments', 'caption', 'center' => null, 'centerLabel' => 'records', 'size' => '11rem', 'empty' => 'Nothing is stored for this range yet. Every category below is zero.', 'legendTitle' => null])

@php
    $rows = collect($segments)->map(fn ($segment) => $segment + ['total' => (int) ($segment['total'] ?? 0)]);
    $total = (int) $rows->sum('total');
    $positive = $rows->filter(fn ($segment) => $segment['total'] > 0)->values();
    $gap = $positive->count() > 1 ? 0.8 : 0;
    $arcs = [];
    $offset = 25;

    foreach ($positive as $segment) {
        $share = $total > 0 ? $segment['total'] / $total * 100 : 0;
        $length = max(0.01, $share - $gap);
        $arcs[] = $segment + ['dash' => sprintf('%.3F %.3F', $length, 100 - $length), 'offset' => sprintf('%.3F', $offset)];
        $offset -= $share;
    }
@endphp

<div class="@container">
<div class="flex flex-col items-center gap-5 @sm:flex-row @sm:items-center">
    <div class="relative shrink-0" style="width: {{ $size }}; height: {{ $size }}">
        <svg viewBox="0 0 42 42" class="size-full" role="img" aria-label="{{ $caption }}">
            <circle cx="21" cy="21" r="15.915" fill="none" stroke="var(--surface-muted)" stroke-width="5.5" />
            @foreach ($arcs as $arc)
                <circle cx="21" cy="21" r="15.915" fill="none" stroke="{{ $arc['ink'] }}" stroke-width="5.5" stroke-dasharray="{{ $arc['dash'] }}" stroke-dashoffset="{{ $arc['offset'] }}">
                    <title>{{ $arc['label'] }}: {{ number_format($arc['total']) }}</title>
                </circle>
            @endforeach
        </svg>
        <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center" aria-hidden="true">
            <span class="text-2xl leading-none font-semibold tracking-tight tabular-nums text-fg">{{ $center ?? number_format($total) }}</span>
            <span class="mt-1 max-w-[6.5rem] text-[0.6875rem] leading-tight text-fg-subtle">{{ $centerLabel }}</span>
        </div>
    </div>
    <div class="w-full min-w-0">
        @if ($total === 0)
            <p class="mb-3 text-sm text-fg-muted">{{ $empty }}</p>
        @endif
        @if ($legendTitle)
            <h3 class="mb-1.5 px-1.5 text-xs font-medium text-fg-muted">{{ $legendTitle }}</h3>
        @endif
        <ul class="space-y-0.5 text-sm">
            @foreach ($rows as $segment)
                <li @class(['flex items-center justify-between gap-3 rounded-md px-1.5 py-0.5', 'opacity-60' => $segment['total'] === 0])>
                    <span class="inline-flex min-w-0 items-center gap-2">
                        <span class="size-2.5 shrink-0 rounded-full" style="background: {{ $segment['ink'] }}" aria-hidden="true"></span>
                        <span class="truncate text-fg">{{ $segment['label'] }}</span>
                    </span>
                    <span class="shrink-0 tabular-nums text-fg-muted"><span class="font-medium text-fg">{{ number_format($segment['total']) }}</span>@if ($total > 0)<span class="ml-1.5 text-xs text-fg-subtle">{{ (int) round($segment['total'] / $total * 100) }}%</span>@endif</span>
                </li>
            @endforeach
        </ul>
    </div>
</div>
</div>

<div class="sr-only">
<table>
    <caption>{{ $caption }}</caption>
    <thead><tr><th>Category</th><th>Count</th></tr></thead>
    <tbody>
        @foreach ($rows as $segment)
            <tr><td>{{ $segment['label'] }}</td><td>{{ $segment['total'] }}</td></tr>
        @endforeach
    </tbody>
</table>
</div>
