<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'NexLoyal') }} - Exclusive Chat Settings</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        @vite(['resources/css/app.css'])
        <style>
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
            .nl-tab { border-radius: 999px; padding: 6px 14px; font-size: 11px; font-weight: 600; }
        </style>
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
        <div class="min-h-screen bg-[radial-gradient(900px_circle_at_top,rgba(56,189,248,0.18),transparent_60%)]">
            <div class="min-h-screen bg-[linear-gradient(120deg,rgba(15,23,42,0.9),rgba(2,6,23,0.95))] nl-shell">
                <div class="flex min-h-screen flex-col lg:flex-row">
                    @include('partials.admin-sidebar')

                    <main class="flex-1 px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
                        <x-page-header eyebrow="" title="Exclusive Chat" breadcrumb="Notifications / Exclusive Chat">
                            <x-slot name="actions">
                                <button id="theme-toggle" class="rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-2 text-xs text-slate-200 nl-panel-muted" type="button">
                                    Switch theme
                                </button>
                            </x-slot>
                        </x-page-header>

                        <div class="mt-6 flex flex-wrap gap-3">
                            <a href="{{ route('exclusive-chat') }}" class="nl-tab border border-slate-700 text-slate-200">Messages</a>
                            <a href="{{ route('exclusive-chat.settings') }}" class="nl-tab border border-slate-700 bg-slate-100 text-slate-900">Settings</a>
                        </div>

                        <section class="mt-6 rounded-2xl border border-slate-800 bg-slate-900/70 p-6 nl-panel">
                            <form method="POST" action="{{ route('exclusive-chat.settings.update') }}" class="grid gap-6">
                                @csrf
                                <div>
                                    <p class="text-sm font-semibold text-slate-100">Chat availability</p>
                                    <p class="mt-1 text-xs text-slate-400">Enable Exclusive Chat and select which tiers can see it.</p>
                                </div>

                                <label class="flex items-center gap-3 text-sm text-slate-200">
                                    <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $settings->enabled))>
                                    Enable Exclusive Chat
                                </label>

                                <div>
                                    <label class="text-xs uppercase tracking-[0.2em] text-slate-400">Allowed tiers</label>
                                    <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                        @foreach ($tiers as $tier)
                                            <label class="flex items-center gap-2 text-xs text-slate-300">
                                                <input type="checkbox" name="allowed_tiers[]" value="{{ $tier->id }}" @checked(in_array($tier->id, old('allowed_tiers', $settings->allowed_tiers ?? []), true))>
                                                {{ $tier->title }}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="flex items-center justify-end gap-3">
                                    <button class="rounded-xl bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-900" type="submit">
                                        Save settings
                                    </button>
                                </div>
                            </form>
                        </section>
                    </main>
                </div>
            </div>
        </div>

        <script>
            (function () {
                const storageKey = 'nl-theme';
                const body = document.body;
                const button = document.getElementById('theme-toggle');

                const applyTheme = (theme) => {
                    body.classList.toggle('nl-theme-light', theme === 'light');
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
