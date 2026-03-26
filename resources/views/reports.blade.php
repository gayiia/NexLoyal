{{-- This view builds and renders reports with filters, summaries, and exports. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        {{-- The title uses the app name configuration with a fallback for local/dev environments. --}}
        <title>{{ config('app.name', 'NexLoyal') }} - Reports</title>
        {{-- Preconnect and load the UI font used across the admin experience. --}}
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        {{-- Vite builds and injects the compiled CSS for this page. --}}
        @vite(['resources/css/app.css'])
        <style>
            {{-- These styles provide light-mode overrides for the report shell. --}}
            :root { color-scheme: dark; }
            body { letter-spacing: 0.01em; }
            .nl-theme-light { color-scheme: light; background-color: #f8fafc; color: #0f172a; }
            .nl-theme-light .nl-shell { background: linear-gradient(120deg, rgba(248, 250, 252, 0.95), rgba(226, 232, 240, 0.95)); }
            .nl-theme-light .nl-panel { background-color: rgba(255, 255, 255, 0.85); border-color: rgba(148, 163, 184, 0.4); color: #0f172a; }
            .nl-theme-light .nl-panel-muted { background-color: rgba(226, 232, 240, 0.6); border-color: rgba(148, 163, 184, 0.4); color: #0f172a; }
            .nl-theme-light .nl-text-muted { color: #475569; }
        </style>
    </head>
    {{-- The body theme class is derived from the user's session preference, defaulting to dark. --}}
    <body class="{{ session('appearance', 'dark') === 'light' ? 'nl-theme-light' : '' }} bg-slate-950 text-slate-100">
        <div class="min-h-screen bg-gradient-to-br from-slate-950 via-slate-950 to-slate-900 nl-shell">
            <div class="mx-auto flex min-h-screen max-w-[1400px]">
                {{-- The admin sidebar is shared across the dashboard and provides navigation. --}}
                @include('partials.admin-sidebar')

                <main class="flex-1 p-8">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-[0.32em] text-slate-400 nl-text-muted">Reports</p>
                            <h1 class="mt-2 text-2xl font-semibold">Report Builder</h1>
                            {{-- This subtitle clarifies the scope of reports available. --}}
                            <p class="mt-2 text-sm text-slate-400 nl-text-muted">Generate KPI, loyalty, and AI reports on demand.</p>
                        </div>
                    </div>

                    {{-- Status messages confirm report actions. --}}
                    @if(session('status'))
                        <div class="mt-6 rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm text-slate-200 nl-panel">
                            {{ session('status') }}
                        </div>
                    @endif

                    {{-- Validation errors are shown above the form. --}}
                    @if($errors->any())
                        <div class="mt-6 rounded-xl border border-rose-500/40 bg-rose-500/10 p-4 text-sm text-rose-100">
                            <p class="font-semibold">Please fix the highlighted fields.</p>
                            <ul class="mt-2 list-disc pl-5">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- This form submits report selections and filters to the backend. --}}
                    <form method="POST" action="{{ route('reports.generate') }}" class="mt-8 rounded-2xl border border-slate-800 bg-slate-950/70 p-6 nl-panel">
                        @csrf
                        <div class="grid gap-6 lg:grid-cols-[1.2fr,1.8fr]">
                            <div>
                                <h2 class="text-lg font-semibold">Report selector</h2>
                                <p class="mt-1 text-sm text-slate-400 nl-text-muted">Choose the report to generate.</p>
                                {{-- The report key determines which dataset is built server-side. --}}
                                <select name="report_key" class="mt-4 w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-100">
                                    <option value="">Select a report</option>
                                    @foreach($reports as $key => $label)
                                        <option value="{{ $key }}" @selected(($selectedReport ?? '') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold">Filters</h2>
                                <p class="mt-1 text-sm text-slate-400 nl-text-muted">Use filters to scope the report output.</p>
                                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                    <div>
                                        {{-- Date filters constrain the time window of results. --}}
                                        <label class="text-xs uppercase tracking-[0.2em] text-slate-400">Start date</label>
                                        <input type="date" name="start_date" value="{{ $filters['start_date'] }}" class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-100">
                                    </div>
                                    <div>
                                        {{-- Date filters constrain the time window of results. --}}
                                        <label class="text-xs uppercase tracking-[0.2em] text-slate-400">End date</label>
                                        <input type="date" name="end_date" value="{{ $filters['end_date'] }}" class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-100">
                                    </div>
                                    <div>
                                        {{-- Customer type filters limit loyalty participation scope. --}}
                                        <label class="text-xs uppercase tracking-[0.2em] text-slate-400">Customer type</label>
                                        <select name="customer_type" class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-100">
                                            <option value="all" @selected($filters['customer_type'] === 'all')>All</option>
                                            <option value="loyalty" @selected($filters['customer_type'] === 'loyalty')>Loyalty members</option>
                                            <option value="non_loyalty" @selected($filters['customer_type'] === 'non_loyalty')>Non-loyalty</option>
                                        </select>
                                    </div>
                                    <div>
                                        {{-- Tier filters narrow results to a specific level. --}}
                                        <label class="text-xs uppercase tracking-[0.2em] text-slate-400">Tier</label>
                                        <select name="tier" class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-100">
                                            <option value="">All</option>
                                            @foreach($tiers as $tier)
                                                <option value="{{ $tier->id }}" @selected(($filters['tier'] ?? null) == $tier->id)>{{ $tier->title }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        {{-- Cluster filters apply only to AI segmentation reports. --}}
                                        <label class="text-xs uppercase tracking-[0.2em] text-slate-400">Cluster</label>
                                        <select name="cluster" class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-100">
                                            <option value="">All</option>
                                            @foreach($clusters as $cluster)
                                                <option value="{{ $cluster->id }}" @selected(($filters['cluster'] ?? null) == $cluster->id)>{{ $cluster->label ?? ('Cluster ' . $cluster->cluster_index) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        {{-- Minimum spend lets reports focus on high-value customers. --}}
                                        <label class="text-xs uppercase tracking-[0.2em] text-slate-400">Min total spent</label>
                                        <input type="number" step="0.01" min="0" name="min_total_spent" value="{{ $filters['min_total_spent'] }}" class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-100">
                                    </div>
                                    <div>
                                        {{-- Minimum orders filters out low-activity customers. --}}
                                        <label class="text-xs uppercase tracking-[0.2em] text-slate-400">Min orders count</label>
                                        <input type="number" min="0" name="min_orders_count" value="{{ $filters['min_orders_count'] }}" class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-100">
                                    </div>
                                    <div>
                                        {{-- Grouping controls how time series are aggregated. --}}
                                        <label class="text-xs uppercase tracking-[0.2em] text-slate-400">Group by</label>
                                        <select name="group_by" class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-100">
                                            <option value="day" @selected($filters['group_by'] === 'day')>Day</option>
                                            <option value="week" @selected($filters['group_by'] === 'week')>Week</option>
                                            <option value="month" @selected($filters['group_by'] === 'month')>Month</option>
                                        </select>
                                    </div>
                                    <div>
                                        {{-- Top N restricts leaderboard-style outputs. --}}
                                        <label class="text-xs uppercase tracking-[0.2em] text-slate-400">Top N</label>
                                        <input type="number" min="1" max="100" name="top_n" value="{{ $filters['top_n'] }}" class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-100">
                                    </div>
                                </div>
                                <div class="mt-6 flex flex-wrap items-center gap-3">
                                    {{-- Generate triggers the server-side report build. --}}
                                    <button type="submit" class="rounded-lg bg-sky-500/90 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-sky-400">Generate report</button>
                                    @if($payload)
                                        {{-- Exports are available only after a report is generated. --}}
                                        <a href="{{ route('reports.export.excel') }}" class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-200 hover:border-slate-500">Export Excel</a>
                                        <a href="{{ route('reports.export.pdf') }}" class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-200 hover:border-slate-500">Export PDF</a>
                                    @else
                                        <span class="text-xs text-slate-400 nl-text-muted">Generate a report to unlock exports.</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </form>

                    {{-- The report output sections only render after generation. --}}
                    @if($payload)
                        <section class="mt-8 grid gap-6 lg:grid-cols-[1.2fr,1fr]">
                            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-6 nl-panel">
                                <h2 class="text-lg font-semibold">Executive Summary</h2>
                                <p class="mt-2 text-sm text-slate-300">{{ $payload['sections']['summary'] ?? '' }}</p>
                                <div class="mt-4 text-xs text-slate-400 nl-text-muted">
                                    Generated at {{ $payload['meta']['generated_at'] ?? '' }}
                                </div>
                            </div>
                            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-6 nl-panel">
                                <h2 class="text-lg font-semibold">Key Insights</h2>
                                <ul class="mt-4 space-y-3 text-sm text-slate-200">
                                    @foreach(($payload['sections']['insights'] ?? []) as $insight)
                                        <li class="rounded-lg border border-slate-800/70 bg-slate-900/60 px-3 py-2">{{ $insight }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </section>

                        <section class="mt-6 grid gap-6 lg:grid-cols-[1.2fr,1fr]">
                            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-6 nl-panel">
                                <h2 class="text-lg font-semibold">KPIs</h2>
                                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                    {{-- Each KPI block shows a metric computed by the report service. --}}
                                    @foreach(($payload['kpis'] ?? []) as $kpi)
                                        <div class="rounded-xl border border-slate-800/70 bg-slate-900/60 p-4">
                                            <div class="text-xs uppercase tracking-[0.18em] text-slate-400">{{ $kpi['label'] ?? '' }}</div>
                                            <div class="mt-2 text-2xl font-semibold">{{ $kpi['value'] ?? '' }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-6 nl-panel">
                                <h2 class="text-lg font-semibold">Methodology</h2>
                                <ul class="mt-4 space-y-2 text-sm text-slate-300">
                                    @foreach(($payload['sections']['methodology'] ?? []) as $method)
                                        <li class="rounded-lg border border-slate-800/70 bg-slate-900/60 px-3 py-2">{{ $method }}</li>
                                    @endforeach
                                </ul>
                                <div class="mt-4 text-xs uppercase tracking-[0.2em] text-slate-400">Sources</div>
                                <div class="mt-2 flex flex-wrap gap-2 text-xs text-slate-200">
                                    {{-- Sources describe the data inputs used by the report. --}}
                                    @foreach(($payload['sections']['sources'] ?? []) as $source)
                                        <span class="rounded-full border border-slate-700 bg-slate-900/70 px-3 py-1">{{ $source }}</span>
                                    @endforeach
                                </div>
                            </div>
                        </section>

                        @if(!empty($payload['chart']['labels']))
                            @php
                                // Precompute max values so the SVG scales correctly.
                                $chart = $payload['chart'] ?? [];
                                $labels = $chart['labels'] ?? [];
                                $datasets = $chart['datasets'] ?? [];
                                $maxValue = 1;
                                foreach ($datasets as $dataset) {
                                    foreach (($dataset['data'] ?? []) as $value) {
                                        if (is_numeric($value)) {
                                            $maxValue = max($maxValue, (float) $value);
                                        }
                                    }
                                }
                                $colors = ['#38bdf8', '#f472b6', '#34d399', '#fbbf24'];
                            @endphp
                            <section class="mt-6 rounded-2xl border border-slate-800 bg-slate-950/70 p-6 nl-panel">
                                <div class="flex items-center justify-between">
                                    <h2 class="text-lg font-semibold">Trend Visualization</h2>
                                    <div class="text-xs text-slate-400 nl-text-muted">Chart-ready series</div>
                                </div>
                                <div class="mt-4">
                                    <svg viewBox="0 0 680 220" class="h-auto w-full">
                                        <rect x="0" y="0" width="680" height="220" fill="transparent"></rect>
                                        {{-- Each dataset is rendered as a polyline. --}}
                                        @foreach($datasets as $index => $dataset)
                                            @php
                                                $series = $dataset['data'] ?? [];
                                                $points = [];
                                                $count = max(count($series), 1);
                                                foreach ($series as $i => $value) {
                                                    $x = 20 + ($i * (640 / max($count - 1, 1)));
                                                    $y = 190 - ((is_numeric($value) ? (float) $value : 0) / $maxValue) * 150;
                                                    $points[] = $x . ',' . $y;
                                                }
                                            @endphp
                                            <polyline points="{{ implode(' ', $points) }}" fill="none" stroke="{{ $colors[$index % count($colors)] }}" stroke-width="2"></polyline>
                                        @endforeach
                                    </svg>
                                </div>
                                <div class="mt-4 grid gap-2 text-xs text-slate-300 md:grid-cols-2">
                                    <div>
                                        <div class="text-xs uppercase tracking-[0.18em] text-slate-400">Labels</div>
                                        <div class="mt-2 text-slate-200">{{ implode(', ', $labels) }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs uppercase tracking-[0.18em] text-slate-400">Datasets</div>
                                        <div class="mt-2 text-slate-200">
                                            {{-- Dataset names and counts are listed for clarity. --}}
                                            @foreach($datasets as $dataset)
                                                <div>{{ $dataset['label'] ?? 'Series' }} ({{ count($dataset['data'] ?? []) }} points)</div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </section>
                        @endif

                        <section class="mt-6 grid gap-6 lg:grid-cols-[1.3fr,1fr]">
                            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-6 nl-panel">
                                <h2 class="text-lg font-semibold">Full Results</h2>
                                <div class="mt-4 overflow-auto">
                                    <table class="w-full text-sm">
                                        <thead class="bg-slate-900/70 text-xs uppercase tracking-[0.18em] text-slate-400">
                                            <tr>
                                                @foreach(($payload['table']['columns'] ?? []) as $column)
                                                    <th class="px-4 py-3 text-left">{{ $column }}</th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {{-- Rows are produced by the report service. --}}
                                            @forelse(($payload['table']['rows'] ?? []) as $row)
                                                <tr class="border-t border-slate-800/70">
                                                    @foreach($row as $cell)
                                                        <td class="px-4 py-3">{{ $cell }}</td>
                                                    @endforeach
                                                </tr>
                                            @empty
                                                {{-- Empty state when there is no table data. --}}
                                                <tr>
                                                    <td colspan="8" class="px-4 py-6 text-center text-slate-400">No data available.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-6 nl-panel">
                                <h2 class="text-lg font-semibold">Evidence & Records</h2>
                                <p class="mt-1 text-sm text-slate-400 nl-text-muted">Top records used to build this report.</p>
                                <div class="mt-4 overflow-auto">
                                    <table class="w-full text-xs">
                                        <thead class="bg-slate-900/70 text-[10px] uppercase tracking-[0.18em] text-slate-400">
                                            <tr>
                                                @foreach(($payload['sections']['evidence']['columns'] ?? []) as $column)
                                                    <th class="px-3 py-2 text-left">{{ $column }}</th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {{-- Evidence rows justify the report’s conclusions. --}}
                                            @forelse(($payload['sections']['evidence']['rows'] ?? []) as $row)
                                                <tr class="border-t border-slate-800/70">
                                                    @foreach($row as $cell)
                                                        <td class="px-3 py-2">{{ $cell }}</td>
                                                    @endforeach
                                                </tr>
                                            @empty
                                                {{-- Empty state when evidence is unavailable. --}}
                                                <tr>
                                                    <td colspan="8" class="px-3 py-4 text-center text-slate-400">No evidence available.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>
                    @endif
                </main>
            </div>
        </div>
    </body>
</html>
