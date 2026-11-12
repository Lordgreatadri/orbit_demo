{{--
    Horizontal comparison bars. Better than columns when labels are words.
    rows: label, total, ink, optional note. Zero rows stay listed with an empty track.
--}}
@props(['rows', 'caption', 'share' => true, 'unit' => null])

@php
    $rows = collect($rows)->map(fn ($row) => $row + ['total' => (int) ($row['total'] ?? 0)]);
    $max = max(1, (int) $rows->max('total'));
    $sum = (int) $rows->sum('total');
@endphp

<ul class="space-y-3" aria-hidden="true">
    @foreach ($rows as $row)
        <li @class(['opacity-70' => $row['total'] === 0])>
            <div class="flex items-baseline justify-between gap-3 text-sm">
                <span class="min-w-0 truncate font-medium text-fg">{{ $row['label'] }}</span>
                <span class="shrink-0 tabular-nums text-fg-muted">
                    <span class="font-semibold text-fg">{{ number_format($row['total']) }}</span>@if ($unit)<span class="ml-1 text-xs">{{ $unit }}</span>@endif
                    @if ($share && $sum > 0)<span class="ml-1.5 text-xs text-fg-subtle">{{ (int) round($row['total'] / $sum * 100) }}%</span>@endif
                </span>
            </div>
            <div class="mt-1.5 meter h-2">
                <span style="width: {{ $row['total'] > 0 ? max(2, round($row['total'] / $max * 100, 2)) : 0 }}%; background: {{ $row['ink'] ?? 'var(--primary)' }}"></span>
            </div>
            @if (! empty($row['note']))
                <p class="mt-1 text-xs text-fg-subtle">{{ $row['note'] }}</p>
            @endif
        </li>
    @endforeach
</ul>

<div class="sr-only">
<table>
    <caption>{{ $caption }}</caption>
    <thead><tr><th>Category</th><th>Count</th></tr></thead>
    <tbody>
        @foreach ($rows as $row)
            <tr><td>{{ $row['label'] }}</td><td>{{ $row['total'] }}</td></tr>
        @endforeach
    </tbody>
</table>
</div>
