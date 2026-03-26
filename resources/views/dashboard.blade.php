{{-- This view renders the admin dashboard with summary KPIs, charts, and recent trends. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        {{-- The title uses the app name configuration with a fallback for local/dev environments. --}}
        <title>{{ config('app.name', 'NexLoyal') }} - Dashboard</title>
        {{-- Preconnect and load the UI font used across the admin experience. --}}
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        {{-- Vite builds and injects the compiled CSS for this page. --}}
        @vite(['resources/css/app.css'])
        <style>
            {{-- These styles toggle light-mode colors and dashboard filter styles. --}}
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
            .nl-theme-light .nl-chip {
                background-color: rgba(226, 232, 240, 0.8);
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
            .nl-filter-pill {
                font-size: 11px;
                letter-spacing: 0.12em;
                text-transform: uppercase;
                padding: 4px 10px;
                border-radius: 999px;
                border: 1px solid rgba(51, 65, 85, 0.8);
                background: rgba(15, 23, 42, 0.7);
                color: #e2e8f0;
                transition: border-color 0.2s ease, background-color 0.2s ease;
            }
            .nl-filter-pill-active {
                border-color: rgba(56, 189, 248, 0.7);
                background: rgba(14, 116, 144, 0.35);
                color: #e0f2fe;
            }
            .nl-filter-input {
                height: 32px;
                font-size: 12px;
            }
            .nl-filter-label {
                font-size: 11px;
            }
            .nl-filter-action {
                height: 32px;
                font-size: 12px;
            }
            .nl-theme-light .nl-filter-pill {
                border-color: rgba(148, 163, 184, 0.7);
                background: rgba(226, 232, 240, 0.8);
                color: #0f172a;
            }
            .nl-theme-light .nl-filter-pill-active {
                border-color: rgba(14, 116, 144, 0.4);
                background: rgba(14, 116, 144, 0.15);
                color: #0f172a;
            }
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
                            {{-- The header displays primary dashboard actions. --}}
                            <x-page-header eyebrow="" title="Dashobaord" subtitle="">
                                <x-slot name="actions">
                                    {{-- Export and campaign actions are placeholders for reporting workflows. --}}
                                    <button class="rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 nl-panel-muted">Export report</button>
                                    <button class="rounded-xl bg-sky-400 px-4 py-2 text-sm font-semibold text-slate-950 shadow-lg shadow-sky-500/30">Create campaign</button>
                                    <button id="theme-toggle" class="rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 nl-panel-muted" type="button">
                                        Switch theme
                                    </button>
                                </x-slot>
                            </x-page-header>
                            {{-- Filters are visual controls only; no backend logic is wired here yet. --}}
                            <section class="mt-6 rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-5 nl-panel">
                                <div class="flex flex-wrap items-center gap-2 border-b border-slate-800/70 pb-3">
                                    <span class="text-xs uppercase tracking-[0.3em] text-slate-400 nl-text-muted">Filters</span>
                                    <button class="nl-filter-pill nl-filter-pill-active" aria-pressed="true">All</button>
                                </div>
                                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-[repeat(4,minmax(0,1fr))_auto] items-end">
                                    <div class="flex flex-col gap-1">
                                        <label class="nl-filter-label uppercase tracking-[0.2em] text-slate-400 nl-text-muted">Date range</label>
                                        <select class="nl-filter-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-xs text-slate-200 nl-chip">
                                            <option>Last 7 days</option>
                                            <option selected>Last 30 days</option>
                                            <option>Last 90 days</option>
                                            <option>Custom</option>
                                        </select>
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label class="nl-filter-label uppercase tracking-[0.2em] text-slate-400 nl-text-muted">Tier</label>
                                        <select class="nl-filter-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-xs text-slate-200 nl-chip">
                                            <option>All tiers</option>
                                            <option>Bronze</option>
                                            <option>Silver</option>
                                            <option>Gold</option>
                                            <option>Platinum</option>
                                        </select>
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label class="nl-filter-label uppercase tracking-[0.2em] text-slate-400 nl-text-muted">Segment</label>
                                        <select class="nl-filter-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-xs text-slate-200 nl-chip">
                                            <option>All segments</option>
                                            <option>Low redeemers</option>
                                            <option>Balanced redeemers</option>
                                            <option>High redeemers</option>
                                        </select>
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label class="nl-filter-label uppercase tracking-[0.2em] text-slate-400 nl-text-muted">Reward type</label>
                                        <select class="nl-filter-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-xs text-slate-200 nl-chip">
                                            <option>All rewards</option>
                                            <option>Amount off</option>
                                            <option>Percentage off</option>
                                            <option>Free shipping</option>
                                            <option>Free product</option>
                                            <option>Gift card</option>
                                        </select>
                                    </div>
                                    <div class="flex items-center justify-start gap-2 xl:justify-end">
                                        <button class="nl-filter-action rounded-xl border border-slate-700 px-3 text-slate-200">Clear</button>
                                        <button class="nl-filter-action rounded-xl bg-sky-400 px-4 font-semibold text-slate-950 shadow-lg shadow-sky-500/30">Apply filters</button>
                                    </div>
                                </div>
                            </section>

                            {{-- KPI tiles summarize high-level loyalty metrics. --}}
                            <section class="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-5">
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Points outstanding</p>
                                    <p class="mt-3 text-2xl font-semibold text-slate-50">{{ number_format($stats['points_outstanding']) }}</p>
                                    <p class="mt-1 text-xs text-slate-400">Current customer balances.</p>
                                </div>
                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-5">
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Redemption rate</p>
                                    <p class="mt-3 text-2xl font-semibold text-slate-50">{{ number_format($stats['redemption_rate'], 1) }}%</p>
                                    <p class="mt-1 text-xs text-slate-400">Last 30 days spend vs earn.</p>
                                </div>
                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-5">
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Active members</p>
                                    <p class="mt-3 text-2xl font-semibold text-slate-50">{{ number_format($stats['active_members']) }}</p>
                                    <p class="mt-1 text-xs text-slate-400">Customers in the loyalty program.</p>
                                </div>
                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-5">
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Mystery box claims</p>
                                    <p class="mt-3 text-2xl font-semibold text-slate-50">{{ number_format($stats['mystery_box_claims']) }}</p>
                                    <p class="mt-1 text-xs text-slate-400">Claims in the last 30 days.</p>
                                </div>
                            </section>

                            {{-- Charts and breakdowns provide a deeper view into earned, spent, and engagement data. --}}
                            <section class="mt-8 grid gap-6 lg:grid-cols-2">
                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-6">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-100">Points earned vs redeemed</p>
                                            <p class="text-xs text-slate-400">Webhook: orders/paid, reward redemptions</p>
                                        </div>
                                        <span class="text-xs text-slate-400">Last 30 days</span>
                                    </div>
                                    <svg class="mt-6 h-40 w-full" viewBox="0 0 600 160" fill="none">
                                        <path id="points-earned-line" d="" stroke="#38bdf8" stroke-width="3" />
                                        <path id="points-spent-line" d="" stroke="#94a3b8" stroke-width="2" stroke-dasharray="6 6" />
                                    </svg>
                                </div>

                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-6">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-100">Redemption mix by reward type</p>
                                            <p class="text-xs text-slate-400">Webhook: reward redemption logs</p>
                                        </div>
                                        <span class="text-xs text-slate-400">This month</span>
                                    </div>
                                    <div class="mt-6 space-y-3 text-xs text-slate-300">
                                        {{-- Each row shows a reward type and its share of redemptions. --}}
                                        @forelse ($redemption_mix as $label => $stats)
                                            <div>
                                                <div class="flex items-center justify-between">
                                                    <span>{{ $label }}</span>
                                                    <span>{{ $stats['percent'] }}% ({{ $stats['count'] }})</span>
                                                </div>
                                                <div class="mt-2 h-2 rounded-full bg-slate-800">
                                                    <div class="h-2 rounded-full bg-sky-400" style="width: {{ $stats['percent'] }}%"></div>
                                                </div>
                                            </div>
                                        @empty
                                            <p class="text-xs text-slate-400">No redemptions yet.</p>
                                        @endforelse
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-6">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-100">Tier distribution</p>
                                            <p class="text-xs text-slate-400">Webhook: customers/create, tier updates</p>
                                        </div>
                                        <span class="text-xs text-slate-400">Active members</span>
                                    </div>
                                    <div class="mt-6 space-y-3 text-xs text-slate-300">
                                        {{-- Tier bars show distribution of active members across tiers. --}}
                                        @forelse ($tier_distribution as $tier)
                                            <div>
                                                <div class="flex items-center justify-between">
                                                    <span>{{ $tier['title'] }}</span><span>{{ number_format($tier['count']) }}</span>
                                                </div>
                                                <div class="mt-2 h-2 rounded-full bg-slate-800">
                                                    <div class="h-2 rounded-full bg-sky-400" style="width: {{ $tier['percent'] }}%"></div>
                                                </div>
                                            </div>
                                        @empty
                                            <p class="text-xs text-slate-400">No tier data yet.</p>
                                        @endforelse
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-6">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-100">Coupon redemptions</p>
                                            <p class="text-xs text-slate-400">Webhook: orders/paid, coupons applied</p>
                                        </div>
                                        <span class="text-xs text-slate-400">Weekly</span>
                                    </div>
                                    @php $maxWeekly = max($series['weekly_redemptions'] ?: [0]); @endphp
                                    {{-- Weekly bars are scaled relative to the highest redemption count. --}}
                                    <div class="mt-6 overflow-x-auto nl-mobile-scroll">
                                        <div class="grid grid-cols-7 items-end gap-2 text-[10px] text-slate-400 sm:text-xs">
                                            @foreach ($series['weekly_redemptions'] as $index => $count)
                                                @php
                                                    $height = $maxWeekly > 0 ? max(8, round(($count / $maxWeekly) * 96)) : 8;
                                                    $label = \Illuminate\Support\Carbon::parse($series['days_7'][$index])->format('m/d');
                                                @endphp
                                                <div class="flex flex-col items-center gap-2">
                                                    <div class="w-6 rounded-lg bg-sky-400/80" style="height: {{ $height }}px"></div>
                                                    <span>{{ $label }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-6">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-100">Mystery box outcomes</p>
                                            <p class="text-xs text-slate-400">Claimed rewards in the last 30 days.</p>
                                        </div>
                                        <span class="text-xs text-slate-400">Top rewards</span>
                                    </div>
                                    @php $maxMystery = max($mystery_box_outcomes->pluck('count')->all() ?: [0]); @endphp
                                    {{-- Reward bars are scaled relative to the most frequent outcome. --}}
                                    <div class="mt-6 overflow-x-auto nl-mobile-scroll">
                                        <div class="grid grid-cols-5 items-end gap-2 text-[10px] text-slate-400 sm:gap-3">
                                            @foreach ($mystery_box_outcomes as $item)
                                                @php
                                                    $height = $maxMystery > 0 ? max(10, round(($item['count'] / $maxMystery) * 96)) : 10;
                                                @endphp
                                                <div class="flex flex-col items-center gap-2">
                                                    <div class="w-8 rounded-lg bg-purple-400/80" style="height: {{ $height }}px"></div>
                                                    <span class="text-[10px] text-center text-slate-400">{{ \Illuminate\Support\Str::limit($item['title'], 8) }}</span>
                                                </div>
                                            @endforeach
                                            {{-- This empty state preserves layout when no outcomes exist. --}}
                                            @if ($mystery_box_outcomes->isEmpty())
                                                <p class="col-span-5 text-center text-xs text-slate-400">No mystery box claims yet.</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-6">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-100">Exclusive chat activity</p>
                                            <p class="text-xs text-slate-400">Poll votes over the last 14 days.</p>
                                        </div>
                                        <span class="text-xs text-slate-400">Last 14 days</span>
                                    </div>
                                    <svg class="mt-6 h-36 w-full" viewBox="0 0 600 140" fill="none">
                                        <path id="chat-votes-line" d="" stroke="#a855f7" stroke-width="3" />
                                    </svg>
                                </div>
                            </section>
                        </main>
                    </div>
                </div>
            </div>
        </div>
        <script>
            (function () {
                // Store the theme preference locally so it persists between visits.
                const storageKey = 'nl-theme';
                const body = document.body;
                const button = document.getElementById('theme-toggle');
                if (!button) return;

                // Apply light or dark styles and update the button label.
                const applyTheme = (theme) => {
                    if (theme === 'light') {
                        body.classList.add('nl-theme-light');
                    } else {
                        body.classList.remove('nl-theme-light');
                    }
                    button.textContent = theme === 'light' ? 'Switch to dark' : 'Switch to light';
                };

                const stored = localStorage.getItem(storageKey);
                applyTheme(stored || 'dark');

                button.addEventListener('click', () => {
                    // Toggle the theme and persist the choice.
                    const next = body.classList.contains('nl-theme-light') ? 'dark' : 'light';
                    localStorage.setItem(storageKey, next);
                    applyTheme(next);
                });

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
                // Build simple SVG paths to avoid heavy chart dependencies.
                const buildLinePath = (values, width, height, padding) => {
                    if (!values.length) {
                        return '';
                    }
                    const maxValue = Math.max(...values, 1);
                    const innerWidth = width - padding * 2;
                    const innerHeight = height - padding * 2;
                    if (values.length === 1) {
                        const y = height - padding - (values[0] / maxValue) * innerHeight;
                        return `M${(padding + innerWidth / 2).toFixed(1)} ${y.toFixed(1)}`;
                    }
                    const step = innerWidth / (values.length - 1);
                    return values.map((value, index) => {
                        const x = padding + step * index;
                        const y = height - padding - (value / maxValue) * innerHeight;
                        return `${index === 0 ? 'M' : 'L'}${x.toFixed(1)} ${y.toFixed(1)}`;
                    }).join(' ');
                };

                const pointsEarned = @json($series['earned']);
                const pointsSpent = @json($series['spent']);
                const chatVotes = @json($series['chat_votes']);

                const earnedPath = document.getElementById('points-earned-line');
                const spentPath = document.getElementById('points-spent-line');
                const chatPath = document.getElementById('chat-votes-line');

                if (earnedPath && spentPath) {
                    // Earned and spent use different styles to show contrast.
                    earnedPath.setAttribute('d', buildLinePath(pointsEarned, 600, 160, 10));
                    spentPath.setAttribute('d', buildLinePath(pointsSpent, 600, 160, 10));
                }

                if (chatPath) {
                    // Chat votes use a separate chart size and padding.
                    chatPath.setAttribute('d', buildLinePath(chatVotes, 600, 140, 12));
                }
            })();
        </script>
    </body>
</html>
