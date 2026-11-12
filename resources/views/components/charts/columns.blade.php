@props(['rows', 'caption', 'height' => '10rem', 'highlightLast' => false, 'columnWidth' => 3.25])

@php
    $max = max(1, (int) collect($rows)->max('total'));
    $width = max(12, count($rows) * (float) $columnWidth);
    $lastIndex = count($rows) - 1;
@endphp

<div class="w-full min-w-0 overflow-x-auto">
    <div style="min-width: {{ $width }}rem">
        <div @class(['plot-grid flex items-end border-b border-edge-strong/70', 'gap-3' => $columnWidth >= 3, 'gap-1.5' => $columnWidth < 3]) style="height: {{ $height }}" role="img" aria-label="{{ $caption }}">
            @foreach ($rows as $row)
                @php $height = $row['total'] > 0 ? max(4, (int) round($row['total'] / $max * 100)) : 0; @endphp
                <div class="flex min-w-0 flex-1 flex-col justify-end" style="height: 100%" title="{{ $row['label'] }}: {{ number_format($row['total']) }}">
                    <span @class(['mb-1 text-center text-xs tabular-nums', 'font-semibold text-fg' => $row['total'] > 0, 'text-fg-subtle' => $row['total'] === 0])>{{ number_format($row['total']) }}</span>
                    @if ($row['total'] > 0)
                        <div @class(['mx-auto w-full max-w-9 rounded-t-md', 'opacity-60' => $highlightLast && $loop->index !== $lastIndex]) style="height: {{ $height }}%; background: {{ $row['ink'] }}"></div>
                    @else
                        <div class="mx-auto h-[3px] w-full max-w-9 rounded-full bg-edge"></div>
                    @endif
                </div>
            @endforeach
        </div>
        <div @class(['mt-2 flex', 'gap-3' => $columnWidth >= 3, 'gap-1.5' => $columnWidth < 3]) aria-hidden="true">
            @foreach ($rows as $row)
                <span class="min-w-0 flex-1 text-center text-[0.6875rem] leading-tight text-fg-subtle">{{ $row['label'] }}</span>
            @endforeach
        </div>
    </div>
</div>

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
