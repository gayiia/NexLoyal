<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'NexLoyal') }} - Customers</title>
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
            .nl-filter-input {
                height: 34px;
                font-size: 12px;
            }
            .nl-filter-label {
                font-size: 11px;
            }
            .nl-export-button {
                background: linear-gradient(135deg, #10b981, #22c55e);
                color: #052e1b;
                font-weight: 600;
                padding: 10px 18px;
                border-radius: 12px;
                box-shadow: 0 12px 20px rgba(16, 185, 129, 0.25);
            }
            .nl-view-button {
                border: 1px solid rgba(148, 163, 184, 0.4);
                background: rgba(30, 41, 59, 0.5);
                padding: 6px 14px;
                border-radius: 999px;
                font-size: 11px;
                letter-spacing: 0.08em;
                text-transform: uppercase;
            }
            .nl-table-head {
                background: rgba(15, 23, 42, 0.6);
            }
            .nl-table-row:hover {
                background: rgba(30, 41, 59, 0.45);
            }
            .nl-theme-light .nl-table-head {
                background: rgba(226, 232, 240, 0.8);
            }
            .nl-theme-light .nl-table-row:hover {
                background: rgba(226, 232, 240, 0.8);
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
                                <a href="{{ route('dashboard') }}" class="flex items-center justify-between rounded-xl border border-transparent px-4 py-3 text-slate-300 hover:border-slate-800 hover:bg-slate-900/60 nl-sidebar-link">
                                    <span>Dashboard</span>
                                    <span class="text-xs text-slate-500 nl-text-muted">Overview</span>
                                </a>
                                <a href="{{ route('customers') }}" class="flex items-center justify-between rounded-xl border border-slate-800 bg-slate-900/80 px-4 py-3 text-slate-100 nl-sidebar-link nl-sidebar-link-active">
                                    <span>Customers</span>
                                    <span class="text-xs text-slate-400 nl-text-muted">Segments</span>
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
                        </aside>

                        <main class="flex-1 px-10 py-8">
                            <header class="flex flex-wrap items-center justify-between gap-4">
                                <div>
                                    <p class="text-xs uppercase tracking-[0.3em] text-slate-400 nl-text-muted">Customers</p>
                                    <h1 class="text-3xl font-semibold text-slate-50">Customers</h1>
                                </div>
                                <div class="flex items-center gap-3 text-xs text-slate-400">
                                    <span>Customers / Customers</span>
                                    <button id="theme-toggle" class="rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-2 text-xs text-slate-200 nl-panel-muted" type="button">
                                        Switch theme
                                    </button>
                                </div>
                            </header>

                            <section class="mt-6 overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel">
                                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800/70 px-6 py-4">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-100">Customer List</p>
                                    </div>
                                    <button class="nl-export-button text-xs">Export Excel</button>
                                </div>

                                <div class="px-6 py-5">
                                    <form method="GET" class="space-y-5">
                                        <div class="flex items-center gap-2 text-xs font-semibold text-slate-300">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                <path d="M3 5h18l-7 8v5l-4 2v-7L3 5z" />
                                            </svg>
                                            Filter
                                        </div>

                                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                            <div class="flex flex-col gap-2">
                                                <label class="nl-filter-label uppercase tracking-[0.2em] text-slate-400 nl-text-muted">Name</label>
                                                <select name="name" class="nl-filter-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200 nl-chip">
                                                    <option value="all" @selected(request('name', 'all') === 'all')>All</option>
                                                    <option value="has" @selected(request('name') === 'has')>Has name</option>
                                                    <option value="missing" @selected(request('name') === 'missing')>Missing</option>
                                                </select>
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <label class="nl-filter-label uppercase tracking-[0.2em] text-slate-400 nl-text-muted">Email</label>
                                                <select name="email" class="nl-filter-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200 nl-chip">
                                                    <option value="all" @selected(request('email', 'all') === 'all')>All</option>
                                                    <option value="has" @selected(request('email') === 'has')>Has email</option>
                                                    <option value="missing" @selected(request('email') === 'missing')>Missing</option>
                                                </select>
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <label class="nl-filter-label uppercase tracking-[0.2em] text-slate-400 nl-text-muted">Mobile</label>
                                                <select name="mobile" class="nl-filter-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200 nl-chip">
                                                    <option value="all" @selected(request('mobile', 'all') === 'all')>All</option>
                                                    <option value="has" @selected(request('mobile') === 'has')>Has mobile</option>
                                                    <option value="missing" @selected(request('mobile') === 'missing')>Missing</option>
                                                </select>
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <label class="nl-filter-label uppercase tracking-[0.2em] text-slate-400 nl-text-muted">Status</label>
                                                <select name="status" class="nl-filter-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200 nl-chip">
                                                    <option value="all" @selected(request('status', 'all') === 'all')>All</option>
                                                    <option value="enabled" @selected(request('status') === 'enabled')>Enabled</option>
                                                    <option value="disabled" @selected(request('status') === 'disabled')>Disabled</option>
                                                    <option value="invited" @selected(request('status') === 'invited')>Invited</option>
                                                    <option value="declined" @selected(request('status') === 'declined')>Declined</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-slate-300">
                                            <div class="flex items-center gap-2">
                                                <span>Show</span>
                                                <select name="per_page" class="rounded-lg border border-slate-700 bg-slate-950/60 px-2 py-1 text-xs text-slate-200 nl-chip">
                                                    @foreach ([10, 25, 50] as $size)
                                                        <option value="{{ $size }}" @selected((int) request('per_page', 10) === $size)>{{ $size }}</option>
                                                    @endforeach
                                                </select>
                                                <span>entries</span>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span>Search:</span>
                                                <input name="search" class="h-7 w-44 rounded-lg border border-slate-700 bg-slate-950/60 px-2 text-xs text-slate-200" type="text" value="{{ request('search') }}" placeholder="Search">
                                                <button type="submit" class="rounded-lg bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-900">Apply</button>
                                                <a class="rounded-lg border border-slate-700 px-3 py-1 text-xs text-slate-200" href="{{ route('customers') }}">Reset</a>
                                            </div>
                                        </div>
                                    </form>

                                    <div class="mt-4 overflow-x-auto">
                                        <table class="w-full text-left text-xs">
                                            <thead class="nl-table-head text-slate-300">
                                                <tr>
                                                    <th class="px-4 py-3 font-semibold">No</th>
                                                    <th class="px-4 py-3 font-semibold">Name</th>
                                                    <th class="px-4 py-3 font-semibold">Email</th>
                                                    <th class="px-4 py-3 font-semibold">Phone</th>
                                                    <th class="px-4 py-3 font-semibold">Status</th>
                                                    <th class="px-4 py-3 text-center font-semibold">View</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-800/80 text-slate-200">
                                                @forelse ($customers as $customer)
                                                    <tr class="nl-table-row">
                                                        <td class="px-4 py-4">{{ ($customers->currentPage() - 1) * $customers->perPage() + $loop->iteration }}</td>
                                                        <td class="px-4 py-4">{{ $customer->full_name ?: 'Unnamed' }}</td>
                                                        <td class="px-4 py-4 text-slate-300">{{ $customer->email ?: '—' }}</td>
                                                        <td class="px-4 py-4 text-slate-300">{{ $customer->phone ?: '—' }}</td>
                                                        <td class="px-4 py-4 text-slate-300">{{ $customer->status ?: '—' }}</td>
                                                        <td class="px-4 py-4 text-center">
                                                            <a class="nl-view-button text-slate-200" href="{{ route('customers.show', $customer) }}">View</a>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">No customers yet. Shopify webhook sync will populate this list.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-400">
                                        <div>
                                            Showing {{ $customers->firstItem() ?? 0 }} to {{ $customers->lastItem() ?? 0 }} of {{ $customers->total() }} entries
                                        </div>
                                        <div class="flex items-center gap-2">
                                            @php
                                                $current = $customers->currentPage();
                                                $last = $customers->lastPage();
                                                $start = max($current - 1, 1);
                                                $end = min($current + 1, $last);
                                            @endphp
                                            <a class="rounded-lg border border-slate-700 px-3 py-1 {{ $customers->onFirstPage() ? 'pointer-events-none text-slate-600' : 'text-slate-200' }}" href="{{ $customers->previousPageUrl() ?? '#' }}">Prev</a>
                                            @for ($page = $start; $page <= $end; $page++)
                                                <a class="rounded-lg border border-slate-700 px-3 py-1 {{ $page === $current ? 'bg-slate-800 text-slate-100' : 'text-slate-300' }}" href="{{ $customers->url($page) }}">{{ $page }}</a>
                                            @endfor
                                            <a class="rounded-lg border border-slate-700 px-3 py-1 {{ $current === $last ? 'pointer-events-none text-slate-600' : 'text-slate-200' }}" href="{{ $customers->nextPageUrl() ?? '#' }}">Next</a>
                                        </div>
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
                    if (button) {
                        button.textContent = theme === 'light' ? 'Switch to dark' : 'Switch to light';
                    }
                };

                const stored = localStorage.getItem(storageKey);
                applyTheme(stored || 'dark');

                if (button) {
                    button.addEventListener('click', () => {
                        const next = body.classList.contains('nl-theme-light') ? 'dark' : 'light';
                        localStorage.setItem(storageKey, next);
                        applyTheme(next);
                    });
                }
            })();
        </script>
    </body>
</html>
