<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>{{ $payload['title'] ?? 'Report' }}</title>
        <style>
            body { font-family: DejaVu Sans, Arial, sans-serif; color: #0f172a; font-size: 12px; }
            h1 { font-size: 20px; margin-bottom: 6px; }
            h2 { font-size: 14px; margin: 20px 0 8px; }
            .meta { color: #475569; margin-bottom: 12px; }
            .kpi-grid { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
            .kpi-grid td { border: 1px solid #e2e8f0; padding: 8px; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #e2e8f0; padding: 6px 8px; text-align: left; }
            th { background: #f1f5f9; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; }
            .muted { color: #64748b; }
        </style>
    </head>
    <body>
        <h1>{{ $payload['title'] ?? 'Report' }}</h1>
        <div class="meta">Generated at {{ $payload['meta']['generated_at'] ?? '' }}</div>
        <div class="meta muted">
            Filters: {{ collect($payload['meta']['filters'] ?? [])->reject(fn($v, $k) => str_starts_with((string) $k, '_'))->map(fn($v, $k) => $k.': '.(is_array($v) ? json_encode($v) : ($v ?? 'n/a')))->implode(' | ') }}
        </div>

        @if(!empty($payload['sections']['summary']))
            <h2>Executive Summary</h2>
            <p>{{ $payload['sections']['summary'] }}</p>
        @endif

        @if(!empty($payload['sections']['insights']))
            <h2>Key Insights</h2>
            <ul>
                @foreach($payload['sections']['insights'] as $insight)
                    <li>{{ $insight }}</li>
                @endforeach
            </ul>
        @endif

        @if(!empty($payload['kpis']))
            <h2>KPIs</h2>
            <table class="kpi-grid">
                @foreach($payload['kpis'] as $kpi)
                    <tr>
                        <td>{{ $kpi['label'] ?? '' }}</td>
                        <td>{{ $kpi['value'] ?? '' }}</td>
                    </tr>
                @endforeach
            </table>
        @endif

        @if(!empty($payload['sections']['methodology']))
            <h2>Methodology</h2>
            <ul>
                @foreach($payload['sections']['methodology'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        @endif

        <h2>Table Results</h2>
        <table>
            <thead>
                <tr>
                    @foreach(($payload['table']['columns'] ?? []) as $column)
                        <th>{{ $column }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse(($payload['table']['rows'] ?? []) as $row)
                    <tr>
                        @foreach($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="muted">No data available.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if(!empty($payload['sections']['evidence']['rows']))
            <h2>Evidence & Records</h2>
            <table>
                <thead>
                    <tr>
                        @foreach(($payload['sections']['evidence']['columns'] ?? []) as $column)
                            <th>{{ $column }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach(($payload['sections']['evidence']['rows'] ?? []) as $row)
                        <tr>
                            @foreach($row as $cell)
                                <td>{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </body>
</html>
