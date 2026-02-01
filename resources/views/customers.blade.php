{{-- This view lists customers and provides filtering, export, and manual creation tools for admins. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        {{-- The title uses the app name configuration with a fallback for local/dev environments. --}}
        <title>{{ config('app.name', 'NexLoyal') }} - Customers</title>
        {{-- Preconnect and load the UI font used across the admin experience. --}}
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        {{-- Vite builds and injects the compiled CSS for this page. --}}
        @vite(['resources/css/app.css'])
        <style>
            {{-- These styles define light-mode overrides and table/modal appearance. --}}
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
            .nl-modal-backdrop {
                position: fixed;
                inset: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 24px;
                background: rgba(2, 6, 23, 0.7);
                opacity: 0;
                pointer-events: none;
                transition: opacity 200ms ease;
                z-index: 50;
            }
            .nl-modal-backdrop.is-open {
                opacity: 1;
                pointer-events: auto;
            }
            .nl-modal-panel {
                width: 100%;
                max-width: 680px;
                border-radius: 18px;
                background: linear-gradient(160deg, rgba(15, 23, 42, 0.98), rgba(2, 6, 23, 0.95));
                border: 1px solid rgba(148, 163, 184, 0.2);
                box-shadow: 0 30px 80px rgba(2, 6, 23, 0.55);
            }
            .nl-modal-divider {
                border-color: rgba(148, 163, 184, 0.16);
            }
            .nl-modal-label {
                font-size: 11px;
                letter-spacing: 0.18em;
            }
            .nl-modal-input {
                height: 42px;
                font-size: 13px;
            }
            .nl-modal-primary {
                background: linear-gradient(135deg, #38bdf8, #2563eb);
                color: #020617;
                font-weight: 600;
                box-shadow: 0 12px 24px rgba(37, 99, 235, 0.4);
            }
            .nl-theme-light .nl-modal-backdrop {
                background: rgba(148, 163, 184, 0.55);
            }
            .nl-theme-light .nl-modal-panel {
                background: linear-gradient(160deg, rgba(255, 255, 255, 0.95), rgba(241, 245, 249, 0.95));
                border-color: rgba(148, 163, 184, 0.4);
                color: #0f172a;
            }
            .nl-theme-light .nl-modal-divider {
                border-color: rgba(148, 163, 184, 0.3);
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
                            {{-- The header establishes context for customer management. --}}
                            <x-page-header eyebrow="" title="Customers" breadcrumb="Customers / Customers">
                                <x-slot name="actions">
                                    <button id="theme-toggle" class="rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-2 text-xs text-slate-200 nl-panel-muted" type="button">
                                        Switch theme
                                    </button>
                                </x-slot>
                            </x-page-header>

                            <section class="mt-6 overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel">
                                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800/70 px-6 py-4">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-100">Customer List</p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        {{-- Opening the modal allows manual customer entry when needed. --}}
                                        <button id="open-create-customer" class="rounded-xl bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-900" type="button">
                                            Create customer
                                        </button>
                                        {{-- Export includes active filters from the current query string. --}}
                                        <a class="nl-export-button text-xs" href="{{ route('customers.export', request()->query()) }}">Export CSV</a>
                                    </div>
                                </div>

                                <div class="px-6 py-5">
                                    {{-- Filters and search are sent as query parameters on GET. --}}
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
                                                {{-- Search applies to names, email, or identifiers depending on backend logic. --}}
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
                                                {{-- Each row maps to a paginated customer record. --}}
                                                @forelse ($customers as $customer)
                                                    <tr class="nl-table-row">
                                                        <td class="px-4 py-4">{{ ($customers->currentPage() - 1) * $customers->perPage() + $loop->iteration }}</td>
                                                        <td class="px-4 py-4">{{ $customer->full_name ?: 'Unnamed' }}</td>
                                                        <td class="px-4 py-4 text-slate-300">{{ $customer->email ?: '—' }}</td>
                                                        <td class="px-4 py-4 text-slate-300">{{ $customer->phone ?: '—' }}</td>
                                                        <td class="px-4 py-4 text-slate-300">{{ $customer->status ?: '—' }}</td>
                                                        <td class="px-4 py-4 text-center">
                                                            {{-- The view link opens a detailed customer profile page. --}}
                                                            <a class="nl-view-button text-slate-200" href="{{ route('customers.show', $customer) }}">View</a>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    {{-- When Shopify sync has not run yet, show a descriptive empty state. --}}
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
                                            {{-- Pagination uses a small window around the current page. --}}
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
        {{-- The create-customer modal supports manual entry when Shopify data is missing. --}}
        <div id="create-customer-modal" class="nl-modal-backdrop" aria-hidden="true">
            <div class="nl-modal-panel">
                <div class="flex items-start justify-between border-b border-slate-800 px-6 py-5 nl-modal-divider">
                    <div>
                        <p class="text-xs uppercase tracking-[0.35em] text-slate-400">Create customer</p>
                        <p class="mt-2 text-lg font-semibold text-slate-100">Add a new customer</p>
                        <p class="mt-1 text-xs text-slate-400">Capture key details to start tracking rewards and activity.</p>
                    </div>
                    <button type="button" class="rounded-full border border-slate-700 px-2.5 py-1 text-xs text-slate-200" data-modal-close>
                        Close
                    </button>
                </div>
                <form id="create-customer-form" class="px-6 py-6" method="POST" action="{{ route('customers.store') }}">
                    @csrf
                    {{-- Validation errors from the POST are surfaced inside the modal. --}}
                    @if ($errors->any())
                        <div class="mb-5 rounded-xl border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-xs text-rose-200" data-error-banner>
                            <p class="font-semibold text-rose-100">Fix the highlighted fields to continue.</p>
                            @if ($errors->has('shopify'))
                                <p class="mt-1 text-rose-200">{{ $errors->first('shopify') }}</p>
                            @endif
                        </div>
                    @endif
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-2">
                            {{-- Names and contact fields are required for customer creation. --}}
                            <label class="nl-modal-label uppercase text-slate-400">First name</label>
                            <input class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="text" name="first_name" value="{{ old('first_name') }}" placeholder="e.g. Jamie" required>
                            @error('first_name')
                                <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="nl-modal-label uppercase text-slate-400">Last name</label>
                            <input class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="text" name="last_name" value="{{ old('last_name') }}" placeholder="e.g. Patel" required>
                            @error('last_name')
                                <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex flex-col gap-2">
                            {{-- Gender is collected for analytics and personalization where available. --}}
                            <label class="nl-modal-label uppercase text-slate-400">Gender</label>
                            <select class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" name="gender" required>
                                <option value="" disabled @selected(!old('gender'))>Select</option>
                                <option value="female" @selected(old('gender') === 'female')>Female</option>
                                <option value="male" @selected(old('gender') === 'male')>Male</option>
                                <option value="nonbinary" @selected(old('gender') === 'nonbinary')>Non-binary</option>
                                <option value="other" @selected(old('gender') === 'other')>Other</option>
                                <option value="na" @selected(old('gender') === 'na')>Prefer not to say</option>
                            </select>
                            @error('gender')
                                <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="nl-modal-label uppercase text-slate-400">Email</label>
                            <input class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="email" name="email" value="{{ old('email') }}" placeholder="name@email.com" required>
                            @error('email')
                                <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex flex-col gap-2 sm:col-span-2">
                            <label class="nl-modal-label uppercase text-slate-400">Mobile number</label>
                            <div class="flex flex-col gap-2 sm:flex-row">
                                {{-- Country code is captured separately for consistent formatting. --}}
                                <select class="nl-modal-input w-full rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200 sm:max-w-[160px]" name="phone_country" required>
                                    <option value="" disabled @selected(!old('phone_country'))>Country code</option>
                                    <option value="+1" @selected(old('phone_country') === '+1')>US (+1)</option>
                                    <option value="+44" @selected(old('phone_country') === '+44')>UK (+44)</option>
                                    <option value="+61" @selected(old('phone_country') === '+61')>AU (+61)</option>
                                    <option value="+91" @selected(old('phone_country') === '+91')>IN (+91)</option>
                                    <option value="+94" @selected(old('phone_country') === '+94')>LK (+94)</option>
                                    <option value="+65" @selected(old('phone_country') === '+65')>SG (+65)</option>
                                </select>
                                <input class="nl-modal-input w-full rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="tel" name="phone" value="{{ old('phone') }}" placeholder="555 123 4567" required>
                            </div>
                            @error('phone_country')
                                <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                            @enderror
                            @error('phone')
                                <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-slate-800 pt-5 nl-modal-divider">
                        {{-- Cancel closes the modal without submitting. --}}
                        <button type="button" class="rounded-xl border border-slate-700 px-4 py-2 text-xs text-slate-200" data-modal-close>
                            Cancel
                        </button>
                        <button type="submit" class="nl-modal-primary rounded-xl px-5 py-2 text-xs">
                            Save customer
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <script>
            (function () {
                // Store the theme preference locally so it persists between visits.
                const storageKey = 'nl-theme';
                const body = document.body;
                const button = document.getElementById('theme-toggle');
                const modal = document.getElementById('create-customer-modal');
                const openModalButton = document.getElementById('open-create-customer');
                const closeButtons = modal ? modal.querySelectorAll('[data-modal-close]') : [];

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

                const form = document.getElementById('create-customer-form');
                // Remove any server-rendered error blocks when closing the modal.
                const clearErrors = () => {
                    if (!modal) {
                        return;
                    }
                    modal.querySelectorAll('[data-error-message]').forEach((node) => {
                        node.remove();
                    });
                    const banner = modal.querySelector('[data-error-banner]');
                    if (banner) {
                        banner.remove();
                    }
                };

                // Reset fields to a clean state after closing the modal.
                const resetForm = () => {
                    if (!form) {
                        return;
                    }
                    form.reset();
                    form.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"]').forEach((input) => {
                        input.value = '';
                    });
                    form.querySelectorAll('select').forEach((select) => {
                        select.selectedIndex = 0;
                    });
                };

                // Toggle modal visibility and cleanup state when it closes.
                const setModalOpen = (isOpen) => {
                    if (!modal) {
                        return;
                    }
                    modal.classList.toggle('is-open', isOpen);
                    modal.setAttribute('aria-hidden', String(!isOpen));
                    if (!isOpen) {
                        clearErrors();
                        resetForm();
                    }
                };

                if (openModalButton) {
                    openModalButton.addEventListener('click', () => setModalOpen(true));
                }

                if (modal) {
                    // Clicking the backdrop closes the modal.
                    modal.addEventListener('click', (event) => {
                        if (event.target === modal) {
                            setModalOpen(false);
                        }
                    });
                }

                closeButtons.forEach((button) => {
                    button.addEventListener('click', () => setModalOpen(false));
                });

                document.addEventListener('keydown', (event) => {
                    // Escape is a common accessibility shortcut for modal close.
                    if (event.key === 'Escape') {
                        setModalOpen(false);
                    }
                });

                // If the server returned validation errors, keep the modal open.
                const shouldOpen = {{ $errors->any() ? 'true' : 'false' }};
                if (shouldOpen) {
                    setModalOpen(true);
                }
            })();
        </script>
    </body>
</html>

