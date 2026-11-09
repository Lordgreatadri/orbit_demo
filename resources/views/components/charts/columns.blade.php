@props(['rows', 'caption'])

@php
    $max = max(1, (int) collect($rows)->max('total'));
    $width = max(36, count($rows) * 4.5);
@endphp

<div class="overflow-x-auto">
    <div style="min-width: {{ $width }}rem">
        <div class="flex items-end gap-2 border-b border-edge" style="height: 14rem" role="img" aria-label="{{ $caption }}">
            @foreach ($rows as $row)
                @php $height = $row['total'] > 0 ? max(4, (int) round($row['total'] / $max * 100)) : 0; @endphp
                <div class="flex min-w-0 flex-1 flex-col justify-end" style="height: 100%" title="{{ $row['label'] }}: {{ number_format($row['total']) }}">
                    <span class="mb-1 text-center text-[0.65rem] tabular-nums text-fg-muted">{{ number_format($row['total']) }}</span>
                    <div class="mx-auto w-full max-w-10 rounded-t-md" style="height: {{ $height }}%; background: {{ $row['ink'] }}"></div>
                </div>
            @endforeach
        </div>
        <div class="mt-2 flex gap-2" aria-hidden="true">
            @foreach ($rows as $row)
                <span class="min-w-0 flex-1 text-center text-[0.65rem] leading-tight text-fg-subtle">{{ $row['label'] }}</span>
            @endforeach
        </div>
    </div>
</div>

<table class="sr-only">
    <caption>{{ $caption }}</caption>
    <thead><tr><th>Category</th><th>Count</th></tr></thead>
    <tbody>
        @foreach ($rows as $row)
            <tr><td>{{ $row['label'] }}</td><td>{{ $row['total'] }}</td></tr>
        @endforeach
    </tbody>
</table>
