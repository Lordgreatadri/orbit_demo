<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $result->definition->title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .meta { color: #555; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; font-weight: 600; }
        .kpis { margin-bottom: 14px; }
        .kpi { display: inline-block; margin-right: 18px; margin-bottom: 8px; }
        .kpi strong { display: block; font-size: 14px; }
    </style>
</head>
<body>
    <h1>{{ $result->definition->title }}</h1>
    <p class="meta">
        {{ $result->from }} – {{ $result->to }}
        · {{ $result->scope->label() }}
        · Organisation timezone: {{ $result->scope->organization->timezone ?: 'UTC' }}
    </p>

    @if ($result->kpis !== [])
        <div class="kpis">
            @foreach ($result->kpis as $kpi)
                <div class="kpi">
                    <span>{{ $kpi->label }}</span>
                    <strong>{{ $kpi->value }}</strong>
                </div>
            @endforeach
        </div>
    @endif

    <table>
        <thead>
            <tr>
                @foreach ($result->columns as $column)
                    <th>{{ $column->label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($result->columns as $column)
                        <td>{{ $row[$column->key] ?? '' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($result->columns) }}">{{ $result->emptyMessage }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    @if (! empty($truncated))
        <p class="meta">PDF limited to the first {{ count($rows) }} rows. Use CSV or Excel for the full export.</p>
    @endif
</body>
</html>
