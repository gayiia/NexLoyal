{{-- This view provides a sandbox for running AI feature generation and clustering with debug output. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        {{-- The title uses the app name configuration with a fallback for local/dev environments. --}}
        <title>{{ config('app.name', 'NexLoyal') }} - AI Sandbox</title>
        {{-- Preconnect and load the UI font used across the admin experience. --}}
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        {{-- Vite builds and injects the compiled CSS for this page. --}}
        @vite(['resources/css/app.css'])
        <style>
            {{-- These styles adjust the light theme and badges for the sandbox UI. --}}
            :root { color-scheme: dark; }
            body { letter-spacing: 0.01em; }
            .nl-theme-light { color-scheme: light; background-color: #f8fafc; color: #0f172a; }
            .nl-theme-light .nl-shell { background: linear-gradient(120deg, rgba(248, 250, 252, 0.95), rgba(226, 232, 240, 0.95)); }
            .nl-theme-light .nl-panel { background-color: rgba(255, 255, 255, 0.85); border-color: rgba(148, 163, 184, 0.4); color: #0f172a; }
            .nl-theme-light .nl-panel-muted { background-color: rgba(226, 232, 240, 0.6); border-color: rgba(148, 163, 184, 0.4); color: #0f172a; }
            .nl-theme-light .nl-text-muted { color: #475569; }
            .nl-theme-light .nl-sidebar-link { color: #0f172a; }
            .nl-theme-light .nl-sidebar-link:hover { background-color: rgba(226, 232, 240, 0.8); border-color: rgba(148, 163, 184, 0.6); }
            .nl-theme-light .nl-sidebar-link-active { background-color: rgba(226, 232, 240, 0.9); border-color: rgba(148, 163, 184, 0.6); color: #0f172a; }
            .nl-chip { border-radius: 999px; padding: 4px 12px; font-size: 11px; font-weight: 600; }
            .nl-badge-muted { background: rgba(148, 163, 184, 0.18); color: #e2e8f0; }
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
                            <p class="text-xs uppercase tracking-[0.32em] text-slate-400 nl-text-muted">AI Sandbox</p>
                            <h1 class="mt-2 text-2xl font-semibold">Train + Inspect</h1>
                            {{-- This summary explains that the page runs the AI pipeline with debug detail. --}}
                            <p class="mt-2 text-sm text-slate-400 nl-text-muted">Run feature generation and clustering with debug-friendly outputs.</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            {{-- Import prepares data for clustering when live data is not available. --}}
                            <a href="{{ route('ai-data-import') }}" class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-200 hover:border-slate-500">Import CSV</a>
                            {{-- Demo generation is not implemented yet, so the button is disabled. --}}
                            <button type="button" class="cursor-not-allowed rounded-lg border border-slate-700/60 px-4 py-2 text-sm text-slate-500" title="Demo generator coming soon" disabled>
                                Generate demo
                            </button>
                            {{-- Computing features pre-processes customer data for training. --}}
                            <form method="POST" action="{{ route('ai-sandbox.compute') }}">
                                @csrf
                                <button type="submit" class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-200 hover:border-slate-500">Compute features</button>
                            </form>
                            {{-- Training runs the clustering pipeline and stores results. --}}
                            <form method="POST" action="{{ route('ai-sandbox.train') }}">
                                @csrf
                                <button type="submit" class="rounded-lg bg-sky-500/90 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-sky-400">Train model</button>
                            </form>
                            {{-- Insights summarize the latest clustering runs for review. --}}
                            <a href="{{ route('ai-insights') }}" class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-200 hover:border-slate-500">View insights</a>
                        </div>
                    </div>

                    {{-- Status messages after running compute or training actions. --}}
                    @if(session('status'))
                        <div class="mt-6 rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm text-slate-200 nl-panel">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="mt-6 rounded-xl border border-rose-500/30 bg-rose-500/10 p-4 text-sm text-rose-200">
                            {{ session('error') }}
                        </div>
                    @endif

                    <div class="mt-8 grid gap-6 lg:grid-cols-[2fr,1fr]">
                        <section class="rounded-2xl border border-slate-800 bg-slate-950/70 p-6 nl-panel">
                            <div class="flex items-start justify-between">
                                <div>
                                    <h2 class="text-lg font-semibold">Latest training run</h2>
                                    {{-- This description makes clear the metrics are from the most recent run. --}}
                                    <p class="mt-1 text-sm text-slate-400 nl-text-muted">Status and metrics from the most recent cluster run.</p>
                                </div>
                                <div class="text-xs text-slate-400 nl-text-muted">
                                    @if($latestRun?->started_at)
                                        {{-- Humanized timestamps help admins confirm recency. --}}
                                        Started {{ $latestRun->started_at->diffForHumans() }}
                                    @endif
                                </div>
                            </div>

                            {{-- When no run exists, show a gentle prompt to start the pipeline. --}}
                            @if(!$latestRun)
                                <div class="mt-6 rounded-lg border border-slate-800 bg-slate-900/60 p-4 text-sm text-slate-300 nl-panel-muted">
                                    No AI runs yet. Use the buttons above to compute features and train.
                                </div>
                            @else
                                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                                    <div class="rounded-lg border border-slate-800/70 bg-slate-900/60 p-4 nl-panel-muted">
                                        <p class="text-xs uppercase tracking-[0.2em] text-slate-400 nl-text-muted">Status</p>
                                        <p class="mt-2 text-lg font-semibold">{{ ucfirst($latestRun->status) }}</p>
                                        <p class="mt-2 text-xs text-slate-400 nl-text-muted">Clusters: {{ $latestRun->total_clusters }}</p>
                                        <p class="text-xs text-slate-400 nl-text-muted">Customers: {{ $latestRun->total_customers }}</p>
                                    </div>
                                    <div class="rounded-lg border border-slate-800/70 bg-slate-900/60 p-4 nl-panel-muted">
                                        <p class="text-xs uppercase tracking-[0.2em] text-slate-400 nl-text-muted">Metrics</p>
                                        {{-- These metrics are optional and can be missing in partial runs. --}}
                                        <p class="mt-2 text-sm text-slate-200">Selected K: {{ $latestRun->selected_k ?? '—' }}</p>
                                        <p class="text-sm text-slate-200">Silhouette: {{ $latestRun->silhouette_score ?? '—' }}</p>
                                        <p class="text-sm text-slate-200">Inertia: {{ $latestRun->final_inertia ?? '—' }}</p>
                                    </div>
                                </div>

                                {{-- When training fails, the error message can be copied for debugging. --}}
                                @if($latestRun->status === 'failed' && $latestRun->error_message)
                                    <div class="mt-4 rounded-lg border border-rose-500/40 bg-rose-500/10 p-4 text-sm text-rose-100">
                                        <p class="font-semibold">Training failed</p>
                                        <p class="mt-2 text-rose-100/90">{{ $latestRun->error_message }}</p>
                                        <div class="mt-3 flex flex-wrap items-center gap-2">
                                            <button type="button" class="rounded-md border border-rose-300/60 px-3 py-1 text-xs" onclick="copyDebug()">Copy debug details</button>
                                            <span class="text-xs text-rose-200">Includes error response from AI service.</span>
                                        </div>
                                        <textarea id="debug-details" class="hidden">{{ $latestRun->error_message }}</textarea>
                                    </div>
                                @endif

                                <div class="mt-6 grid gap-6 lg:grid-cols-2">
                                    <div class="rounded-lg border border-slate-800/70 bg-slate-900/60 p-4 nl-panel-muted">
                                        <p class="text-xs uppercase tracking-[0.2em] text-slate-400 nl-text-muted">Silhouette scores</p>
                                        {{-- Each row shows the score for a tested K value. --}}
                                        @if($latestRun->silhouette_scores)
                                            <div class="mt-3 space-y-2 text-sm">
                                                @foreach($latestRun->silhouette_scores as $row)
                                                    <div class="flex items-center justify-between">
                                                        <span>K={{ $row['k'] ?? '—' }}</span>
                                                        <span class="text-slate-200">{{ $row['score'] ?? '—' }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="mt-3 text-xs text-slate-400 nl-text-muted">No silhouette metrics recorded.</p>
                                        @endif
                                    </div>
                                    <div class="rounded-lg border border-slate-800/70 bg-slate-900/60 p-4 nl-panel-muted">
                                        <p class="text-xs uppercase tracking-[0.2em] text-slate-400 nl-text-muted">Inertia scores</p>
                                        {{-- Each row shows the inertia for a tested K value. --}}
                                        @if($latestRun->inertia_scores)
                                            <div class="mt-3 space-y-2 text-sm">
                                                @foreach($latestRun->inertia_scores as $row)
                                                    <div class="flex items-center justify-between">
                                                        <span>K={{ $row['k'] ?? '—' }}</span>
                                                        <span class="text-slate-200">{{ $row['inertia'] ?? '—' }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="mt-3 text-xs text-slate-400 nl-text-muted">No inertia metrics recorded.</p>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </section>

                        <aside class="rounded-2xl border border-slate-800 bg-slate-950/70 p-6 nl-panel">
                            <h2 class="text-lg font-semibold">AI settings</h2>
                            {{-- These values come from config to show the training defaults. --}}
                            <p class="mt-1 text-sm text-slate-400 nl-text-muted">Config-driven defaults used for training.</p>
                            <div class="mt-4 space-y-2 text-sm">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-400">K range</span>
                                    <span class="text-slate-200">{{ data_get($settings, 'k_range.min') }} - {{ data_get($settings, 'k_range.max') }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-400">Outlier cap</span>
                                    <span class="text-slate-200">p{{ data_get($settings, 'outlier_cap_quantile') }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-400">Schema version</span>
                                    <span class="text-slate-200">{{ data_get($settings, 'feature_schema_version') }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-400">Algorithm</span>
                                    <span class="text-slate-200">{{ data_get($settings, 'algorithm_version') }}</span>
                                </div>
                                <div>
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-400 nl-text-muted">Log transforms</p>
                                    {{-- Log transforms indicate which numeric fields are scaled before clustering. --}}
                                    <p class="mt-2 text-xs text-slate-200">{{ implode(', ', data_get($settings, 'log_transforms', [])) ?: 'None' }}</p>
                                </div>
                            </div>
                        </aside>
                    </div>

                    <section class="mt-8 rounded-2xl border border-slate-800 bg-slate-950/70 p-6 nl-panel">
                        <h2 class="text-lg font-semibold">Onboarding checklist</h2>
                        {{-- This checklist mirrors the steps needed before reliable clustering. --}}
                        <p class="mt-1 text-sm text-slate-400 nl-text-muted">Quick milestones to activate AI-driven loyalty.</p>
                        <div class="mt-4 grid gap-3 md:grid-cols-2">
                            <div class="rounded-lg border border-slate-800/70 bg-slate-900/60 p-3 text-sm">Connect Shopify</div>
                            <div class="rounded-lg border border-slate-800/70 bg-slate-900/60 p-3 text-sm">Sync customers</div>
                            <div class="rounded-lg border border-slate-800/70 bg-slate-900/60 p-3 text-sm">Sync orders</div>
                            <div class="rounded-lg border border-slate-800/70 bg-slate-900/60 p-3 text-sm">Configure loyalty rules</div>
                            <div class="rounded-lg border border-slate-800/70 bg-slate-900/60 p-3 text-sm">Test widget</div>
                        </div>
                    </section>
                </main>
            </div>
        </div>

        <script>
            // Copy detailed error information so it can be shared with developers or examiners.
            function copyDebug() {
                const el = document.getElementById('debug-details');
                if (!el) return;
                const text = el.value || el.textContent || '';
                if (!text) return;
                // Use the Clipboard API when available, with a textarea fallback.
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(text);
                    return;
                }
                const temp = document.createElement('textarea');
                temp.value = text;
                document.body.appendChild(temp);
                temp.select();
                document.execCommand('copy');
                document.body.removeChild(temp);
            }
        </script>
    </body>
</html>
