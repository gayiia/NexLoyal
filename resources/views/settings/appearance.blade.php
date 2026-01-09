<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'NexLoyal') }} - Appearance</title>
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
            .nl-appearance-button {
                border: 1px solid rgba(148, 163, 184, 0.4);
                background: rgba(30, 41, 59, 0.6);
                padding: 10px 16px;
                border-radius: 12px;
                font-size: 12px;
                font-weight: 600;
                letter-spacing: 0.08em;
                text-transform: uppercase;
            }
            .nl-appearance-active {
                border-color: rgba(56, 189, 248, 0.7);
                background: rgba(14, 116, 144, 0.35);
                color: #e0f2fe;
            }
            .nl-theme-light .nl-appearance-button {
                background: rgba(226, 232, 240, 0.7);
                color: #0f172a;
            }
            .nl-theme-light .nl-appearance-active {
                background: rgba(14, 116, 144, 0.2);
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
                                        <img src="{{ URL::asset('build\\Images\\logo-light.png') }}" alt="NexLoyal" class="w-auto">
                                    </div>
                                </div>
                            </div>

                            <nav class="mt-10 space-y-2 text-sm">
                                <a href="{{ route('dashboard') }}" class="flex items-center justify-between rounded-xl border border-transparent px-4 py-3 text-slate-300 hover:border-slate-800 hover:bg-slate-900/60 nl-sidebar-link">
                                    <span>Dashboard</span>
                                    <span class="text-xs text-slate-500 nl-text-muted">Overview</span>
                                </a>
                                <a href="{{ route('customers') }}" class="flex items-center justify-between rounded-xl border border-transparent px-4 py-3 text-slate-300 hover:border-slate-800 hover:bg-slate-900/60 nl-sidebar-link">
                                    <span>Customers</span>
                                    <span class="text-xs text-slate-500 nl-text-muted">Segments</span>
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
                                    <button id="settings-toggle" type="button" class="flex w-full items-center justify-between rounded-xl border border-transparent px-4 py-3 text-slate-100 bg-slate-900/80 border-slate-800 nl-sidebar-link nl-sidebar-link-active">
                                        <span>Settings</span>
                                        <span class="text-xs text-slate-400 nl-text-muted">Rules</span>
                                    </button>
                                    <div id="settings-menu" class="mt-2 space-y-1 pl-4 text-xs">
                                        <a href="{{ route('profile.edit') }}" class="block rounded-lg px-3 py-2 text-slate-300 hover:bg-slate-900/60">Profile</a>
                                        <a href="{{ route('user-password.edit') }}" class="block rounded-lg px-3 py-2 text-slate-300 hover:bg-slate-900/60">Password</a>
                                        <a href="{{ route('two-factor.show') }}" class="block rounded-lg px-3 py-2 text-slate-300 hover:bg-slate-900/60">Two-Factor Auth</a>
                                        <a href="{{ route('appearance.edit') }}" class="block rounded-lg bg-slate-900/80 px-3 py-2 text-slate-100">Appearance</a>
                                        <a href="{{ route('customer-groups') }}" class="block rounded-lg px-3 py-2 text-slate-300 hover:bg-slate-900/60">Customer groups</a>
                                        <a href="{{ route('point-rules') }}" class="block rounded-lg px-3 py-2 text-slate-300 hover:bg-slate-900/60">Point rules</a>
                                        <a href="{{ route('tier-rules') }}" class="block rounded-lg px-3 py-2 text-slate-300 hover:bg-slate-900/60">Tier rules</a>
                                    </div>
                                </div>
                            </nav>
                        </aside>

                        <main class="flex-1 px-10 py-8">
                            <x-page-header eyebrow="" title="Appearance" breadcrumb="Settings / Appearance">
                                <x-slot name="actions">
                                    <button id="theme-toggle" class="rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-2 text-xs text-slate-200 nl-panel-muted" type="button">
                                        Switch theme
                                    </button>
                                </x-slot>
                            </x-page-header>

                            <section class="mt-6 overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel">
                                <div class="border-b border-slate-800/70 px-6 py-4">
                                    <p class="text-sm font-semibold text-slate-100">Appearance settings</p>
                                    <p class="text-xs text-slate-400">Choose how the dashboard looks for you.</p>
                                </div>

                                <div class="px-6 py-6">
                                    <div class="flex flex-wrap gap-3">
                                        <button type="button" class="nl-appearance-button" data-theme="light">Light</button>
                                        <button type="button" class="nl-appearance-button" data-theme="dark">Dark</button>
                                        <button type="button" class="nl-appearance-button" data-theme="system">System</button>
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
                const buttons = document.querySelectorAll('[data-theme]');

                const applyTheme = (theme) => {
                    if (theme === 'system') {
                        const systemTheme = window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
                        body.classList.toggle('nl-theme-light', systemTheme === 'light');
                    } else {
                        body.classList.toggle('nl-theme-light', theme === 'light');
                    }
                    if (button) {
                        const currentTheme = body.classList.contains('nl-theme-light') ? 'light' : 'dark';
                        button.textContent = currentTheme === 'light' ? 'Switch to dark' : 'Switch to light';
                    }
                    buttons.forEach((item) => {
                        const isActive = item.getAttribute('data-theme') === theme;
                        item.classList.toggle('nl-appearance-active', isActive);
                    });
                };

                const stored = localStorage.getItem(storageKey) || 'dark';
                applyTheme(stored);

                if (button) {
                    button.addEventListener('click', () => {
                        const next = body.classList.contains('nl-theme-light') ? 'dark' : 'light';
                        localStorage.setItem(storageKey, next);
                        applyTheme(next);
                    });
                }

                buttons.forEach((item) => {
                    item.addEventListener('click', () => {
                        const theme = item.getAttribute('data-theme');
                        if (!theme) {
                            return;
                        }
                        localStorage.setItem(storageKey, theme);
                        applyTheme(theme);
                    });
                });

                const settingsToggle = document.getElementById('settings-toggle');
                const settingsMenu = document.getElementById('settings-menu');
                if (settingsToggle && settingsMenu) {
                    settingsToggle.addEventListener('click', () => {
                        settingsMenu.classList.toggle('hidden');
                    });
                }
            })();
        </script>
    </body>
</html>
