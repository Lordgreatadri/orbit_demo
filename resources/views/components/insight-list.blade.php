@props(['insights'])

@php
    $rows = collect($insights)->map(fn ($insight) => is_array($insight) ? $insight : $insight->toArray());
    $action = $rows->where('band', 'action')->values();
    $planning = $rows->where('band', '!=', 'action')->values();
@endphp

{{--
    Operational intelligence. Each note is a fixed threshold rule that quotes
    stored figures (ADR-0016). It is labelled Calculated, never AI-generated.
--}}
<section aria-labelledby="insights-heading" class="briefing overflow-hidden">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-edge px-5 py-4 sm:px-6">
        <div class="flex min-w-0 items-start gap-3">
            <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-primary text-primary-fg" aria-hidden="true"><x-icon name="sparkles" class="size-4.5" /></span>
            <div class="min-w-0">
                <h2 id="insights-heading" class="text-base font-semibold tracking-tight text-fg">Operational intelligence</h2>
                <p class="mt-0.5 max-w-3xl text-sm leading-relaxed text-fg-muted">Calculated notes. Fixed rules read the stored figures on this page and quote them. They are not written by an external model and they do not change any record.</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            @if ($action->isNotEmpty())
                <x-badge tone="danger">{{ $action->count() }} action</x-badge>
            @endif
            @if ($planning->isNotEmpty())
                <x-badge tone="info">{{ $planning->count() }} planning</x-badge>
            @endif
            <x-badge>Calculated</x-badge>
        </div>
    </div>

    @if ($rows->isEmpty())
        <div class="flex items-center gap-3 px-5 py-5 sm:px-6">
            <span class="icon-well tone-success" aria-hidden="true"><x-icon name="check-circle" class="size-4" /></span>
            <p class="text-sm text-fg-muted">No threshold was crossed for this period. That is a result, not missing data.</p>
        </div>
    @else
        <div class="grid lg:grid-cols-2 lg:divide-x lg:divide-edge">
            @foreach (['Action required now' => [$action, 'danger', 'alert'], 'Planning attention' => [$planning, 'info', 'calendar']] as $heading => [$group, $tone, $icon])
                <div class="px-5 py-4 sm:px-6 {{ $loop->first ? '' : 'border-t border-edge lg:border-t-0' }}">
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-fg">
                        <x-icon :name="$icon" class="size-4 {{ $tone === 'danger' ? 'text-danger' : 'text-info' }}" />
                        {{ $heading }}
                    </h3>
                    @if ($group->isEmpty())
                        <p class="mt-2 text-sm text-fg-muted">None in this group.</p>
                    @else
                        <ul class="mt-3 space-y-4">
                            @foreach ($group as $row)
                                <li class="rail rail-{{ $tone }}">
                                    <p class="font-medium leading-snug text-fg">{{ $row['title'] }}</p>
                                    <p class="mt-1 text-sm leading-relaxed text-fg-muted">{{ $row['body'] }}</p>
                                    <p class="mt-1.5 flex flex-wrap gap-x-3 gap-y-1 text-xs text-fg-subtle">
                                        <span><span class="font-medium text-fg-muted">Period</span> {{ $row['period'] }}</span>
                                        <span><span class="font-medium text-fg-muted">Metric</span> {{ $row['metric'] }}</span>
                                    </p>
                                    @if (! empty($row['action_url']))
                                        <a href="{{ $row['action_url'] }}" class="mt-1 inline-flex min-h-11 items-center gap-1.5 text-sm font-medium text-primary hover:underline focus-visible:outline-2">{{ $row['action'] }} <x-icon name="arrow-right" class="size-3.5" /></a>
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
