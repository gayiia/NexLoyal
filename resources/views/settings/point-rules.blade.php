<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'NexLoyal') }} - Point Rules</title>
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
                background-color: #f7f9fc;
                color: #0f172a;
            }
            .nl-theme-light .nl-shell {
                background: linear-gradient(130deg, rgba(245, 248, 255, 0.95), rgba(226, 235, 250, 0.98));
            }
            .nl-theme-light .nl-panel {
                background-color: rgba(255, 255, 255, 0.96);
                border-color: rgba(15, 23, 42, 0.12);
                color: #0b1736;
            }
            .nl-theme-light .nl-panel-muted {
                background-color: rgba(240, 245, 255, 0.9);
                border-color: rgba(15, 23, 42, 0.16);
                color: #0b1736;
            }
            .nl-theme-light .nl-text-muted {
                color: #5b6b84;
            }
            .nl-theme-light .nl-sidebar-link {
                color: #0b1736;
            }
            .nl-theme-light .nl-sidebar-link:hover {
                background-color: rgba(214, 229, 248, 0.7);
                border-color: rgba(15, 23, 42, 0.2);
            }
            .nl-theme-light .nl-sidebar-link-active {
                background-color: rgba(199, 219, 245, 0.9);
                border-color: rgba(15, 23, 42, 0.24);
                color: #0b1736;
            }
            .nl-theme-light .text-slate-50,
            .nl-theme-light .text-slate-100,
            .nl-theme-light .text-slate-200 {
                color: #0b1736;
            }
            .nl-theme-light .text-slate-300 {
                color: #1f2f4d;
            }
            .nl-theme-light .text-slate-400,
            .nl-theme-light .text-slate-500 {
                color: #4b5f7a;
            }
            .nl-input {
                height: 42px;
                font-size: 13px;
            }
            .nl-section {
                border-radius: 18px;
                border: 1px solid rgba(148, 163, 184, 0.28);
                background: rgba(15, 23, 42, 0.6);
            }
            .nl-section-header {
                background: rgba(2, 6, 23, 0.35);
            }
            .nl-row {
                border-radius: 14px;
                border: 1px solid rgba(30, 41, 59, 0.6);
                background: rgba(2, 6, 23, 0.35);
                padding: 14px 16px;
            }
            .nl-row-title {
                font-size: 13px;
                font-weight: 600;
                color: #e2e8f0;
            }
            .nl-row-help {
                font-size: 11px;
                color: #94a3b8;
            }
            .nl-row-label {
                font-size: 11px;
                letter-spacing: 0.2em;
                text-transform: uppercase;
                color: #94a3b8;
            }
            .nl-theme-light .nl-section {
                background: rgba(255, 255, 255, 0.98);
                border-color: rgba(15, 23, 42, 0.14);
            }
            .nl-theme-light .nl-section-header {
                background: rgba(229, 238, 252, 0.75);
            }
            .nl-theme-light .nl-row {
                border-color: rgba(15, 23, 42, 0.18);
                background: rgba(236, 243, 255, 0.9);
            }
            .nl-theme-light .nl-row-title {
                color: #0b1736;
            }
            .nl-theme-light .nl-row-help {
                color: #53657f;
            }
            .nl-theme-light .nl-row-label {
                color: #53657f;
            }
        </style>
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
        <div class="min-h-screen bg-[radial-gradient(900px_circle_at_top,rgba(56,189,248,0.18),transparent_60%)]">
            <div class="min-h-screen bg-[radial-gradient(700px_circle_at_bottom,rgba(30,64,175,0.22),transparent_60%)]">
                <div class="min-h-screen bg-[linear-gradient(120deg,rgba(15,23,42,0.9),rgba(2,6,23,0.95))] nl-shell">
                    <div class="flex min-h-screen flex-col lg:flex-row">
                        <aside class="w-full border-b border-slate-800/70 bg-slate-950/80 px-6 py-6 nl-panel lg:w-72 lg:border-b-0 lg:border-r lg:py-8">
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
                                <a href="#" class="flex items-center justify-between rounded-xl border border-transparent px-4 py-3 text-slate-300 hover:border-slate-800 hover:bg-slate-900/60 nl-sidebar-link">
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
                                        <a href="{{ route('appearance.edit') }}" class="block rounded-lg px-3 py-2 text-slate-300 hover:bg-slate-900/60">Appearance</a>
                                        <a href="{{ route('customer-groups') }}" class="block rounded-lg px-3 py-2 text-slate-300 hover:bg-slate-900/60">Customer groups</a>
                                        <a href="{{ route('point-rules') }}" class="block rounded-lg bg-slate-900/80 px-3 py-2 text-slate-100">Point rules</a>
                                        <a href="{{ route('tier-rules') }}" class="block rounded-lg px-3 py-2 text-slate-300 hover:bg-slate-900/60">Tier rules</a>
                                    </div>
                                </div>
                            </nav>
                        </aside>

                        <main class="flex-1 px-4 py-6 sm:px-6 lg:px-10">
                            <div class="mx-auto w-full max-w-6xl">
                                <x-page-header eyebrow="" title="Point Rules" breadcrumb="Settings / Point rules">
                                    <x-slot name="actions">
                                        <button id="theme-toggle" class="rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-2 text-xs text-slate-200 nl-panel-muted" type="button">
                                            Switch theme
                                        </button>
                                    </x-slot>
                                </x-page-header>

                                <section class="mt-6 overflow-hidden nl-section">
                                    <div class="px-6 py-4 nl-section-header">
                                        <p class="text-sm font-semibold text-slate-100">General settings</p>
                                        <p class="text-xs text-slate-400">Set how many points customers earn for the core actions in your store.</p>
                                    </div>

                                    <div class="space-y-3 px-6 py-4">
                                        <div class="nl-row grid gap-3 sm:grid-cols-[220px_1fr] sm:items-center">
                                            <div>
                                                <p class="nl-row-title">Welcome bonus points</p>
                                                <p class="nl-row-help">Granted when a customer joins.</p>
                                            </div>
                                            <input class="nl-input w-full rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="number" min="0" placeholder="100">
                                        </div>
                                        <div class="nl-row grid gap-3 sm:grid-cols-[220px_1fr] sm:items-center">
                                            <div>
                                                <p class="nl-row-title">Birthday reward points</p>
                                                <p class="nl-row-help">Applied once per year.</p>
                                            </div>
                                            <input class="nl-input w-full rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="number" min="0" placeholder="250">
                                        </div>
                                        <div class="nl-row grid gap-3 sm:grid-cols-[220px_1fr] sm:items-center">
                                            <div>
                                                <p class="nl-row-title">Profile completion points</p>
                                                <p class="nl-row-help">Reward for finishing required fields.</p>
                                            </div>
                                            <input class="nl-input w-full rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="number" min="0" placeholder="75">
                                        </div>
                                        <div class="nl-row grid gap-3 sm:grid-cols-[220px_1fr] sm:items-center">
                                            <div>
                                                <p class="nl-row-title">Newsletter sign up points</p>
                                                <p class="nl-row-help">One-time reward on signup.</p>
                                            </div>
                                            <input class="nl-input w-full rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="number" min="0" placeholder="50">
                                        </div>
                                        <div class="nl-row grid gap-3 sm:grid-cols-[220px_1fr] sm:items-center">
                                            <div>
                                                <p class="nl-row-title">Newsletter sign up link</p>
                                                <p class="nl-row-help">Where customers opt in.</p>
                                            </div>
                                            <input class="nl-input w-full rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="url" placeholder="https://yourbrand.com/newsletter">
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-end px-6 pb-6">
                                        <button type="button" class="rounded-xl bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-900">
                                            Save general settings
                                        </button>
                                    </div>
                                </section>

                                <section class="mt-6 overflow-hidden nl-section">
                                    <div class="px-6 py-4 nl-section-header">
                                        <p class="text-sm font-semibold text-slate-100">Social media rewards</p>
                                        <p class="text-xs text-slate-400">Add the profile links customers should visit and the points earned once per platform.</p>
                                    </div>

                                    <div class="space-y-3 px-6 py-4">
                                        <div class="nl-row grid gap-3 sm:grid-cols-[140px_1fr_120px] sm:items-center">
                                            <div>
                                                <p class="nl-row-title">LinkedIn</p>
                                                <p class="nl-row-help">Follow or visit the company page.</p>
                                            </div>
                                            <div class="grid gap-2">
                                                <label class="nl-row-label sm:sr-only">Link</label>
                                                <input class="nl-input w-full rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="url" placeholder="https://linkedin.com/company/yourbrand">
                                            </div>
                                            <div class="grid gap-2">
                                                <label class="nl-row-label sm:sr-only">Points</label>
                                                <input class="nl-input w-full rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="number" min="0" placeholder="25">
                                            </div>
                                        </div>
                                        <div class="nl-row grid gap-3 sm:grid-cols-[140px_1fr_120px] sm:items-center">
                                            <div>
                                                <p class="nl-row-title">TikTok</p>
                                                <p class="nl-row-help">Visit or follow your account.</p>
                                            </div>
                                            <div class="grid gap-2">
                                                <label class="nl-row-label sm:sr-only">Link</label>
                                                <input class="nl-input w-full rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="url" placeholder="https://www.tiktok.com/@yourbrand">
                                            </div>
                                            <div class="grid gap-2">
                                                <label class="nl-row-label sm:sr-only">Points</label>
                                                <input class="nl-input w-full rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="number" min="0" placeholder="30">
                                            </div>
                                        </div>
                                        <div class="nl-row grid gap-3 sm:grid-cols-[140px_1fr_120px] sm:items-center">
                                            <div>
                                                <p class="nl-row-title">Facebook</p>
                                                <p class="nl-row-help">Visit the store page.</p>
                                            </div>
                                            <div class="grid gap-2">
                                                <label class="nl-row-label sm:sr-only">Link</label>
                                                <input class="nl-input w-full rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="url" placeholder="https://facebook.com/yourbrand">
                                            </div>
                                            <div class="grid gap-2">
                                                <label class="nl-row-label sm:sr-only">Points</label>
                                                <input class="nl-input w-full rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="number" min="0" placeholder="20">
                                            </div>
                                        </div>
                                        <div class="nl-row grid gap-3 sm:grid-cols-[140px_1fr_120px] sm:items-center">
                                            <div>
                                                <p class="nl-row-title">X</p>
                                                <p class="nl-row-help">Visit or follow your profile.</p>
                                            </div>
                                            <div class="grid gap-2">
                                                <label class="nl-row-label sm:sr-only">Link</label>
                                                <input class="nl-input w-full rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="url" placeholder="https://x.com/yourbrand">
                                            </div>
                                            <div class="grid gap-2">
                                                <label class="nl-row-label sm:sr-only">Points</label>
                                                <input class="nl-input w-full rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="number" min="0" placeholder="15">
                                            </div>
                                        </div>
                                        <div class="nl-row grid gap-3 sm:grid-cols-[140px_1fr_120px] sm:items-center">
                                            <div>
                                                <p class="nl-row-title">Instagram</p>
                                                <p class="nl-row-help">Visit your profile.</p>
                                            </div>
                                            <div class="grid gap-2">
                                                <label class="nl-row-label sm:sr-only">Link</label>
                                                <input class="nl-input w-full rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="url" placeholder="https://instagram.com/yourbrand">
                                            </div>
                                            <div class="grid gap-2">
                                                <label class="nl-row-label sm:sr-only">Points</label>
                                                <input class="nl-input w-full rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="number" min="0" placeholder="20">
                                            </div>
                                        </div>
                                        <div class="nl-row grid gap-3 sm:grid-cols-[140px_1fr_120px] sm:items-center">
                                            <div>
                                                <p class="nl-row-title">YouTube</p>
                                                <p class="nl-row-help">Watch or subscribe.</p>
                                            </div>
                                            <div class="grid gap-2">
                                                <label class="nl-row-label sm:sr-only">Link</label>
                                                <input class="nl-input w-full rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="url" placeholder="https://youtube.com/@yourbrand">
                                            </div>
                                            <div class="grid gap-2">
                                                <label class="nl-row-label sm:sr-only">Points</label>
                                                <input class="nl-input w-full rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="number" min="0" placeholder="40">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-end px-6 pb-6">
                                        <button type="button" class="rounded-xl bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-900">
                                            Save social rewards
                                        </button>
                                    </div>
                                </section>
                            </div>
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
