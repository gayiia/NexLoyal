<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'NexLoyal') }} - Customer</title>
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
            .nl-history-badge {
                border-radius: 999px;
                padding: 4px 10px;
                font-size: 11px;
                font-weight: 600;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            .nl-history-earned {
                background: rgba(34, 197, 94, 0.18);
                color: #bbf7d0;
            }
            .nl-history-redeemed {
                background: rgba(248, 113, 113, 0.18);
                color: #fecaca;
            }
            .nl-history-pending {
                background: rgba(148, 163, 184, 0.2);
                color: #e2e8f0;
            }
        </style>
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
        <div class="min-h-screen bg-[radial-gradient(900px_circle_at_top,rgba(56,189,248,0.18),transparent_60%)]">
            <div class="min-h-screen bg-[radial-gradient(700px_circle_at_bottom,rgba(30,64,175,0.22),transparent_60%)]">
                <div class="min-h-screen bg-[linear-gradient(120deg,rgba(15,23,42,0.9),rgba(2,6,23,0.95))] nl-shell">
                    <div class="flex min-h-screen">
                        @include('partials.admin-sidebar')

                        <main class="flex-1 px-10 py-8">
                            <x-page-header eyebrow="Customer" title="{{ $customer->full_name ?: 'Unnamed customer' }}" subtitle="Shopify ID {{ $customer->shopify_id }}" breadcrumb="Customers / View">
                                <x-slot name="actions">
                                    <a class="rounded-xl border border-slate-700 px-4 py-2 text-slate-200" href="{{ route('customers') }}">Back to customers</a>
                                    <button id="theme-toggle" class="rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-2 text-xs text-slate-200 nl-panel-muted" type="button">
                                        Switch theme
                                    </button>
                                </x-slot>
                            </x-page-header>

                            <section class="mt-6 grid gap-4 lg:grid-cols-3">
                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-6">
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Contact</p>
                                    <div class="mt-4 space-y-3 text-sm">
                                        <div>
                                            <p class="text-xs text-slate-400">Email</p>
                                            <p class="text-slate-100">{{ $customer->email ?: '—' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-400">Phone</p>
                                            <p class="text-slate-100">{{ $customer->phone ?: '—' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-400">Status</p>
                                            <p class="text-slate-100">{{ $customer->status ?: '—' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-400">Birthday</p>
                                            <p class="text-slate-100">{{ $customer->birthday ? $customer->birthday->format('M d, Y') : '—' }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-6">
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Activity</p>
                                    <div class="mt-4 space-y-3 text-sm">
                                        <div>
                                            <p class="text-xs text-slate-400">Orders</p>
                                            <p class="text-slate-100">{{ $customer->orders_count }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-400">Total spent</p>
                                            <p class="text-slate-100">
                                                {{ $customer->currency ? $customer->currency.' ' : '' }}{{ number_format($customer->total_spent, 2) }}
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-400">Joined</p>
                                            <p class="text-slate-100">{{ optional($customer->shopify_created_at)->format('M d, Y') ?: '—' }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-6">
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Notes</p>
                                    <p class="mt-4 text-sm text-slate-300">
                                        Syncs via Shopify webhooks. Loyalty tiering and notes can be added after profile enrichment.
                                    </p>
                                </div>
                            </section>

                            <section class="mt-6 rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-6">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-100">Point history</p>
                                        <p class="text-xs text-slate-400">Latest earn and redeem activity.</p>
                                    </div>
                                    <a class="rounded-xl border border-slate-700 px-3 py-2 text-xs text-slate-200" href="{{ route('customers.show.export', $customer) }}">
                                        Export CSV
                                    </a>
                                </div>

                                <div class="mt-4 overflow-x-auto">
                                    <table class="w-full text-left text-xs">
                                        <thead class="text-slate-400">
                                            <tr class="border-b border-slate-800/60">
                                                <th class="px-3 py-2">Status</th>
                                                <th class="px-3 py-2">Points</th>
                                                <th class="px-3 py-2">Title</th>
                                                <th class="px-3 py-2">Type</th>
                                                <th class="px-3 py-2">Time</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-800/60">
                                            @forelse ($transactions as $transaction)
                                                @php
                                                    $isEarn = $transaction['direction'] === 'EARN';
                                                    $statusLabel = $isEarn ? 'Earned' : 'Redeemed';
                                                    $pointsLabel = ($isEarn ? '+' : '-') . $transaction['points'];
                                                @endphp
                                                <tr>
                                                    <td class="px-3 py-3">
                                                        <span class="nl-history-badge {{ $isEarn ? 'nl-history-earned' : 'nl-history-redeemed' }}">
                                                            {{ $statusLabel }}
                                                        </span>
                                                        @if ($transaction['status'] === 'PENDING')
                                                            <span class="ml-2 nl-history-badge nl-history-pending">In progress</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-3">
                                                        <span class="{{ $isEarn ? 'text-emerald-300' : 'text-rose-300' }}">{{ $pointsLabel }}</span>
                                                    </td>
                                                    <td class="px-3 py-3 text-slate-200">{{ $transaction['title'] }}</td>
                                                    <td class="px-3 py-3 text-slate-300">{{ $transaction['type'] }}</td>
                                                    <td class="px-3 py-3 text-slate-400">
                                                        {{ \Carbon\Carbon::parse($transaction['created_at'])->format('Y-m-d H:i:s') }}
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="px-3 py-6 text-center text-slate-400">No point activity yet.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>

                                <div class="mt-4 flex items-center justify-between text-xs text-slate-400">
                                    <div>
                                        Showing {{ $transactions->firstItem() ?? 0 }} to {{ $transactions->lastItem() ?? 0 }} of {{ $transactions->total() }} entries
                                    </div>
                                    <div class="flex items-center gap-2">
                                        @php
                                            $current = $transactions->currentPage();
                                            $last = $transactions->lastPage();
                                            $start = max($current - 1, 1);
                                            $end = min($current + 1, $last);
                                        @endphp
                                        <a class="rounded-lg border border-slate-700 px-3 py-1 {{ $transactions->onFirstPage() ? 'pointer-events-none text-slate-600' : 'text-slate-200' }}" href="{{ $transactions->previousPageUrl() ?? '#' }}">Prev</a>
                                        @for ($page = $start; $page <= $end; $page++)
                                            <a class="rounded-lg border border-slate-700 px-3 py-1 {{ $page === $current ? 'bg-slate-800 text-slate-100' : 'text-slate-300' }}" href="{{ $transactions->url($page) }}">{{ $page }}</a>
                                        @endfor
                                        <a class="rounded-lg border border-slate-700 px-3 py-1 {{ $current === $last ? 'pointer-events-none text-slate-600' : 'text-slate-200' }}" href="{{ $transactions->nextPageUrl() ?? '#' }}">Next</a>
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

                const applyTheme = (theme) => {
                    if (theme === 'light') {
                        body.classList.add('nl-theme-light');
                    } else {
                        body.classList.remove('nl-theme-light');
                    }
                };

                const stored = localStorage.getItem(storageKey);
                applyTheme(stored || 'dark');

                if (button) {
                    button.textContent = body.classList.contains('nl-theme-light') ? 'Switch to dark' : 'Switch to light';
                    button.addEventListener('click', () => {
                        const next = body.classList.contains('nl-theme-light') ? 'dark' : 'light';
                        localStorage.setItem(storageKey, next);
                        applyTheme(next);
                        button.textContent = next === 'light' ? 'Switch to dark' : 'Switch to light';
                    });
                }

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
        </script>
    </body>
</html>

