@props(['rows', 'series', 'caption'])

@php
    $max = max(1, (int) collect($rows)->max(fn ($row) => (int) ($row['present'] ?? 0) + (int) ($row['late'] ?? 0) + (int) ($row['on_leave'] ?? 0) + (int) ($row['other'] ?? 0)));
    $count = count($rows);
    $chartMin = $count * 2.75;
    $stack = array_reverse($series);
@endphp

<div class="flex w-full min-w-0 gap-3">
    <div class="flex shrink-0 flex-col justify-between pb-7 text-right text-xs tabular-nums text-fg-muted" style="height: 12rem" aria-hidden="true">
        <span>{{ number_format($max) }}</span>
        <span>{{ $max > 2 ? number_format((int) round($max / 2)) : '' }}</span>
        <span>0</span>
    </div>
    <div class="min-w-0 flex-1 overflow-x-auto">
        <div class="flex items-end gap-1 border-b border-edge" style="height: 12rem; min-width: {{ $chartMin }}rem" role="img" aria-label="{{ $caption }}">
            @foreach ($rows as $row)
                @php
                    $segments = [];
                    foreach ($stack as $status) {
                        $value = (int) ($row['counts'][$status['key']] ?? $row[$status['key']] ?? 0);
                        if ($value > 0) {
                            $segments[] = ['height' => ($value / $max) * 100, 'ink' => $status['ink'], 'label' => $status['label'], 'count' => $value];
                        }
                    }
                    $title = ($row['date'] ?? '').': '.collect($segments)->map(fn ($segment) => $segment['count'].' '.$segment['label'])->join(', ');
                @endphp
                <div class="flex min-w-[2.75rem] flex-1 items-end justify-center" style="height: 100%" title="{{ $segments === [] ? ($row['date'] ?? '').': no records' : $title }}">
                    <div class="flex w-3 flex-col justify-end sm:w-5" style="height: 100%">
                        @if ($segments === [])
                            <div class="h-0.5 w-full rounded-full bg-edge"></div>
                        @else
                            @foreach ($segments as $segment)
                                <div @class(['rounded-t-sm' => $loop->first]) style="height: {{ $segment['height'] }}%; background: {{ $segment['ink'] }}"></div>
                            @endforeach
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-2 flex gap-1 text-[0.7rem] tabular-nums text-fg-muted" style="min-width: {{ $chartMin }}rem" aria-hidden="true">
            @foreach ($rows as $row)
                <span class="min-w-[2.75rem] flex-1 whitespace-nowrap text-center">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('j/n') }}</span>
            @endforeach
        </div>
    </div>
</div>
<ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-fg-muted">
    @foreach ($series as $status)
        <li class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm" style="background: {{ $status['ink'] }}" aria-hidden="true"></span>{{ $status['label'] }}</li>
    @endforeach
</ul>

<div class="sr-only">
<table>
    <caption>{{ $caption }}</caption>
    <thead>
        <tr>
            <th>Date</th>
            @foreach ($series as $status)
                <th>{{ $status['label'] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['date'] }}</td>
                @foreach ($series as $status)
                    <td>{{ $row['counts'][$status['key']] ?? $row[$status['key']] ?? 0 }}</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
</div>
