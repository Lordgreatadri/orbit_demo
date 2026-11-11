@props(['segments', 'caption'])

@php
    $positive = collect($segments)->filter(fn ($segment) => (int) $segment['total'] > 0)->values();
    $total = (int) $positive->sum('total');
    $paths = [];

    if ($total > 0 && $positive->count() === 1) {
        $only = $positive->first();
        $paths[] = [
            'circle' => true,
            'ink' => $only['ink'],
            'label' => $only['label'],
            'total' => $only['total'],
        ];
    } elseif ($total > 0) {
        $start = -M_PI / 2;
        $cx = 21;
        $cy = 21;
        $radius = 16;

        foreach ($positive as $segment) {
            $sweep = ($segment['total'] / $total) * 2 * M_PI;
            $end = $start + $sweep;
            $large = $sweep > M_PI ? 1 : 0;
            $paths[] = [
                'circle' => false,
                'ink' => $segment['ink'],
                'label' => $segment['label'],
                'total' => $segment['total'],
                'd' => sprintf(
                    'M %.3F %.3F L %.3F %.3F A %.3F %.3F 0 %d 1 %.3F %.3F Z',
                    $cx,
                    $cy,
                    $cx + ($radius * cos($start)),
                    $cy + ($radius * sin($start)),
                    $radius,
                    $radius,
                    $large,
                    $cx + ($radius * cos($end)),
                    $cy + ($radius * sin($end)),
                ),
            ];
            $start = $end;
        }
    }
@endphp

@if ($total === 0)
    <x-empty-state title="Nothing to chart" description="Every category below is zero for this range. The chart appears when a record is stored." />
    <ul class="mt-4 space-y-2 text-sm">
        @foreach ($segments as $segment)
            <li class="flex items-center justify-between gap-3">
                <span class="inline-flex min-w-0 items-center gap-2">
                    <span class="size-2.5 shrink-0 rounded-sm" style="background: {{ $segment['ink'] }}" aria-hidden="true"></span>
                    <span class="truncate text-fg">{{ $segment['label'] }}</span>
                </span>
                <span class="shrink-0 tabular-nums text-fg-muted">0</span>
            </li>
        @endforeach
    </ul>
@else
    <div class="flex flex-col items-center gap-6 sm:flex-row sm:items-start">
        <svg viewBox="0 0 42 42" class="shrink-0" style="width: 13rem; height: 13rem" role="img" aria-label="{{ $caption }}">
            @foreach ($paths as $path)
                @if ($path['circle'])
                    <circle cx="21" cy="21" r="16" style="fill: {{ $path['ink'] }}"><title>{{ $path['label'] }}: {{ number_format($path['total']) }}</title></circle>
                @else
                    <path d="{{ $path['d'] }}" style="fill: {{ $path['ink'] }}; stroke: var(--surface)" stroke-width="0.35"><title>{{ $path['label'] }}: {{ number_format($path['total']) }}</title></path>
                @endif
            @endforeach
        </svg>
        <ul class="w-full min-w-0 space-y-2 text-sm">
            @foreach ($segments as $segment)
                <li class="flex items-center justify-between gap-3">
                    <span class="inline-flex min-w-0 items-center gap-2">
                        <span class="size-2.5 shrink-0 rounded-sm" style="background: {{ $segment['ink'] }}" aria-hidden="true"></span>
                        <span class="truncate text-fg">{{ $segment['label'] }}</span>
                    </span>
                    <span class="shrink-0 tabular-nums text-fg-muted">{{ number_format($segment['total']) }} · {{ (int) round($segment['total'] / $total * 100) }}%</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif

<div class="sr-only">
<table>
    <caption>{{ $caption }}</caption>
    <thead><tr><th>Category</th><th>Count</th></tr></thead>
    <tbody>
        @foreach ($segments as $segment)
            <tr><td>{{ $segment['label'] }}</td><td>{{ $segment['total'] }}</td></tr>
        @endforeach
    </tbody>
</table>
</div>
