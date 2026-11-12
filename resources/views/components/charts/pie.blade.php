{{-- Kept for existing callers. A share is drawn as a donut: one ring, total in the centre. --}}
@props(['segments', 'caption'])

<x-charts.donut :segments="$segments" :caption="$caption" />
