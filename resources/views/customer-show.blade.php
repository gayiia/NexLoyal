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
                                <a href="{{ route('coupons') }}" class="flex items-center justify-between rounded-xl border border-transparent px-4 py-3 text-slate-300 hover:border-slate-800 hover:bg-slate-900/60 nl-sidebar-link">
                                    <span>Coupons</span>
                                    <span class="text-xs text-slate-500 nl-text-muted">Rewards</span>
                                </a>
                                <a href="#" class="flex items-center justify-between rounded-xl border border-transparent px-4 py-3 text-slate-300 hover:border-slate-800 hover:bg-slate-900/60 nl-sidebar-link">
                                    <span>Notifications</span>
                                    <span class="text-xs text-slate-500 nl-text-muted">Engage</span>
                                </a>
                                <div>
                                    <button id="settings-toggle" type="button" class="flex w-full items-center justify-between rounded-xl border border-transparent px-4 py-3 text-slate-300 hover:border-slate-800 hover:bg-slate-900/60 nl-sidebar-link">
                                        <span>Settings</span>
                                        <span class="text-xs text-slate-500 nl-text-muted">Rules</span>
                                    </button>
                                    <div id="settings-menu" class="mt-2 hidden space-y-1 pl-4 text-xs">
                                        <a href="{{ route('profile.edit') }}" class="block rounded-lg px-3 py-2 text-slate-300 hover:bg-slate-900/60">Profile</a>
                                        <a href="{{ route('user-password.edit') }}" class="block rounded-lg px-3 py-2 text-slate-300 hover:bg-slate-900/60">Password</a>
                                        <a href="{{ route('two-factor.show') }}" class="block rounded-lg px-3 py-2 text-slate-300 hover:bg-slate-900/60">Two-Factor Auth</a>
                                        <a href="{{ route('appearance.edit') }}" class="block rounded-lg px-3 py-2 text-slate-300 hover:bg-slate-900/60">Appearance</a>
                                        <a href="{{ route('customer-groups') }}" class="block rounded-lg px-3 py-2 text-slate-300 hover:bg-slate-900/60">Customer groups</a>
                                        <a href="{{ route('tier-rules') }}" class="block rounded-lg px-3 py-2 text-slate-300 hover:bg-slate-900/60">Tier rules</a>
                                    </div>
                                </div>
                            </nav>
                        </aside>

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
