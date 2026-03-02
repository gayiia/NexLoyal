
{{-- This view presents AI clustering results, charts, and award management for administrators. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        {{-- The title uses the app name configuration with a fallback for local/dev environments. --}}
        <title>{{ config('app.name', 'NexLoyal') }} - AI Insights</title>
        {{-- Preconnect and load the UI font used across the admin experience. --}}
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        {{-- Vite builds and injects the compiled CSS for this page. --}}
        @vite(['resources/css/app.css'])
        {{-- Chart.js renders the clustering charts in the dashboard. --}}
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <style>
            {{-- These styles toggle light-mode colors for admin previews within the dark theme. --}}
            :root {
                color-scheme: dark;
            }
            body {
                letter-spacing: 0.01em;
            }
            .nl-theme-light {
                color-scheme: light;
                background-color: #f8fafc;
                color: #0f172a;
            }
            .nl-theme-light .nl-shell {
                background: linear-gradient(120deg, rgba(248, 250, 252, 0.95), rgba(226, 232, 240, 0.95));
            }
            .nl-theme-light .nl-panel {
                background-color: rgba(255, 255, 255, 0.85);
                border-color: rgba(148, 163, 184, 0.4);
                color: #0f172a;
            }
            .nl-theme-light .nl-panel-muted {
                background-color: rgba(226, 232, 240, 0.6);
                border-color: rgba(148, 163, 184, 0.4);
                color: #0f172a;
            }
            .nl-theme-light .nl-text-muted {
                color: #475569;
            }
            .nl-theme-light .nl-sidebar-link {
                color: #0f172a;
            }
            .nl-theme-light .nl-sidebar-link:hover {
                background-color: rgba(226, 232, 240, 0.8);
                border-color: rgba(148, 163, 184, 0.6);
            }
            .nl-theme-light .nl-sidebar-link-active {
                background-color: rgba(226, 232, 240, 0.9);
                border-color: rgba(148, 163, 184, 0.6);
                color: #0f172a;
            }
            .nl-theme-light .text-slate-50,
            .nl-theme-light .text-slate-100,
            .nl-theme-light .text-slate-200 {
                color: #0f172a;
            }
            .nl-theme-light .text-slate-300 {
                color: #334155;
            }
            .nl-theme-light .text-slate-400,
            .nl-theme-light .text-slate-500 {
                color: #475569;
            }
            .nl-chip {
                border-radius: 999px;
                padding: 4px 12px;
                font-size: 11px;
                font-weight: 600;
            }
            .nl-badge-muted {
                background: rgba(148, 163, 184, 0.18);
                color: #e2e8f0;
            }
            .nl-modal-backdrop {
                position: fixed;
                inset: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 24px;
                background: rgba(2, 6, 23, 0.7);
                opacity: 0;
                pointer-events: none;
                transition: opacity 200ms ease;
                z-index: 50;
            }
            .nl-modal-backdrop.is-open {
                opacity: 1;
                pointer-events: auto;
            }
            .nl-modal-panel {
                width: 100%;
                max-width: 860px;
                border-radius: 18px;
                background: linear-gradient(160deg, rgba(15, 23, 42, 0.98), rgba(2, 6, 23, 0.95));
                border: 1px solid rgba(148, 163, 184, 0.2);
                box-shadow: 0 30px 80px rgba(2, 6, 23, 0.55);
                display: flex;
                flex-direction: column;
                max-height: calc(100vh - 48px);
            }
            .nl-modal-divider {
                border-color: rgba(148, 163, 184, 0.16);
            }
            .nl-theme-light .nl-modal-backdrop {
                background: rgba(148, 163, 184, 0.55);
            }
            .nl-theme-light .nl-modal-panel {
                background: linear-gradient(160deg, rgba(255, 255, 255, 0.95), rgba(241, 245, 249, 0.95));
                border-color: rgba(148, 163, 184, 0.4);
                color: #0f172a;
            }
            .nl-theme-light .nl-modal-divider {
                border-color: rgba(148, 163, 184, 0.3);
            }
            /* The progress bar animates while clustering jobs are running. */
            .nl-progress { position: relative; height: 6px; border-radius: 999px; background: rgba(148,163,184,0.18); overflow: hidden; }
.nl-progress::after { content: ''; position: absolute; inset: 0; width: 40%; background: linear-gradient(90deg, rgba(56,189,248,0.1), rgba(56,189,248,0.8), rgba(56,189,248,0.1)); animation: nl-progress-move 1.2s linear infinite; }
@keyframes nl-progress-move { 0% { transform: translateX(-120%); } 100% { transform: translateX(220%); } }

        </style>
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
        <div class="min-h-screen bg-[radial-gradient(900px_circle_at_top,rgba(56,189,248,0.18),transparent_60%)]">
            <div class="min-h-screen bg-[radial-gradient(700px_circle_at_bottom,rgba(30,64,175,0.22),transparent_60%)]">
                <div class="min-h-screen bg-[linear-gradient(120deg,rgba(15,23,42,0.9),rgba(2,6,23,0.95))] nl-shell">
                    <div class="flex min-h-screen">
                        {{-- The admin sidebar is shared across the dashboard and provides navigation. --}}
                        @include('partials.admin-sidebar')

                        <main class="flex-1 px-10 py-8">
                            {{-- The header provides actions to run clustering and manage awards. --}}
                            <x-page-header eyebrow="Insights" title="AI Insights ?" subtitle="Cluster-based customer intelligence">
                                <x-slot name="actions">
                                    {{-- Running clustering triggers the backend pipeline to compute segments. --}}
                                    <form method="POST" action="{{ route('ai-insights.run') }}">
                                        @csrf
                                        <button class="rounded-xl bg-sky-400 px-4 py-2 text-sm font-semibold text-slate-950 shadow-lg shadow-sky-500/30" type="submit">
                                            Run clustering
                                        </button>
                                    </form>
                                    {{-- Awards are created from clustering results. --}}
                                    <a href="{{ route('ai-insights.awards.create') }}" class="rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 nl-panel-muted">
                                        Create award
                                    </a>
                                    <button id="theme-toggle" class="rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 nl-panel-muted" type="button">
                                        Switch theme
                                    </button>
                                </x-slot>
                            </x-page-header>

                            {{-- Status flashes are shown after actions like running clustering. --}}
                            @if (session('status'))
                                <div class="mt-4 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
                                    {{ session('status') }}
                                </div>
                            @endif

                            @if (session('error'))
                                <div class="mt-4 rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                                    {{ session('error') }}
                                </div>
                            @endif

                            {{-- Award-specific errors are displayed near the top of the page. --}}
                            @if ($errors->has('award'))
                                <div class="mt-4 rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                                    {{ $errors->first('award') }}
                                </div>
                            @endif
                            {{-- This panel displays the current clustering run status with refresh support. --}}
<div id="ai-status-panel" class="mt-4 rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-4 text-xs text-slate-300">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <span class="text-slate-400">Clustering status:</span>
            <span id="ai-status-text" class="ml-2 text-slate-100">No runs</span>
        </div>
        <button id="ai-status-refresh" type="button" class="rounded-lg border border-slate-700 px-3 py-1 text-xs text-slate-200">
            Refresh
        </button>
    </div>
    <p id="ai-status-phase" class="mt-3 text-[11px] uppercase tracking-[0.2em] text-sky-300 hidden"></p>
    <p id="ai-status-message" class="mt-2 text-xs text-slate-300 hidden"></p>
    <div id="ai-progress" class="mt-3 hidden">
        <div class="nl-progress"></div>
        <p class="mt-2 text-[11px] text-slate-400">Working through customer features and clustering.</p>
    </div>
    <p id="ai-status-error" class="mt-3 hidden text-rose-300"></p>
    <div class="mt-4 rounded-xl border border-slate-800/70 bg-slate-950/40 p-3">
        <div class="flex items-center justify-between gap-2">
            <p class="text-[11px] uppercase tracking-[0.2em] text-slate-400">Activity log</p>
            <span id="ai-status-updated" class="text-[11px] text-slate-500">Waiting for updates</span>
        </div>
        <ul id="ai-status-log" class="mt-3 space-y-2 text-xs text-slate-300">
            <li class="text-slate-500">No activity yet.</li>
        </ul>
    </div>
</div>

                            <section class="mt-4 grid gap-4 lg:grid-cols-2">
                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-4 text-xs text-slate-300">
                                    <p class="uppercase tracking-[0.2em] text-slate-400">AI Service</p>
                                    <p class="mt-2 text-sm text-slate-100">{{ ($serviceHealth['ok'] ?? false) ? 'Online' : 'Offline' }}</p>
                                    <p class="mt-1 text-slate-400">{{ $serviceHealth['message'] ?? 'No status available.' }}</p>
                                </div>
                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-4 text-xs text-slate-300">
                                    <p class="uppercase tracking-[0.2em] text-slate-400">Feature Dataset</p>
                                    <p class="mt-2 text-sm text-slate-100">Eligible: {{ number_format($featureStats['eligible_customer_features'] ?? 0) }} / {{ number_format($featureStats['customer_features'] ?? 0) }}</p>
                                    <p class="mt-1 text-slate-400">Customers: {{ number_format($featureStats['customers'] ?? 0) }}</p>
                                    <p class="text-slate-400">Minimum for training: {{ number_format($featureStats['min_customers_for_training'] ?? 0) }}</p>
                                </div>
                            </section>

                            <section class="mt-6 grid gap-4 lg:grid-cols-4">
                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-5">
                                    {{-- The latest run summary helps confirm the current model state. --}}
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Latest run</p>
                                    <p class="mt-3 text-2xl font-semibold text-slate-50">
                                        {{ $latestRun?->status ? ucfirst($latestRun->status) : 'No runs' }}
                                    </p>
                                    <p class="mt-1 text-xs text-slate-400">
                                        {{ $latestRun?->started_at ? $latestRun->started_at->format('M d, Y H:i') : 'Start clustering to see results.' }}
                                    </p>
                                </div>
                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-5">
                                    {{-- Clusters and K show how many segments were produced. --}}
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Clusters</p>
                                    <p class="mt-3 text-2xl font-semibold text-slate-50">{{ $latestRun?->total_clusters ?? 0 }}</p>
                                    <p class="mt-1 text-xs text-slate-400">Selected K: {{ $latestRun?->selected_k ?? '-' }}</p>
                                </div>
                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-5">
                                    {{-- Silhouette and inertia give model quality signals. --}}
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Silhouette</p>
                                    <p class="mt-3 text-2xl font-semibold text-slate-50">
                                        {{ $latestRun?->silhouette_score !== null ? number_format($latestRun->silhouette_score, 3) : '-' }}
                                    </p>
                                    <p class="mt-1 text-xs text-slate-400">Inertia: {{ $latestRun?->final_inertia ?? '-' }}</p>
                                </div>
                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-5">
                                    {{-- Customer counts show how many were included in clustering. --}}
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Customers</p>
                                    <p class="mt-3 text-2xl font-semibold text-slate-50">{{ number_format($latestRun?->total_customers ?? 0) }}</p>
                                    <p class="mt-1 text-xs text-slate-400">Included in latest run.</p>
                                </div>
                            </section>

                            <section class="mt-8 grid gap-6 lg:grid-cols-3">
                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-6">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-100">Customer distribution</p>
                                            <p class="text-xs text-slate-400">Customers per cluster.</p>
                                        </div>
                                    </div>
                                    <div class="mt-6">
                                        {{-- The chart renders when data is available; otherwise the empty message shows. --}}
                                        <canvas id="cluster-distribution-chart" height="180"></canvas>
                                        <p id="cluster-distribution-empty" class="mt-4 text-xs text-slate-400 hidden">No clustering data yet.</p>
                                    </div>
                                </div>
                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-6">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-100">Avg spend per cluster</p>
                                            <p class="text-xs text-slate-400">Total spent snapshot.</p>
                                        </div>
                                    </div>
                                    <div class="mt-6">
                                        {{-- The chart renders when spend data exists for clusters. --}}
                                        <canvas id="cluster-spend-chart" height="180"></canvas>
                                        <p id="cluster-spend-empty" class="mt-4 text-xs text-slate-400 hidden">No spend data yet.</p>
                                    </div>
                                </div>
                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-6">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-100">Award issuance overview</p>
                                            <p class="text-xs text-slate-400">Points vs coupons.</p>
                                        </div>
                                    </div>
                                    <div class="mt-6">
                                        {{-- The chart renders only when awards have been issued. --}}
                                        <canvas id="award-mix-chart" height="180"></canvas>
                                        <p id="award-mix-empty" class="mt-4 text-xs text-slate-400 hidden">No awards issued yet.</p>
                                    </div>
                                </div>
                            </section>

                            <section class="mt-10">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-100">Cluster breakdown</p>
                                        <p class="text-xs text-slate-400">Explore customers and segment traits.</p>
                                    </div>
                                </div>
                                <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                    {{-- Each card summarizes a single cluster and offers drill-down actions. --}}
                                    @forelse ($clusters as $cluster)
                                        <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-5">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-400">{{ $cluster->label }}</p>
                                                    <p class="mt-2 text-2xl font-semibold text-slate-50">{{ number_format($cluster->customer_count) }}</p>
                                                    <p class="mt-1 text-xs text-slate-400">customers</p>
                                                </div>
                                                <span class="nl-chip nl-badge-muted">{{ number_format($cluster->avg_total_spent, 2) }} avg spend</span>
                                            </div>
                                            <div class="mt-4 space-y-2 text-xs text-slate-300">
                                                <div class="flex items-center justify-between">
                                                    <span>Avg orders</span>
                                                    <span>{{ number_format($cluster->avg_orders_count, 1) }}</span>
                                                </div>
                                                <div class="flex items-center justify-between">
                                                    <span>Avg loyalty points</span>
                                                    <span>{{ number_format($cluster->avg_loyalty_points, 1) }}</span>
                                                </div>
                                                <div class="flex items-center justify-between">
                                                    <span>Avg points spent</span>
                                                    <span>{{ number_format($cluster->avg_points_spent, 1) }}</span>
                                                </div>
                                            </div>
                                            <div class="mt-5 flex flex-wrap gap-2">
                                                {{-- The modal shows the customer list without leaving the page. --}}
                                                <button class="rounded-xl border border-slate-700 px-3 py-2 text-xs text-slate-200" data-modal-open="cluster-{{ $cluster->id }}">
                                                    View customers
                                                </button>
                                                <a class="rounded-xl border border-slate-700 px-3 py-2 text-xs text-slate-200" href="{{ route('ai-insights.clusters.export', $cluster) }}">
                                                    Export CSV
                                                </a>
                                            </div>
                                        </div>
                                    @empty
                                        {{-- Encourage running clustering when no cluster data exists. --}}
                                        <div class="col-span-full rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-6 text-sm text-slate-400">
                                            Run clustering to generate AI segments.
                                        </div>
                                    @endforelse
                                </div>
                            </section>

                            <section class="mt-12 rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-6">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-100">Award history</p>
                                        <p class="text-xs text-slate-400">Drafts and active AI rewards.</p>
                                    </div>
                                    {{-- Shortcut for creating a new award from insights. --}}
                                    <a href="{{ route('ai-insights.awards.create') }}" class="rounded-xl bg-sky-400 px-4 py-2 text-xs font-semibold text-slate-950">
                                        New award
                                    </a>
                                </div>

                                <div class="mt-4 overflow-x-auto">
                                    <table class="w-full text-left text-xs">
                                        <thead class="text-slate-400">
                                            <tr class="border-b border-slate-800/60">
                                                <th class="px-3 py-2">Title</th>
                                                <th class="px-3 py-2">Cluster</th>
                                                <th class="px-3 py-2">Type</th>
                                                <th class="px-3 py-2">Status</th>
                                                <th class="px-3 py-2">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-800/60">
                                            {{-- Each row represents an AI award and the actions available for its status. --}}
                                            @forelse ($awards as $award)
                                                <tr>
                                                    <td class="px-3 py-3 text-slate-200">{{ $award->title }}</td>
                                                    <td class="px-3 py-3 text-slate-300">{{ $award->cluster?->label ?? '?' }}</td>
                                                    <td class="px-3 py-3 text-slate-300">{{ ucfirst($award->type) }}</td>
                                                    <td class="px-3 py-3 text-slate-300">{{ ucfirst($award->status) }}</td>
                                                    <td class="px-3 py-3">
                                                        <div class="flex flex-wrap gap-2">
                                                            {{-- Draft awards can be edited before activation. --}}
                                                            @if ($award->status === 'draft')
                                                                <a class="rounded-lg border border-slate-700 px-3 py-1.5 text-slate-200" href="{{ route('ai-insights.awards.edit', $award) }}">Edit</a>
                                                            @endif
                                                            {{-- Export provides a CSV of targeted customers. --}}
                                                            <a class="rounded-lg border border-slate-700 px-3 py-1.5 text-slate-200" href="{{ route('ai-insights.awards.export', $award) }}">Export</a>
                                                            @if ($award->status !== 'active')
                                                                {{-- Activating makes the award available for issuance. --}}
                                                                <form method="POST" action="{{ route('ai-insights.awards.activate', $award) }}">
                                                                    @csrf
                                                                    @method('PATCH')
                                                                    <button class="rounded-lg border border-slate-700 px-3 py-1.5 text-slate-200" type="submit">Activate</button>
                                                                </form>
                                                            @else
                                                                {{-- Deactivation pauses issuance for the award. --}}
                                                                <form method="POST" action="{{ route('ai-insights.awards.deactivate', $award) }}">
                                                                    @csrf
                                                                    @method('PATCH')
                                                                    <button class="rounded-lg border border-slate-700 px-3 py-1.5 text-slate-200" type="submit">Deactivate</button>
                                                                </form>
                                                            @endif
                                                            @if ($award->status === 'draft')
                                                                {{-- Deleting is only allowed while the award is still a draft. --}}
                                                                <form method="POST" action="{{ route('ai-insights.awards.destroy', $award) }}" onsubmit="return confirm('Delete this award?');">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button class="rounded-lg border border-rose-500/50 px-3 py-1.5 text-rose-200" type="submit">Delete</button>
                                                                </form>
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                {{-- Empty state when no awards exist. --}}
                                                <tr>
                                                    <td colspan="5" class="px-3 py-6 text-center text-slate-400">No awards created yet.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        </main>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modals render per cluster and load customer details on demand. --}}
        @foreach ($clusters as $cluster)
            <div class="nl-modal-backdrop" id="cluster-{{ $cluster->id }}" aria-hidden="true" data-cluster-id="{{ $cluster->id }}" data-customers-url="{{ route('ai-insights.clusters.customers', $cluster) }}">
                <div class="nl-modal-panel">
                    <div class="flex items-center justify-between border-b border-slate-800 px-6 py-4 nl-modal-divider">
                        <div>
                            <p class="text-sm font-semibold text-slate-100">{{ $cluster->label }}</p>
                            <p class="text-xs text-slate-400">{{ number_format($cluster->customer_count) }} customers in this cluster.</p>
                        </div>
                        <button type="button" class="rounded-lg border border-slate-700 px-3 py-1.5 text-xs text-slate-200" data-modal-close>
                            Close
                        </button>
                    </div>
                    <div class="flex-1 overflow-auto px-6 py-4">
                        <table class="w-full text-left text-xs">
                            <thead class="text-slate-400">
                                <tr class="border-b border-slate-800/60">
                                    <th class="px-3 py-2">Customer</th>
                                    <th class="px-3 py-2">Email</th>
                                    <th class="px-3 py-2">Orders</th>
                                    <th class="px-3 py-2">Total Spent</th>
                                    <th class="px-3 py-2">Points</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60" data-modal-rows>
                                <tr>
                                    <td colspan="5" class="px-3 py-6 text-center text-slate-400">Loading customers...</td>
                                </tr>
                            </tbody>
                        </table>
                        <p class="mt-3 text-[11px] text-slate-500" data-modal-footnote hidden></p>
                    </div>
                    <div class="flex items-center justify-end border-t border-slate-800 px-6 py-4 nl-modal-divider">
                        <a class="rounded-lg border border-slate-700 px-3 py-2 text-xs text-slate-200" href="{{ route('ai-insights.clusters.export', $cluster) }}">
                            Export CSV
                        </a>
                    </div>
                </div>
            </div>
        @endforeach

        <script>
            (function () {
                // Store the theme preference locally so it persists between visits.
                const storageKey = 'nl-theme';
                const body = document.body;
                const button = document.getElementById('theme-toggle');

                // Apply light or dark styles and update the button label.
                const applyTheme = (theme) => {
                    if (theme === 'light') {
                        body.classList.add('nl-theme-light');
                    } else {
                        body.classList.remove('nl-theme-light');
                    }
                    if (button) {
                        button.textContent = theme === 'light' ? 'Switch to dark' : 'Switch to light';
                    }
                };

                // Default to dark when no preference is stored.
                const stored = localStorage.getItem(storageKey);
                applyTheme(stored || 'dark');

                if (button) {
                    button.addEventListener('click', () => {
                        // Toggle the theme and persist the choice.
                        const next = body.classList.contains('nl-theme-light') ? 'dark' : 'light';
                        localStorage.setItem(storageKey, next);
                        applyTheme(next);
                    });
                }

                // Keep the settings menu open when navigating within settings pages.
                const settingsToggle = document.getElementById('settings-toggle');
                const settingsMenu = document.getElementById('settings-menu');
                const shouldOpenSettings = window.location.pathname.startsWith('/settings');
                if (settingsMenu) {
                    settingsMenu.classList.toggle('hidden', !shouldOpenSettings);
                }
                if (settingsToggle && settingsMenu) {
                    settingsToggle.addEventListener('click', () => {
                        settingsMenu.classList.toggle('hidden');
                    });
                }
            })();

            (function () {
                // Chart data is injected from the server-side insights calculations.
                const labels = @json($charts['labels']);
                const distribution = @json($charts['distribution']);
                const avgSpend = @json($charts['avg_spend']);
                const awardMix = @json($charts['award_mix']);

                // Shared chart styling matches the dark theme.
                const chartOptions = {
                    responsive: true,
                    plugins: {
                        legend: {
                            labels: {
                                color: '#e2e8f0',
                            },
                        },
                    },
                    scales: {
                        x: {
                            ticks: { color: '#94a3b8' },
                            grid: { color: 'rgba(148, 163, 184, 0.12)' },
                        },
                        y: {
                            ticks: { color: '#94a3b8' },
                            grid: { color: 'rgba(148, 163, 184, 0.12)' },
                        },
                    },
                };

                // Only show the distribution chart when data exists.
                const hasDistribution = labels.length > 0 && distribution.some((value) => value > 0);
                const distributionCanvas = document.getElementById('cluster-distribution-chart');
                const distributionEmpty = document.getElementById('cluster-distribution-empty');
                if (!hasDistribution) {
                    distributionEmpty?.classList.remove('hidden');
                    distributionCanvas?.classList.add('hidden');
                } else if (distributionCanvas) {
                    new Chart(distributionCanvas, {
                        type: 'bar',
                        data: {
                            labels,
                            datasets: [{
                                label: 'Customers',
                                data: distribution,
                                backgroundColor: 'rgba(56, 189, 248, 0.7)',
                                borderRadius: 6,
                            }],
                        },
                        options: chartOptions,
                    });
                }

                // Only show the spend chart when data exists.
                const hasSpend = labels.length > 0 && avgSpend.some((value) => value > 0);
                const spendCanvas = document.getElementById('cluster-spend-chart');
                const spendEmpty = document.getElementById('cluster-spend-empty');
                if (!hasSpend) {
                    spendEmpty?.classList.remove('hidden');
                    spendCanvas?.classList.add('hidden');
                } else if (spendCanvas) {
                    new Chart(spendCanvas, {
                        type: 'bar',
                        data: {
                            labels,
                            datasets: [{
                                label: 'Avg Total Spent',
                                data: avgSpend,
                                backgroundColor: 'rgba(129, 140, 248, 0.7)',
                                borderRadius: 6,
                            }],
                        },
                        options: chartOptions,
                    });
                }

                // Only show the award mix chart when there are issued awards.
                const awardCanvas = document.getElementById('award-mix-chart');
                const awardEmpty = document.getElementById('award-mix-empty');
                const awardValues = [awardMix.points || 0, awardMix.coupon || 0];
                const hasAwards = awardValues.some((value) => value > 0);
                if (!hasAwards) {
                    awardEmpty?.classList.remove('hidden');
                    awardCanvas?.classList.add('hidden');
                } else if (awardCanvas) {
                    new Chart(awardCanvas, {
                        type: 'doughnut',
                        data: {
                            labels: ['Points awards', 'Coupon awards'],
                            datasets: [{
                                data: awardValues,
                                backgroundColor: ['rgba(56, 189, 248, 0.8)', 'rgba(244, 114, 182, 0.8)'],
                                borderWidth: 0,
                            }],
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    labels: {
                                        color: '#e2e8f0',
                                    },
                                },
                            },
                        },
                    });
                }
            })();

            (function () {
                // Modal open/close handlers provide an inline customer drill-down.
                const openButtons = document.querySelectorAll('[data-modal-open]');
                const closeButtons = document.querySelectorAll('[data-modal-close]');
                const loadModalRows = (modal) => {
                    if (!modal || modal.dataset.loaded === 'true' || modal.dataset.loading === 'true') {
                        return;
                    }

                    const rowsBody = modal.querySelector('[data-modal-rows]');
                    const footnote = modal.querySelector('[data-modal-footnote]');
                    const url = modal.dataset.customersUrl;
                    if (!rowsBody || !url) {
                        return;
                    }

                    modal.dataset.loading = 'true';
                    rowsBody.innerHTML = '<tr><td colspan="5" class="px-3 py-6 text-center text-slate-400">Loading customers...</td></tr>';

                    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                        .then((response) => response.json())
                        .then((payload) => {
                            const rows = Array.isArray(payload?.rows) ? payload.rows : [];
                            rowsBody.innerHTML = '';

                            if (rows.length === 0) {
                                rowsBody.innerHTML = '<tr><td colspan="5" class="px-3 py-6 text-center text-slate-400">No customers in this cluster.</td></tr>';
                            } else {
                                rows.forEach((row) => {
                                    const tr = document.createElement('tr');
                                    tr.innerHTML = `
                                        <td class="px-3 py-3 text-slate-200">${row.name || 'Customer'}</td>
                                        <td class="px-3 py-3 text-slate-300">${row.email || ''}</td>
                                        <td class="px-3 py-3 text-slate-300">${row.orders_count_snapshot ?? 0}</td>
                                        <td class="px-3 py-3 text-slate-300">${Number(row.total_spent_snapshot ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                        <td class="px-3 py-3 text-slate-300">${Number(row.loyalty_points_snapshot ?? 0).toLocaleString()}</td>
                                    `;
                                    rowsBody.appendChild(tr);
                                });
                            }

                            if (footnote) {
                                const sampleLimit = Number(payload?.sample_limit || 0);
                                const customerCount = Number(payload?.customer_count || rows.length);
                                if (sampleLimit && customerCount > sampleLimit) {
                                    footnote.textContent = `Showing the top ${sampleLimit.toLocaleString()} customers by spend. Export CSV for the full cluster.`;
                                    footnote.hidden = false;
                                } else {
                                    footnote.hidden = true;
                                    footnote.textContent = '';
                                }
                            }

                            modal.dataset.loaded = 'true';
                        })
                        .catch(() => {
                            rowsBody.innerHTML = '<tr><td colspan="5" class="px-3 py-6 text-center text-rose-300">Unable to load cluster customers right now.</td></tr>';
                        })
                        .finally(() => {
                            delete modal.dataset.loading;
                        });
                };

                openButtons.forEach((button) => {
                    button.addEventListener('click', () => {
                        const id = button.getAttribute('data-modal-open');
                        const modal = document.getElementById(id);
                        if (modal) {
                            modal.classList.add('is-open');
                            modal.setAttribute('aria-hidden', 'false');
                            loadModalRows(modal);
                        }
                    });
                });

                closeButtons.forEach((button) => {
                    button.addEventListener('click', () => {
                        const modal = button.closest('.nl-modal-backdrop');
                        if (modal) {
                            modal.classList.remove('is-open');
                            modal.setAttribute('aria-hidden', 'true');
                        }
                    });
                });

                // Clicking outside the modal panel closes the overlay.
                document.querySelectorAll('.nl-modal-backdrop').forEach((modal) => {
                    modal.addEventListener('click', (event) => {
                        if (event.target === modal) {
                            modal.classList.remove('is-open');
                            modal.setAttribute('aria-hidden', 'true');
                        }
                    });
                });
            })();
        </script>
        <script>
