@props(['insights'])

@php
    $rows = collect($insights)->map(fn ($insight) => is_array($insight) ? $insight : $insight->toArray());
    $action = $rows->where('band', 'action')->values();
    $planning = $rows->where('band', '!=', 'action')->values();
@endphp

<section aria-labelledby="insights-heading" class="card p-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 id="insights-heading" class="text-base font-semibold text-fg">Calculated notes</h2>
            <p class="mt-1 max-w-3xl text-sm leading-relaxed text-fg-muted">These notes quote stored figures. They are not written by an external model, and they do not change any record.</p>
        </div>
        <x-badge>Calculated</x-badge>
    </div>

    @if ($rows->isEmpty())
        <p class="mt-4 text-sm text-fg-muted">No threshold was crossed for this period. That is a result, not missing data.</p>
    @else
        <div class="mt-5 grid gap-5 lg:grid-cols-2">
            @foreach (['Action required now' => $action, 'Planning attention' => $planning] as $heading => $group)
                <div>
                    <h3 class="text-sm font-semibold text-fg">{{ $heading }}</h3>
                    @if ($group->isEmpty())
                        <p class="mt-2 text-sm text-fg-muted">None in this group.</p>
                    @else
                        <ul class="mt-3 space-y-3">
                            @foreach ($group as $row)
                                <li class="rounded-xl border border-edge bg-surface-muted/40 p-4">
                                    <p class="text-xs font-semibold tracking-wide text-fg-muted uppercase">{{ $heading }}</p>
                                    <p class="mt-1 font-medium text-fg">{{ $row['title'] }}</p>
                                    <p class="mt-1 text-sm leading-relaxed text-fg-muted">{{ $row['body'] }}</p>
                                    <p class="mt-2 text-xs text-fg-subtle">Period {{ $row['period'] }}. Metric: {{ $row['metric'] }}.</p>
                                    @if (! empty($row['action_url']))
                                        <a href="{{ $row['action_url'] }}" class="mt-2 inline-flex text-sm font-medium text-primary hover:underline focus-visible:outline-2">{{ $row['action'] }}</a>
                                    @else
                                        <p class="mt-2 text-sm text-fg">{{ $row['action'] }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</section>
