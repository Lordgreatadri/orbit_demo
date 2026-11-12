@props(['rows', 'series', 'caption', 'height' => '11rem', 'totals' => true])

@php
    $max = max(1, (int) collect($rows)->max(fn ($row) => collect($series)->sum(fn ($status) => (int) ($row['counts'][$status['key']] ?? $row[$status['key']] ?? 0))));
    $count = count($rows);
    $chartMin = $count * 2.25;
    $stack = array_reverse($series);
    $lastIndex = $count - 1;
    $seriesTotals = collect($series)->mapWithKeys(fn ($status) => [
        $status['key'] => (int) collect($rows)->sum(fn ($row) => (int) ($row['counts'][$status['key']] ?? $row[$status['key']] ?? 0)),
    ]);
@endphp

<div class="flex w-full min-w-0 gap-2.5">
    <div class="flex shrink-0 flex-col justify-between pb-6 text-right text-[0.6875rem] tabular-nums text-fg-subtle" style="height: calc({{ $height }} + 1.5rem)" aria-hidden="true">
        <span class="-mt-1.5">{{ number_format($max) }}</span>
        <span>{{ $max > 1 ? number_format($max / 2, $max % 2 === 0 ? 0 : 1) : '' }}</span>
        <span class="-mb-1.5">0</span>
    </div>
    <div class="min-w-0 flex-1 overflow-x-auto">
        <div class="plot-grid flex items-end gap-1 border-b border-edge-strong/70" style="height: {{ $height }}; min-width: {{ $chartMin }}rem" role="img" aria-label="{{ $caption }}">
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
                <div class="group flex min-w-[2rem] flex-1 items-end justify-center" style="height: 100%" title="{{ $segments === [] ? ($row['date'] ?? '').': no records' : $title }}">
                    <div class="flex w-full max-w-7 flex-col justify-end px-0.5 sm:px-1" style="height: 100%">
                        @if ($segments === [])
                            <div class="h-[3px] w-full rounded-full bg-edge"></div>
                        @else
                            @foreach ($segments as $segment)
                                <div @class(['transition-opacity group-hover:opacity-85', 'rounded-t-[3px]' => $loop->first]) style="height: {{ $segment['height'] }}%; background: {{ $segment['ink'] }}"></div>
                            @endforeach
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-1.5 flex gap-1 text-[0.6875rem] tabular-nums text-fg-subtle" style="min-width: {{ $chartMin }}rem" aria-hidden="true">
            @foreach ($rows as $row)
                <span @class(['min-w-[2rem] flex-1 whitespace-nowrap text-center', 'font-semibold text-fg' => $loop->index === $lastIndex])>{{ \Illuminate\Support\Carbon::parse($row['date'])->format('j/n') }}</span>
            @endforeach
        </div>
    </div>
</div>
<ul class="mt-4 flex flex-wrap gap-x-5 gap-y-1.5 text-xs text-fg-muted">
    @foreach ($series as $status)
        <li class="inline-flex items-center gap-1.5">
            <span class="size-2.5 rounded-full" style="background: {{ $status['ink'] }}" aria-hidden="true"></span>
            {{ $status['label'] }}
            @if ($totals)
                <span class="font-semibold tabular-nums text-fg">{{ number_format($seriesTotals[$status['key']] ?? 0) }}</span>
            @endif
        </li>
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
