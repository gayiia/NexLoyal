{{-- This view lets admins choose their preferred theme appearance. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        {{-- The title uses the app name configuration with a fallback for local/dev environments. --}}
        <title>{{ config('app.name', 'NexLoyal') }} - Appearance</title>
        {{-- Preconnect and load the UI font used across the admin experience. --}}
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        {{-- Vite builds and injects the compiled CSS for this page. --}}
        @vite(['resources/css/app.css'])
        <style>
            {{-- These styles define light-mode overrides and appearance button states. --}}
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
                        {{-- The admin sidebar is shared across the dashboard and provides navigation. --}}
                        @include('partials.admin-sidebar')

                        <main class="flex-1 px-10 py-8">
                            {{-- The header anchors the appearance settings screen. --}}
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
                                        {{-- These buttons persist the theme preference in local storage. --}}
                                        <button type="button" class="nl-appearance-button" data-theme="light">Light</button>
                                        <button type="button" class="nl-appearance-button" data-theme="dark">Dark</button>
                                        <button type="button" class="nl-appearance-button" data-theme="system">System</button>
                                    </div>
                                </div>
                            </section>

                            <section class="mt-6 overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel">
                                <div class="border-b border-slate-800/70 px-6 py-4">
                                    <p class="text-sm font-semibold text-slate-100">Brand logo</p>
                                    <p class="text-xs text-slate-400">Upload one logo and reuse it anywhere the admin UI displays your brand.</p>
                                </div>

                                <div class="grid gap-6 px-6 py-6 lg:grid-cols-[minmax(0,20rem)_1fr]">
                                    <div class="rounded-2xl border border-slate-800 bg-slate-950/40 p-5 nl-panel-muted">
                                        <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Current logo</p>
                                        <div class="mt-4 flex min-h-32 items-center justify-center rounded-2xl border border-slate-800 bg-slate-950/70 p-6">
                                            <img id="logo-preview" src="{{ $appLogoUrl }}" alt="Current brand logo" class="max-h-16 w-auto max-w-full">
                                        </div>
                                        <p class="mt-4 text-xs text-slate-400">
                                            The uploaded file is used in the login screen and admin sidebar.
                                        </p>
                                    </div>

                                    <form method="POST" action="{{ route('appearance.update') }}" enctype="multipart/form-data" class="space-y-5">
                                        @csrf
                                        @method('PATCH')

                                        <div class="grid gap-2">
                                            <label for="logo" class="text-xs uppercase tracking-[0.2em] text-slate-400">Upload logo</label>
                                            <input
                                                id="logo"
                                                name="logo"
                                                type="file"
                                                accept=".png,.jpg,.jpeg,.webp"
                                                class="rounded-xl border border-slate-700 bg-slate-950/60 px-3 py-3 text-sm text-slate-200 file:mr-4 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-slate-900"
                                                required
                                            >
                                            <p class="text-xs text-slate-400">PNG, JPG, or WEBP up to 2MB.</p>
                                            @error('logo')
                                                <p class="text-xs text-rose-300">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div class="flex items-center gap-3">
                                            <button type="submit" class="rounded-xl bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-900">
                                                Save logo
                                            </button>
                                            @if (session('status') === 'branding-updated')
                                                <span class="text-xs text-emerald-300">Logo updated.</span>
                                            @endif
                                        </div>
                                    </form>
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
                const buttons = document.querySelectorAll('[data-theme]');
                const logoInput = document.getElementById('logo');
                const logoPreview = document.getElementById('logo-preview');
                let previewUrl = null;

                // Apply a theme preference, falling back to system if selected.
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

                // Default to dark if no preference is stored.
                const stored = localStorage.getItem(storageKey) || 'dark';
                applyTheme(stored);

                if (button) {
                    button.addEventListener('click', () => {
                        // Toggle between light and dark for quick switching.
                        const next = body.classList.contains('nl-theme-light') ? 'dark' : 'light';
                        localStorage.setItem(storageKey, next);
                        applyTheme(next);
                    });
                }

                buttons.forEach((item) => {
                    item.addEventListener('click', () => {
                        // Persist the selected appearance and update button styles.
                        const theme = item.getAttribute('data-theme');
                        if (!theme) {
                            return;
                        }
                        localStorage.setItem(storageKey, theme);
                        applyTheme(theme);
                    });
                });

                logoInput?.addEventListener('change', (event) => {
                    const [file] = event.target.files || [];
                    if (!file || !logoPreview) {
                        return;
                    }

                    if (previewUrl) {
                        URL.revokeObjectURL(previewUrl);
                    }

                    previewUrl = URL.createObjectURL(file);
                    logoPreview.src = previewUrl;
                });

                window.addEventListener('beforeunload', () => {
                    if (previewUrl) {
                        URL.revokeObjectURL(previewUrl);
                    }
                });

                const settingsToggle = document.getElementById('settings-toggle');
                const settingsMenu = document.getElementById('settings-menu');
                // Settings submenu toggles via the sidebar caret.
                if (settingsToggle && settingsMenu) {
                    settingsToggle.addEventListener('click', () => {
                        settingsMenu.classList.toggle('hidden');
                    });
                }
            })();
        </script>
    </body>
</html>
