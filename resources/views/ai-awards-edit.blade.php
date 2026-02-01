
{{-- This view renders the form used to edit an existing AI award draft. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        {{-- The title uses the app name configuration with a fallback for local/dev environments. --}}
        <title>{{ config('app.name', 'NexLoyal') }} - Edit AI Award</title>
        {{-- Preconnect and load the UI font used across the admin experience. --}}
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        {{-- Vite builds and injects the compiled CSS for this page. --}}
        @vite(['resources/css/app.css'])
        <style>
            {{-- These styles toggle light-mode colors for admin previews within the dark theme. --}}
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
            .nl-form-label {
                font-size: 11px;
                letter-spacing: 0.2em;
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
                            {{-- The header shows that the current action is editing an award draft. --}}
                            <x-page-header eyebrow="AI Insights" title="Edit award" subtitle="Draft update" breadcrumb="AI Insights / Awards">
                                <x-slot name="actions">
                                    <a class="rounded-xl border border-slate-700 px-4 py-2 text-xs text-slate-200" href="{{ route('ai-insights') }}">Back to AI Insights</a>
                                    <button id="theme-toggle" class="rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-2 text-xs text-slate-200 nl-panel-muted" type="button">
                                        Switch theme
                                    </button>
                                </x-slot>
                            </x-page-header>

                            {{-- This form updates an existing award draft and keeps the current values prefilled. --}}
                            <form method="POST" action="{{ route('ai-insights.awards.update', $award) }}" class="mt-6 rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-6 space-y-6">
                                @csrf
                                {{-- PATCH signals a partial update for the existing award. --}}
                                @method('PATCH')

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="flex flex-col gap-2">
                                        {{-- The title describes the award shown to administrators. --}}
                                        <label class="nl-form-label uppercase text-slate-400">Award title</label>
                                        <input class="rounded-lg border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-slate-200" type="text" name="title" value="{{ old('title', $award->title) }}" required>
                                        @error('title')
                                            <p class="text-xs text-rose-300">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        {{-- Awards are linked to a specific AI cluster. --}}
                                        <label class="nl-form-label uppercase text-slate-400">Cluster</label>
                                        <select class="rounded-lg border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-slate-200" name="ai_cluster_id" required>
                                            @foreach ($clusters as $cluster)
                                                <option value="{{ $cluster->id }}" @selected((string) old('ai_cluster_id', $award->ai_cluster_id) === (string) $cluster->id)>{{ $cluster->label }}</option>
                                            @endforeach
                                        </select>
                                        @error('ai_cluster_id')
                                            <p class="text-xs text-rose-300">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        {{-- The award can issue points or a coupon. --}}
                                        <label class="nl-form-label uppercase text-slate-400">Type</label>
                                        <select class="rounded-lg border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-slate-200" name="type" id="award-type" required>
                                            <option value="points" @selected(old('type', $award->type) === 'points')>Points</option>
                                            <option value="coupon" @selected(old('type', $award->type) === 'coupon')>Coupon</option>
                                        </select>
                                    </div>
                                    <div class="flex flex-col gap-2" data-type-section="points">
                                        {{-- Points amount only applies when the type is points. --}}
                                        <label class="nl-form-label uppercase text-slate-400">Points amount</label>
                                        <input class="rounded-lg border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-slate-200" type="number" min="1" name="points_amount" value="{{ old('points_amount', $award->points_amount) }}">
                                        @error('points_amount')
                                            <p class="text-xs text-rose-300">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="flex flex-col gap-2" data-type-section="coupon">
                                        {{-- Coupon selection only applies when the type is coupon. --}}
                                        <label class="nl-form-label uppercase text-slate-400">Coupon</label>
                                        <select class="rounded-lg border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-slate-200" name="coupon_id">
                                            <option value="" disabled @selected(!old('coupon_id', $award->coupon_id))>Select coupon</option>
                                            @foreach ($coupons as $coupon)
                                                <option value="{{ $coupon->id }}" @selected((string) old('coupon_id', $award->coupon_id) === (string) $coupon->id)>{{ $coupon->title }}</option>
                                            @endforeach
                                        </select>
                                        @error('coupon_id')
                                            <p class="text-xs text-rose-300">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>

                                <div class="flex items-center justify-end gap-3">
                                    {{-- Cancel returns to insights without saving the draft. --}}
                                    <a href="{{ route('ai-insights') }}" class="rounded-xl border border-slate-700 px-4 py-2 text-xs text-slate-200">Cancel</a>
                                    <button type="submit" class="rounded-xl bg-sky-400 px-5 py-2 text-xs font-semibold text-slate-950">Save draft</button>
                                </div>
                            </form>
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

                // Apply light or dark styles and update the button label.
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

                // Default to dark when no preference is stored.
                const stored = localStorage.getItem(storageKey);
                applyTheme(stored || 'dark');

                if (button) {
                    button.addEventListener('click', () => {
                        // Toggle the theme and persist the choice.
                        const next = body.classList.contains('nl-theme-light') ? 'dark' : 'light';
                        localStorage.setItem(storageKey, next);
                        applyTheme(next);
                    });
                }

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

                // Show only the inputs relevant to the selected award type.
                const typeSelect = document.getElementById('award-type');
                const sections = document.querySelectorAll('[data-type-section]');
                const updateSections = () => {
                    const active = typeSelect ? typeSelect.value : 'points';
                    sections.forEach((section) => {
                        const isActive = section.dataset.typeSection === active;
                        section.classList.toggle('hidden', !isActive);
                    });
                };

                if (typeSelect) {
                    typeSelect.addEventListener('change', updateSections);
                }
                updateSections();
            })();
        </script>
    </body>
</html>