(function () {
    // This block polls the backend for clustering status and updates the status panel.
    const statusText = document.getElementById('ai-status-text');
    const statusError = document.getElementById('ai-status-error');
    const statusPhase = document.getElementById('ai-status-phase');
    const statusMessage = document.getElementById('ai-status-message');
    const statusUpdated = document.getElementById('ai-status-updated');
    const statusLog = document.getElementById('ai-status-log');
    const progress = document.getElementById('ai-progress');
    const refresh = document.getElementById('ai-status-refresh');
    let pollTimer = null;
    let lastStatus = null;

    const titleCase = (value) => {
        if (!value) {
            return '';
        }

        return value
            .split('_')
            .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
            .join(' ');
    };

    const renderLogs = (logs) => {
        if (!statusLog) {
            return;
        }

        statusLog.innerHTML = '';

        if (!Array.isArray(logs) || logs.length === 0) {
            const item = document.createElement('li');
            item.className = 'text-slate-500';
            item.textContent = 'No activity yet.';
            statusLog.appendChild(item);
            return;
        }

        logs.slice().reverse().forEach((entry) => {
            const item = document.createElement('li');
            const levelClass = entry?.level === 'error' ? 'text-rose-300' : 'text-slate-300';
            item.className = `flex items-start justify-between gap-3 ${levelClass}`;
            item.innerHTML = `<span>${entry?.message || 'Update received.'}</span><span class="shrink-0 text-[11px] text-slate-500">${entry?.time || ''}</span>`;
            statusLog.appendChild(item);
        });
    };

    const updateUi = (payload) => {
        const status = payload?.status || 'none';
        const label = status === 'none' ? 'No runs' : status.charAt(0).toUpperCase() + status.slice(1);
        if (statusText) statusText.textContent = label;
        if (statusPhase) {
            statusPhase.textContent = payload?.phase ? `Phase: ${titleCase(payload.phase)}` : '';
            statusPhase.classList.toggle('hidden', !payload?.phase);
        }
        if (statusMessage) {
            statusMessage.textContent = payload?.message || '';
            statusMessage.classList.toggle('hidden', !payload?.message);
        }
        if (statusUpdated) {
            statusUpdated.textContent = payload?.updated_at
                ? `Updated ${new Date(payload.updated_at).toLocaleTimeString()}`
                : 'Waiting for updates';
        }
        renderLogs(payload?.logs || []);
        const hasError = status === 'failed' && payload?.error_message;
        if (statusError) {
            statusError.textContent = hasError ? payload.error_message : '';
            statusError.classList.toggle('hidden', !hasError);
        }
        const isRunning = status === 'running' || status === 'pending';
        if (progress) progress.classList.toggle('hidden', !isRunning);
        if (isRunning) {
            startPolling();
        } else {
            stopPolling();
            if (lastStatus === 'running' || lastStatus === 'pending') {
                window.setTimeout(() => window.location.reload(), 1200);
            }
        }
        lastStatus = status;
    };

    const fetchStatus = () => {
        // The status endpoint returns the latest clustering run state as JSON.
        fetch('{{ route('ai-insights.status') }}', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then((r) => r.json())
            .then(updateUi)
            .catch(() => {
                // Failures show a brief message rather than throwing.
                if (statusError) {
                    statusError.textContent = 'Unable to fetch status right now.';
                    statusError.classList.remove('hidden');
                }
            });
    };

    // Polling continues only while a run is pending or running.
    const startPolling = () => { if (!pollTimer) pollTimer = setInterval(fetchStatus, 5000); };
    const stopPolling = () => { if (pollTimer) { clearInterval(pollTimer); pollTimer = null; } };

    // Manual refresh is available for administrators.
    if (refresh) refresh.addEventListener('click', fetchStatus);
    fetchStatus();
})();
</script>

    </body>
</html>
