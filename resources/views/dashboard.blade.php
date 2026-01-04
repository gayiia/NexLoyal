<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'NexLoyal') }} - Dashboard</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        @vite(['resources/css/app.css'])
        <style>
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
                        <aside class="w-72 border-r border-slate-800/70 bg-slate-950/80 px-6 py-8 nl-panel">
                            <div class="flex items-center gap-3">
                            <div class="flex items-center gap-4 nl-animate-up nl-delay-1">
                                <div class="flex w-40 items-center justify-center">
                                    <img src="{{ URL::asset('build\Images\logo-light.png') }}" alt="NexLoyal" class="w-auto">
                                </div>
                            </div>

                            </div>

                            <nav class="mt-10 space-y-2 text-sm">
                                <a href="{{ route('dashboard') }}" class="flex items-center justify-between rounded-xl border border-slate-800 bg-slate-900/80 px-4 py-3 text-slate-100 nl-sidebar-link nl-sidebar-link-active">
                                    <span>Dashboard</span>
                                    <span class="text-xs text-slate-400 nl-text-muted">Overview</span>
                                </a>
                                <a href="{{ route('customers') }}" class="flex items-center justify-between rounded-xl border border-transparent px-4 py-3 text-slate-300 hover:border-slate-800 hover:bg-slate-900/60 nl-sidebar-link">
                                    <span>Customers</span>
                                    <span class="text-xs text-slate-500 nl-text-muted">Segments</span>
                                </a>
                                <a href="#" class="flex items-center justify-between rounded-xl border border-transparent px-4 py-3 text-slate-300 hover:border-slate-800 hover:bg-slate-900/60 nl-sidebar-link">
                                    <span>Coupons</span>
                                    <span class="text-xs text-slate-500 nl-text-muted">Rewards</span>
                                </a>
                                <a href="#" class="flex items-center justify-between rounded-xl border border-transparent px-4 py-3 text-slate-300 hover:border-slate-800 hover:bg-slate-900/60 nl-sidebar-link">
                                    <span>Notifications</span>
                                    <span class="text-xs text-slate-500 nl-text-muted">Engage</span>
                                </a>
                                <a href="#" class="flex items-center justify-between rounded-xl border border-transparent px-4 py-3 text-slate-300 hover:border-slate-800 hover:bg-slate-900/60 nl-sidebar-link">
                                    <span>Settings</span>
                                    <span class="text-xs text-slate-500 nl-text-muted">Rules</span>
                                </a>
                            </nav>

                            <div class="mt-10 rounded-2xl border border-slate-800 bg-slate-900/60 p-4 nl-panel-muted">
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-400 nl-text-muted">Webhook status</p>
                                <p class="mt-2 text-sm font-medium text-slate-100">Shopify data stream</p>
                                <p class="mt-1 text-xs text-slate-400 nl-text-muted">Listening for customers/create, orders/paid, refunds, cancellations.</p>
                                <div class="mt-3 flex items-center gap-2 text-xs text-slate-300 nl-text-muted">
                                    <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                                    Online (connect in Settings)
                                </div>
                            </div>
                            <div class="mt-6 rounded-2xl border border-slate-800 bg-slate-900/60 p-4 nl-panel-muted">
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-400 nl-text-muted">Account</p>
                                <p class="mt-2 text-sm font-medium text-slate-100">gayindu</p>
                                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                                    @csrf
                                    <button type="submit" class="w-full rounded-xl border border-slate-700 px-3 py-2 text-xs text-slate-200 hover:border-slate-500">
                                        Log out
                                    </button>
                                </form>
                            </div>
                        </aside>

                        <main class="flex-1 px-10 py-8">
                            <x-page-header eyebrow="Dashboard" title="Loyalty performance overview" subtitle="Charts pull from Shopify webhooks once connected.">
                                <x-slot name="actions">
                                    <button class="rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 nl-panel-muted">Export report</button>
                                    <button class="rounded-xl bg-sky-400 px-4 py-2 text-sm font-semibold text-slate-950 shadow-lg shadow-sky-500/30">Create campaign</button>
                                    <button id="theme-toggle" class="rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 nl-panel-muted" type="button">
                                        Switch theme
                                    </button>
                                </x-slot>
                            </x-page-header>
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

                            <section class="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-5">
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Points outstanding</p>
                                    <p class="mt-3 text-2xl font-semibold text-slate-50">1,284,300</p>
                                    <p class="mt-1 text-xs text-slate-400">+8.2% this month</p>
                                </div>
                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-5">
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Redemption rate</p>
                                    <p class="mt-3 text-2xl font-semibold text-slate-50">37.4%</p>
                                    <p class="mt-1 text-xs text-slate-400">+4.1% since last week</p>
                                </div>
                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-5">
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Active members</p>
                                    <p class="mt-3 text-2xl font-semibold text-slate-50">18,240</p>
                                    <p class="mt-1 text-xs text-slate-400">+620 new signups</p>
                                </div>
                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-5">
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Tier upgrades</p>
                                    <p class="mt-3 text-2xl font-semibold text-slate-50">1,102</p>
                                    <p class="mt-1 text-xs text-slate-400">Bronze -> Silver</p>
                                </div>
                            </section>

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
                                        <path d="M10 120 C60 60, 120 80, 180 40 C240 10, 300 40, 360 70 C420 100, 480 60, 590 30" stroke="#38bdf8" stroke-width="3" />
                                        <path d="M10 140 C70 110, 120 130, 180 90 C240 60, 300 90, 360 110 C420 130, 480 120, 590 80" stroke="#94a3b8" stroke-width="2" stroke-dasharray="6 6" />
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
                                    <div class="mt-6 grid grid-cols-2 gap-4">
                                        <div class="flex items-center justify-center">
                                            <svg class="h-32 w-32" viewBox="0 0 120 120">
                                                <circle cx="60" cy="60" r="48" stroke="#1e293b" stroke-width="12" fill="none" />
                                                <circle cx="60" cy="60" r="48" stroke="#38bdf8" stroke-width="12" fill="none" stroke-dasharray="140 160" stroke-linecap="round" transform="rotate(-90 60 60)" />
                                                <circle cx="60" cy="60" r="36" fill="#0f172a" />
                                                <text x="60" y="66" text-anchor="middle" fill="#e2e8f0" font-size="14">38%</text>
                                            </svg>
                                        </div>
                                        <div class="space-y-3 text-xs text-slate-300">
                                            <div class="flex items-center justify-between">
                                                <span>Amount off</span>
                                                <span>38%</span>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <span>Free shipping</span>
                                                <span>22%</span>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <span>Gift cards</span>
                                                <span>18%</span>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <span>Free product</span>
                                                <span>12%</span>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <span>Percentage off</span>
                                                <span>10%</span>
                                            </div>
                                        </div>
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
                                        <div>
                                            <div class="flex items-center justify-between">
                                                <span>Bronze</span><span>9,200</span>
                                            </div>
                                            <div class="mt-2 h-2 rounded-full bg-slate-800">
                                                <div class="h-2 rounded-full bg-amber-400" style="width: 62%"></div>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="flex items-center justify-between">
                                                <span>Silver</span><span>6,100</span>
                                            </div>
                                            <div class="mt-2 h-2 rounded-full bg-slate-800">
                                                <div class="h-2 rounded-full bg-slate-300" style="width: 41%"></div>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="flex items-center justify-between">
                                                <span>Gold</span><span>2,100</span>
                                            </div>
                                            <div class="mt-2 h-2 rounded-full bg-slate-800">
                                                <div class="h-2 rounded-full bg-yellow-300" style="width: 22%"></div>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="flex items-center justify-between">
                                                <span>Platinum</span><span>840</span>
                                            </div>
                                            <div class="mt-2 h-2 rounded-full bg-slate-800">
                                                <div class="h-2 rounded-full bg-sky-300" style="width: 12%"></div>
                                            </div>
                                        </div>
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
                                    <div class="mt-6 grid grid-cols-7 items-end gap-2 text-xs text-slate-400">
                                        <div class="h-16 rounded-lg bg-sky-500/70"></div>
                                        <div class="h-20 rounded-lg bg-sky-400/80"></div>
                                        <div class="h-10 rounded-lg bg-sky-500/60"></div>
                                        <div class="h-24 rounded-lg bg-sky-400"></div>
                                        <div class="h-18 rounded-lg bg-sky-500/70"></div>
                                        <div class="h-12 rounded-lg bg-sky-500/60"></div>
                                        <div class="h-22 rounded-lg bg-sky-400/80"></div>
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-6">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-100">Expiring points forecast</p>
                                            <p class="text-xs text-slate-400">Webhook + expiry scheduler</p>
                                        </div>
                                        <span class="text-xs text-slate-400">Next 8 weeks</span>
                                    </div>
                                    <svg class="mt-6 h-36 w-full" viewBox="0 0 600 140" fill="none">
                                        <path d="M20 120 L80 90 L140 95 L200 70 L260 60 L320 75 L380 50 L440 60 L500 40 L580 55" stroke="#f97316" stroke-width="3" />
                                    </svg>
                                </div>

                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-6">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-100">Streak engagement</p>
                                            <p class="text-xs text-slate-400">Webhook: activity + login streaks</p>
                                        </div>
                                        <span class="text-xs text-slate-400">Daily</span>
                                    </div>
                                    <svg class="mt-6 h-36 w-full" viewBox="0 0 600 140" fill="none">
                                        <path d="M20 100 C90 110, 140 80, 200 90 C260 100, 320 60, 380 70 C440 80, 500 50, 580 40" stroke="#34d399" stroke-width="3" />
                                    </svg>
                                </div>

                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-6">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-100">Mystery box outcomes</p>
                                            <p class="text-xs text-slate-400">Webhook: mystery reward claims</p>
                                        </div>
                                        <span class="text-xs text-slate-400">Last 30 days</span>
                                    </div>
                                    <div class="mt-6 grid grid-cols-5 items-end gap-3 text-xs text-slate-400">
                                        <div class="h-14 rounded-lg bg-purple-400/70"></div>
                                        <div class="h-24 rounded-lg bg-purple-300"></div>
                                        <div class="h-10 rounded-lg bg-purple-500/70"></div>
                                        <div class="h-20 rounded-lg bg-purple-300/80"></div>
                                        <div class="h-16 rounded-lg bg-purple-400/90"></div>
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-6">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-100">Notification click-through</p>
                                            <p class="text-xs text-slate-400">Webhook: message delivery + clicks</p>
                                        </div>
                                        <span class="text-xs text-slate-400">Last 14 days</span>
                                    </div>
                                    <svg class="mt-6 h-36 w-full" viewBox="0 0 600 140" fill="none">
                                        <path d="M20 110 L80 90 L140 100 L200 70 L260 60 L320 80 L380 55 L440 65 L500 50 L580 45" stroke="#a855f7" stroke-width="3" />
                                    </svg>
                                </div>

                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-6">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-100">Segment distribution</p>
                                            <p class="text-xs text-slate-400">Clustered by earn vs redeem ratio</p>
                                        </div>
                                        <span class="text-xs text-slate-400">Weekly</span>
                                    </div>
                                    <div class="mt-6 grid gap-3 text-xs text-slate-300">
                                        <div class="flex items-center justify-between">
                                            <span>Low redeemers</span>
                                            <span>43%</span>
                                        </div>
                                        <div class="h-2 rounded-full bg-slate-800">
                                            <div class="h-2 rounded-full bg-rose-400" style="width: 43%"></div>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span>Balanced redeemers</span>
                                            <span>37%</span>
                                        </div>
                                        <div class="h-2 rounded-full bg-slate-800">
                                            <div class="h-2 rounded-full bg-emerald-400" style="width: 37%"></div>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span>High redeemers</span>
                                            <span>20%</span>
                                        </div>
                                        <div class="h-2 rounded-full bg-slate-800">
                                            <div class="h-2 rounded-full bg-sky-400" style="width: 20%"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-6">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-100">Customer lifetime value by tier</p>
                                            <p class="text-xs text-slate-400">Webhook: orders/paid + tier mapping</p>
                                        </div>
                                        <span class="text-xs text-slate-400">Quarterly</span>
                                    </div>
                                    <div class="mt-6 grid grid-cols-4 items-end gap-4 text-xs text-slate-400">
                                        <div class="h-16 rounded-lg bg-amber-400/70"></div>
                                        <div class="h-24 rounded-lg bg-slate-300"></div>
                                        <div class="h-32 rounded-lg bg-yellow-300"></div>
                                        <div class="h-36 rounded-lg bg-sky-300"></div>
                                    </div>
                                </div>
                            </section>
                        </main>
                    </div>
                </div>
            </div>
        </div>
        <script>
            (function () {
                const storageKey = 'nl-theme';
                const body = document.body;
                const button = document.getElementById('theme-toggle');
                if (!button) return;

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
                    const next = body.classList.contains('nl-theme-light') ? 'dark' : 'light';
                    localStorage.setItem(storageKey, next);
                    applyTheme(next);
                });
            })();
        </script>
    </body>
</html>
