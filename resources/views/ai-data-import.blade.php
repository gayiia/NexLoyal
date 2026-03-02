{{-- This view renders the AI CSV import screen so admins can upload data for clustering and review import results. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        {{-- The title uses the app name configuration with a fallback for local/dev environments. --}}
        <title>{{ config('app.name', 'NexLoyal') }} - AI Data Import</title>
        {{-- Preconnect and load the UI font used across the admin experience. --}}
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        {{-- Vite builds and injects the compiled CSS for this page. --}}
        @vite(['resources/css/app.css'])
        <meta name="csrf-token" content="{{ csrf_token() }}">
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
            .import-progress-bar {
                width: var(--progress-width, 0%);
            }
            .import-progress-bar.processing {
                width: 55%;
                animation: import-progress 1.3s ease-in-out infinite;
            }
            @keyframes import-progress {
                0% {
                    transform: translateX(-65%);
                }
                50% {
                    transform: translateX(35%);
                }
                100% {
                    transform: translateX(160%);
                }
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

                        <main class="flex-1 px-6 py-8 lg:px-10">
                            {{-- The page header communicates location and offers quick actions. --}}
                            <x-page-header eyebrow="AI" title="Data import" subtitle="Import CSV data for AI clustering" breadcrumb="AI / Data / Import CSV">
                                <x-slot name="actions">
                                    <a class="rounded-xl border border-slate-700 px-4 py-2 text-xs text-slate-200" href="{{ route('ai-insights') }}">Back to AI Insights</a>
                                    <button id="theme-toggle" class="rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-2 text-xs text-slate-200 nl-panel-muted" type="button">
                                        Switch theme
                                    </button>
                                </x-slot>
                            </x-page-header>

                            {{-- Validation errors from the upload/import process are listed here. --}}
                            <div id="import-errors" class="mt-4 hidden rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                                <p class="font-semibold text-rose-100">Import failed.</p>
                                <ul id="import-errors-list" class="mt-2 list-disc space-y-1 pl-5"></ul>
                            </div>

                            @if ($errors->any())
                                <div class="mt-4 rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                                    <p class="font-semibold text-rose-100">Import failed.</p>
                                    <ul class="mt-2 list-disc space-y-1 pl-5">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <section id="import-progress-panel" class="mt-4 hidden rounded-2xl border border-sky-500/30 bg-sky-500/10 p-6 text-sm text-sky-100">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-sky-50">Import in progress</p>
                                        <p id="import-progress-label" class="text-xs text-sky-200">Uploading CSV files...</p>
                                    </div>
                                    <p id="import-progress-value" class="text-xs font-semibold text-sky-100">0%</p>
                                </div>
                                <div class="mt-4 h-3 overflow-hidden rounded-full bg-slate-950/60">
                                    <div id="import-progress-bar" class="import-progress-bar h-full rounded-full bg-gradient-to-r from-sky-300 via-cyan-300 to-emerald-300 transition-[width] duration-300 ease-out"></div>
                                </div>
                                <p class="mt-3 text-xs text-sky-200">Keep this tab open until the import summary appears.</p>
                            </section>

                            {{-- The import summary is only shown after a successful or partial import. --}}
                            @if (session('import_summary'))
                                @php $summary = session('import_summary'); @endphp
                                <section class="mt-4 rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-6">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-100">Import summary</p>
                                            <p class="text-xs text-slate-400">{{ $summary['status'] ?? 'Import completed.' }}</p>
                                        </div>
                                        <a class="rounded-xl border border-slate-700 px-3 py-2 text-xs text-slate-200" href="{{ route('ai-insights') }}">View AI Insights</a>
                                    </div>
                                    <div class="mt-5 grid gap-4 lg:grid-cols-3">
                                        <div class="rounded-xl border border-slate-800/70 bg-slate-950/40 p-4 text-xs">
                                            {{-- The customers block reports counts by action taken. --}}
                                            <p class="uppercase tracking-[0.2em] text-slate-400">Customers</p>
                                            <p class="mt-2 text-sm text-slate-100">Imported: {{ $summary['customers']['imported'] ?? 0 }}</p>
                                            <p class="text-slate-300">Updated: {{ $summary['customers']['updated'] ?? 0 }}</p>
                                            <p class="text-slate-300">Skipped: {{ $summary['customers']['skipped'] ?? 0 }}</p>
                                            @if (!empty($summary['customers']['synthetic_orders']))
                                                <p class="text-slate-300">Synthetic orders: {{ $summary['customers']['synthetic_orders'] }}</p>
                                            @endif
                                        </div>
                                        <div class="rounded-xl border border-slate-800/70 bg-slate-950/40 p-4 text-xs">
                                            {{-- Points transactions are optional but summarized when provided. --}}
                                            <p class="uppercase tracking-[0.2em] text-slate-400">Points transactions</p>
                                            <p class="mt-2 text-sm text-slate-100">Imported: {{ $summary['points_transactions']['imported'] ?? 0 }}</p>
                                            <p class="text-slate-300">Skipped: {{ $summary['points_transactions']['skipped'] ?? 0 }}</p>
                                        </div>
                                        <div class="rounded-xl border border-slate-800/70 bg-slate-950/40 p-4 text-xs">
                                            {{-- Customer coupons are optional but summarized when provided. --}}
                                            <p class="uppercase tracking-[0.2em] text-slate-400">Customer coupons</p>
                                            <p class="mt-2 text-sm text-slate-100">Imported: {{ $summary['customer_coupons']['imported'] ?? 0 }}</p>
                                            <p class="text-slate-300">Updated: {{ $summary['customer_coupons']['updated'] ?? 0 }}</p>
                                            <p class="text-slate-300">Skipped: {{ $summary['customer_coupons']['skipped'] ?? 0 }}</p>
                                        </div>
                                    </div>
                                    {{-- Warnings capture non-fatal issues that still completed the import. --}}
                                    @if (!empty($summary['warnings']))
                                        <div class="mt-4 rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-xs text-amber-200">
                                            <p class="font-semibold">Warnings</p>
                                            <ul class="mt-2 list-disc space-y-1 pl-5">
                                                @foreach ($summary['warnings'] as $warning)
                                                    <li>{{ $warning }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                    {{-- Sample rows help diagnose which input data was ignored. --}}
                                    @if (!empty($summary['skipped_rows']))
                                        <div class="mt-4 rounded-xl border border-slate-800/70 bg-slate-950/40 p-4 text-xs">
                                            <p class="font-semibold text-slate-100">Skipped rows (sample)</p>
                                            <div class="mt-3 space-y-3 text-slate-300">
                                                @foreach ($summary['skipped_rows'] as $group => $rows)
                                                    <div>
                                                        <p class="text-slate-200">{{ str_replace('_', ' ', $group) }}</p>
                                                        @foreach ($rows as $row)
                                                            <pre class="mt-2 whitespace-pre-wrap rounded-lg border border-slate-800/70 bg-slate-950/40 p-3 text-[11px] text-slate-400">{{ json_encode($row, JSON_PRETTY_PRINT) }}</pre>
                                                        @endforeach
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </section>
                            @endif

                            <section class="mt-6 grid gap-6 lg:grid-cols-3">
                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-6">
                                    {{-- Templates are provided so users can match the expected CSV structure. --}}
                                    <p class="text-sm font-semibold text-slate-100">Templates</p>
                                    <p class="mt-1 text-xs text-slate-400">Download sample CSVs with 5 rows.</p>
                                    <div class="mt-4 space-y-2 text-xs">
                                        <a class="block rounded-lg border border-slate-700 px-3 py-2 text-slate-200" href="{{ asset('templates/customers.csv') }}" download>customers.csv</a>
                                        <a class="block rounded-lg border border-slate-700 px-3 py-2 text-slate-200" href="{{ asset('templates/points_transactions.csv') }}" download>points_transactions.csv</a>
                                        <a class="block rounded-lg border border-slate-700 px-3 py-2 text-slate-200" href="{{ asset('templates/customer_coupons.csv') }}" download>customer_coupons.csv</a>
                                    </div>
                                </div>
                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel p-6 lg:col-span-2">
                                    {{-- This form submits CSV files for server-side import and optional clustering. --}}
                                    <p class="text-sm font-semibold text-slate-100">Upload CSV files</p>
                                    <p class="mt-1 text-xs text-slate-400">Customers file is required. Others are optional.</p>

                                    <form id="ai-import-form" class="mt-4 space-y-4" method="POST" action="{{ route('ai-data-import.store') }}" enctype="multipart/form-data">
                                        @csrf
                                        <div class="grid gap-4 md:grid-cols-2">
                                            <div>
                                                {{-- Customers are required because other datasets link to customer IDs. --}}
                                                <label class="text-xs uppercase tracking-[0.2em] text-slate-400">customers.csv</label>
                                                <input class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950/60 px-3 py-2 text-xs text-slate-200" type="file" name="customers_file" accept=".csv" required>
                                            </div>
                                            <div>
                                                {{-- Transactions improve feature accuracy but can be omitted. --}}
                                                <label class="text-xs uppercase tracking-[0.2em] text-slate-400">points_transactions.csv</label>
                                                <input class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950/60 px-3 py-2 text-xs text-slate-200" type="file" name="points_transactions_file" accept=".csv">
                                            </div>
                                            <div>
                                                {{-- Coupon redemptions are optional and are used for richer clustering. --}}
                                                <label class="text-xs uppercase tracking-[0.2em] text-slate-400">customer_coupons.csv</label>
                                                <input class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950/60 px-3 py-2 text-xs text-slate-200" type="file" name="customer_coupons_file" accept=".csv">
                                            </div>
                                            <div class="flex items-center gap-2 text-xs text-slate-200">
                                                {{-- This flag triggers a background clustering job after import. --}}
                                                <input id="run_clustering" type="checkbox" name="run_clustering" value="1" checked>
                                                <label for="run_clustering">Run clustering after import</label>
                                            </div>
                                        </div>

                                        {{-- The import relies on queued jobs, so a worker must be running. --}}
                                        <div class="rounded-xl border border-slate-800/70 bg-slate-950/40 px-4 py-3 text-xs text-slate-300">
                                            Make sure the queue worker is running: <span class="text-slate-200">php artisan queue:work</span>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-3">
                                            <button id="import-submit-button" type="submit" class="rounded-xl bg-sky-400 px-5 py-2 text-xs font-semibold text-slate-950 shadow-lg shadow-sky-500/30">
                                                Import CSV
                                            </button>
                                            {{-- The import message reflects that database writes are wrapped in a transaction. --}}
                                            <p class="text-xs text-slate-400">The import runs inside a database transaction.</p>
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
                const form = document.getElementById('ai-import-form');
                const submitButton = document.getElementById('import-submit-button');
                const progressPanel = document.getElementById('import-progress-panel');
                const progressLabel = document.getElementById('import-progress-label');
                const progressValue = document.getElementById('import-progress-value');
                const progressBar = document.getElementById('import-progress-bar');
                const errorsPanel = document.getElementById('import-errors');
                const errorsList = document.getElementById('import-errors-list');

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

                const clearErrors = () => {
                    if (!errorsPanel || !errorsList) {
                        return;
                    }
                    errorsList.innerHTML = '';
                    errorsPanel.classList.add('hidden');
                };

                const showErrors = (messages) => {
                    if (!errorsPanel || !errorsList) {
                        return;
                    }
                    errorsList.innerHTML = '';
                    messages.forEach((message) => {
                        const item = document.createElement('li');
                        item.textContent = message;
                        errorsList.appendChild(item);
                    });
                    errorsPanel.classList.remove('hidden');
                    errorsPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                };

                const setProgress = (value, label, processing = false) => {
                    if (!progressPanel || !progressLabel || !progressValue || !progressBar) {
                        return;
                    }

                    progressPanel.classList.remove('hidden');
                    progressLabel.textContent = label;

                    if (processing) {
                        progressValue.textContent = 'Processing';
                        progressBar.classList.add('processing');
                        progressBar.style.setProperty('--progress-width', '55%');
                        return;
                    }

                    const boundedValue = Math.max(0, Math.min(100, value));
                    progressValue.textContent = `${Math.round(boundedValue)}%`;
                    progressBar.classList.remove('processing');
                    progressBar.style.setProperty('--progress-width', `${boundedValue}%`);
                };

                if (form && submitButton) {
                    form.addEventListener('submit', (event) => {
                        event.preventDefault();
                        clearErrors();

                        const data = new FormData(form);
                        const xhr = new XMLHttpRequest();

                        submitButton.disabled = true;
                        submitButton.classList.add('cursor-not-allowed', 'opacity-70');
                        setProgress(0, 'Uploading CSV files...');

                        xhr.open('POST', form.action, true);
                        xhr.setRequestHeader('Accept', 'application/json');
                        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                        xhr.upload.addEventListener('progress', (uploadEvent) => {
                            if (!uploadEvent.lengthComputable) {
                                setProgress(15, 'Uploading CSV files...');
                                return;
                            }

                            const uploadPercent = (uploadEvent.loaded / uploadEvent.total) * 100;
                            const displayPercent = Math.min(90, uploadPercent);
                            setProgress(displayPercent, 'Uploading CSV files...');
                        });

                        xhr.addEventListener('load', () => {
                            submitButton.disabled = false;
                            submitButton.classList.remove('cursor-not-allowed', 'opacity-70');

                            let payload = {};
                            try {
                                payload = JSON.parse(xhr.responseText || '{}');
                            } catch (error) {
                                payload = {};
                            }

                            if (xhr.status >= 200 && xhr.status < 300 && payload.redirect) {
                                setProgress(100, 'Import completed. Redirecting...');
                                window.location.assign(payload.redirect);
                                return;
                            }

                            const messages = [];
                            if (payload.errors && typeof payload.errors === 'object') {
                                Object.values(payload.errors).forEach((group) => {
                                    if (Array.isArray(group)) {
                                        group.forEach((message) => messages.push(message));
                                    }
                                });
                            }
                            if (messages.length === 0) {
                                messages.push(payload.message || 'The import failed before completion.');
                            }

                            progressPanel?.classList.add('hidden');
                            showErrors(messages);
                        });

                        xhr.addEventListener('error', () => {
                            submitButton.disabled = false;
                            submitButton.classList.remove('cursor-not-allowed', 'opacity-70');
                            progressPanel?.classList.add('hidden');
                            showErrors(['The import request could not be completed.']);
                        });

                        xhr.addEventListener('loadstart', () => {
                            setProgress(5, 'Uploading CSV files...');
                        });

                        xhr.upload.addEventListener('loadend', () => {
                            setProgress(95, 'Files uploaded. Importing records...', true);
                        });

                        xhr.send(data);
                    });
                }
            })();
        </script>
    </body>
</html>
