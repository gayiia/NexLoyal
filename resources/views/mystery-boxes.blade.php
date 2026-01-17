<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'NexLoyal') }} - Mystery Box</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        @vite(['resources/css/app.css'])
        <style>
            :root { color-scheme: dark; }
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
            .nl-theme-light .nl-text-muted { color: #475569; }
            .nl-theme-light .nl-sidebar-link { color: #0f172a; }
            .nl-theme-light .nl-sidebar-link:hover {
                background-color: rgba(226, 232, 240, 0.8);
                border-color: rgba(148, 163, 184, 0.6);
            }
            .nl-theme-light .nl-sidebar-link-active {
                background-color: rgba(226, 232, 240, 0.9);
                border-color: rgba(148, 163, 184, 0.6);
                color: #0f172a;
            }
            .nl-table-head { background: rgba(15, 23, 42, 0.6); }
            .nl-table-row:hover { background: rgba(30, 41, 59, 0.45); }
            .nl-theme-light .nl-table-head { background: rgba(226, 232, 240, 0.8); }
            .nl-theme-light .nl-table-row:hover { background: rgba(226, 232, 240, 0.8); }
            .nl-badge {
                border-radius: 999px;
                padding: 4px 10px;
                font-size: 11px;
                font-weight: 600;
            }
        </style>
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
        <div class="min-h-screen bg-[radial-gradient(900px_circle_at_top,rgba(56,189,248,0.18),transparent_60%)]">
            <div class="min-h-screen bg-[linear-gradient(120deg,rgba(15,23,42,0.9),rgba(2,6,23,0.95))] nl-shell">
                <div class="flex min-h-screen flex-col lg:flex-row">
                    @include('partials.admin-sidebar')

                    <main class="flex-1 px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
                        <x-page-header eyebrow="" title="Mystery Box" breadcrumb="Coupons / Mystery Box">
                            <x-slot name="actions">
                                <button id="theme-toggle" class="rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-2 text-xs text-slate-200 nl-panel-muted" type="button">
                                    Switch theme
                                </button>
                            </x-slot>
                        </x-page-header>

                        <section class="mt-6 overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel">
                            @if ($errors->has('mysteryBox'))
                                <div class="border-b border-slate-800/70 px-6 py-4 text-xs text-rose-200">
                                    <p class="font-semibold text-rose-100">Action failed.</p>
                                    <p class="mt-1 text-rose-200">{{ $errors->first('mysteryBox') }}</p>
                                </div>
                            @endif
                            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800/70 px-6 py-4">
                                <div>
                                    <p class="text-sm font-semibold text-slate-100">Mystery Box list</p>
                                    <p class="mt-1 text-xs text-slate-400">Manage wheel picker rewards by tier.</p>
                                </div>
                                <a href="{{ route('mystery-boxes.create') }}" class="rounded-xl bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-900">
                                    Create mystery box
                                </a>
                            </div>

                            <div class="overflow-x-auto px-6 py-5">
                                <table class="min-w-full text-left text-sm">
                                    <thead class="text-xs uppercase tracking-[0.2em] text-slate-400">
                                        <tr class="nl-table-head">
                                            <th class="px-4 py-3">Name</th>
                                            <th class="px-4 py-3">Tiers</th>
                                            <th class="px-4 py-3">Status</th>
                                            <th class="px-4 py-3">Created</th>
                                            <th class="px-4 py-3 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-800/70 text-slate-200">
                                        @forelse ($boxes as $box)
                                            @php
                                                $tierIds = collect($box->tiers ?? [])->map(fn ($id) => (int) $id)->all();
                                                $tierNames = $tiers->whereIn('id', $tierIds)->pluck('title')->all();
                                            @endphp
                                            <tr class="nl-table-row">
                                                <td class="px-4 py-4 font-semibold text-slate-100">{{ $box->name }}</td>
                                                <td class="px-4 py-4 text-xs text-slate-300">
                                                    {{ $tierNames ? implode(', ', $tierNames) : 'All tiers' }}
                                                </td>
                                                <td class="px-4 py-4">
                                                    @if ($box->is_active)
                                                        <span class="nl-badge border border-emerald-400/40 bg-emerald-400/10 text-emerald-200">Active</span>
                                                    @else
                                                        <span class="nl-badge border border-slate-500/50 bg-slate-800/70 text-slate-300">Inactive</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-4 text-xs text-slate-400">
                                                    {{ $box->created_at?->format('Y-m-d') }}
                                                </td>
                                                <td class="px-4 py-4 text-right text-xs">
                                                    <div class="flex flex-wrap justify-end gap-2">
                                                        <a href="{{ route('mystery-boxes.view', $box) }}" class="rounded-lg border border-slate-700 px-3 py-1.5 text-slate-200">View</a>
                                                        @if (!$box->is_active)
                                                            <a href="{{ route('mystery-boxes.edit', $box) }}" class="rounded-lg border border-slate-700 px-3 py-1.5 text-slate-200">Edit</a>
                                                        @endif
                                                        @if ($box->is_active)
                                                            <form method="POST" action="{{ route('mystery-boxes.deactivate', $box) }}">
                                                                @csrf
                                                                @method('PATCH')
                                                                <button type="submit" class="rounded-lg border border-amber-500/60 px-3 py-1.5 text-amber-200">
                                                                    Deactivate
                                                                </button>
                                                            </form>
                                                        @else
                                                            <form method="POST" action="{{ route('mystery-boxes.activate', $box) }}">
                                                                @csrf
                                                                @method('PATCH')
                                                                <button type="submit" class="rounded-lg border border-emerald-500/60 px-3 py-1.5 text-emerald-200">
                                                                    Activate
                                                                </button>
                                                            </form>
                                                            <form method="POST" action="{{ route('mystery-boxes.destroy', $box) }}" onsubmit="return confirm('Delete this mystery box?');">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="rounded-lg border border-rose-500/60 px-3 py-1.5 text-rose-200">
                                                                    Delete
                                                                </button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="px-4 py-6 text-center text-slate-400">No mystery boxes yet.</td>
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
