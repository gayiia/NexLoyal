<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'NexLoyal') }} - Tier Rules</title>
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
            .nl-status {
                border-radius: 999px;
                padding: 4px 10px;
                font-size: 11px;
                font-weight: 600;
                letter-spacing: 0.05em;
                text-transform: uppercase;
            }
            .nl-status-active {
                background: rgba(16, 185, 129, 0.2);
                color: #6ee7b7;
                border: 1px solid rgba(16, 185, 129, 0.4);
            }
            .nl-status-inactive {
                background: rgba(148, 163, 184, 0.2);
                color: #cbd5f5;
                border: 1px solid rgba(148, 163, 184, 0.4);
            }
            .nl-action-button {
                border: 1px solid rgba(148, 163, 184, 0.3);
                padding: 6px 12px;
                border-radius: 999px;
                font-size: 11px;
                letter-spacing: 0.08em;
                text-transform: uppercase;
            }
            .nl-action-trigger {
                border: 1px solid rgba(148, 163, 184, 0.4);
                padding: 6px 12px;
                border-radius: 999px;
                font-size: 11px;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                background: rgba(30, 41, 59, 0.5);
                color: #e2e8f0;
            }
            .nl-action-menu {
                position: absolute;
                right: 0;
                top: calc(100% + 8px);
                min-width: 160px;
                padding: 8px;
                border-radius: 12px;
                border: 1px solid rgba(148, 163, 184, 0.25);
                background: rgba(15, 23, 42, 0.95);
                box-shadow: 0 18px 40px rgba(2, 6, 23, 0.45);
                opacity: 0;
                pointer-events: none;
                transform: translateY(-6px);
                transition: opacity 120ms ease, transform 120ms ease;
                z-index: 20;
            }
            .nl-action-menu.is-open {
                opacity: 1;
                pointer-events: auto;
                transform: translateY(0);
            }
            .nl-action-item {
                width: 100%;
                text-align: left;
                padding: 8px 10px;
                border-radius: 10px;
                font-size: 12px;
                color: #e2e8f0;
            }
            .nl-action-item:hover {
                background: rgba(30, 41, 59, 0.6);
            }
            .nl-action-item-danger {
                color: #fda4af;
            }
            .nl-theme-light .nl-action-trigger {
                background: rgba(226, 232, 240, 0.8);
                color: #0f172a;
            }
            .nl-theme-light .nl-action-menu {
                background: rgba(255, 255, 255, 0.96);
                border-color: rgba(148, 163, 184, 0.4);
                color: #0f172a;
            }
            .nl-theme-light .nl-action-item {
                color: #0f172a;
            }
            .nl-theme-light .nl-action-item:hover {
                background: rgba(226, 232, 240, 0.9);
            }
            .nl-action-primary {
                background: rgba(59, 130, 246, 0.15);
                color: #bfdbfe;
                border-color: rgba(59, 130, 246, 0.35);
            }
            .nl-action-warning {
                background: rgba(251, 191, 36, 0.15);
                color: #fde68a;
                border-color: rgba(251, 191, 36, 0.35);
            }
            .nl-action-danger {
                background: rgba(244, 63, 94, 0.15);
                color: #fda4af;
                border-color: rgba(244, 63, 94, 0.35);
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
                max-width: 700px;
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
            .nl-color-chip {
                width: 16px;
                height: 16px;
                border-radius: 6px;
                border: 1px solid rgba(148, 163, 184, 0.5);
                box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.2) inset;
            }
            .nl-color-input {
                height: 42px;
                padding: 6px 8px;
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
                                        <a href="{{ route('appearance.edit') }}" class="block rounded-lg px-3 py-2 text-slate-300 hover:bg-slate-900/60">Appearance</a>
                                        <a href="{{ route('customer-groups') }}" class="block rounded-lg px-3 py-2 text-slate-300 hover:bg-slate-900/60">Customer groups</a>
                                        <a href="{{ route('point-rules') }}" class="block rounded-lg px-3 py-2 text-slate-300 hover:bg-slate-900/60">Point rules</a>
                                        <a href="{{ route('tier-rules') }}" class="block rounded-lg bg-slate-900/80 px-3 py-2 text-slate-100">Tier rules</a>
                                    </div>
                                </div>
                            </nav>
                        </aside>

                        <main class="flex-1 px-10 py-8">
                            <x-page-header eyebrow="" title="Tier Rules" breadcrumb="Settings / Tier rules">
                                <x-slot name="actions">
                                    <button id="theme-toggle" class="rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-2 text-xs text-slate-200 nl-panel-muted" type="button">
                                        Switch theme
                                    </button>
                                </x-slot>
                            </x-page-header>

                            <section class="mt-6 overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel">
                                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800/70 px-6 py-4">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-100">Tier list</p>
                                        <p class="text-xs text-slate-400">Define point ranges and rewards for each tier.</p>
                                    </div>
                                    <button id="open-create-tier" class="rounded-xl bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-900" type="button">
                                        Add new tier
                                    </button>
                                </div>

                                <div class="px-6 py-5">
                                    <div>
                                        <table class="w-full text-left text-xs">
                                            <thead class="nl-table-head text-slate-300">
                                                <tr>
                                                    <th class="px-4 py-3 font-semibold">No.</th>
                                                    <th class="px-4 py-3 font-semibold">Title</th>
                                                    <th class="px-4 py-3 font-semibold">Color</th>
                                                    <th class="px-4 py-3 font-semibold">Minimum point</th>
                                                    <th class="px-4 py-3 font-semibold">Maximum point</th>
                                                    <th class="px-4 py-3 font-semibold">Single point value</th>
                                                    <th class="px-4 py-3 font-semibold">Status</th>
                                                    <th class="px-4 py-3 font-semibold">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-800/80 text-slate-200">
                                                @forelse ($tiers as $tier)
                                                    <tr class="nl-table-row">
                                                        <td class="px-4 py-4">{{ ($tiers->currentPage() - 1) * $tiers->perPage() + $loop->iteration }}</td>
                                                        <td class="px-4 py-4 font-semibold text-slate-100">{{ $tier->title }}</td>
                                                        <td class="px-4 py-4">
                                                            <div class="flex items-center gap-2">
                                                                <span class="nl-color-chip" style="background-color: {{ $tier->color }}"></span>
                                                                <span class="text-slate-300">{{ strtoupper($tier->color) }}</span>
                                                            </div>
                                                        </td>
                                                        <td class="px-4 py-4 text-slate-300">{{ number_format($tier->min_points) }}</td>
                                                        <td class="px-4 py-4 text-slate-300">{{ number_format($tier->max_points) }}</td>
                                                        <td class="px-4 py-4 text-slate-300">{{ $tier->single_point_value }}</td>
                                                        <td class="px-4 py-4">
                                                            <span class="nl-status {{ $tier->status === 'active' ? 'nl-status-active' : 'nl-status-inactive' }}">
                                                                {{ ucfirst($tier->status) }}
                                                            </span>
                                                        </td>
                                                        <td class="px-4 py-4">
                                                            <div class="relative inline-flex">
                                                                <button
                                                                    class="nl-action-trigger"
                                                                    type="button"
                                                                    data-action-toggle
                                                                >
                                                                    Actions
                                                                </button>
                                                                <div class="nl-action-menu" data-action-menu>
                                                                    <button
                                                                        class="nl-action-item"
                                                                        type="button"
                                                                        data-tier-edit
                                                                        data-tier-action="{{ route('tier-rules.update', $tier) }}"
                                                                        data-tier-title="{{ $tier->title }}"
                                                                        data-tier-color="{{ $tier->color }}"
                                                                        data-tier-min-points="{{ $tier->min_points }}"
                                                                        data-tier-max-points="{{ $tier->max_points }}"
                                                                        data-tier-single-value="{{ $tier->single_point_value }}"
                                                                        data-tier-description="{{ $tier->description }}"
                                                                    >
                                                                        Edit
                                                                    </button>
                                                                    <form method="POST" action="{{ route('tier-rules.status', $tier) }}">
                                                                        @csrf
                                                                        @method('PATCH')
                                                                        <input type="hidden" name="status" value="{{ $tier->status === 'active' ? 'inactive' : 'active' }}">
                                                                        <button class="nl-action-item" type="submit">
                                                                            {{ $tier->status === 'active' ? 'Deactivate' : 'Activate' }}
                                                                        </button>
                                                                    </form>
                                                                    <form method="POST" action="{{ route('tier-rules.destroy', $tier) }}">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button class="nl-action-item nl-action-item-danger" type="submit">Delete</button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="8" class="px-4 py-10 text-center text-slate-400">
                                                            No tiers yet. Add the first tier to begin.
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-400">
                                        <div>
                                            Showing {{ $tiers->firstItem() ?? 0 }} to {{ $tiers->lastItem() ?? 0 }} of {{ $tiers->total() }} entries
                                        </div>
                                        <div class="flex items-center gap-2">
                                            @php
                                                $current = $tiers->currentPage();
                                                $last = $tiers->lastPage();
                                                $start = max($current - 1, 1);
                                                $end = min($current + 1, $last);
                                            @endphp
                                            <a class="rounded-lg border border-slate-700 px-3 py-1 {{ $tiers->onFirstPage() ? 'pointer-events-none text-slate-600' : 'text-slate-200' }}" href="{{ $tiers->previousPageUrl() ?? '#' }}">Prev</a>
                                            @for ($page = $start; $page <= $end; $page++)
                                                <a class="rounded-lg border border-slate-700 px-3 py-1 {{ $page === $current ? 'bg-slate-800 text-slate-100' : 'text-slate-300' }}" href="{{ $tiers->url($page) }}">{{ $page }}</a>
                                            @endfor
                                            <a class="rounded-lg border border-slate-700 px-3 py-1 {{ $current === $last ? 'pointer-events-none text-slate-600' : 'text-slate-200' }}" href="{{ $tiers->nextPageUrl() ?? '#' }}">Next</a>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </main>
                    </div>
                </div>
            </div>
        </div>

        <div id="create-tier-modal" class="nl-modal-backdrop" aria-hidden="true">
            <div class="nl-modal-panel">
                <div class="flex items-start justify-between border-b border-slate-800 px-6 py-5 nl-modal-divider">
                    <div>
                        <p id="tier-modal-eyebrow" class="text-xs uppercase tracking-[0.35em] text-slate-400">Add tier</p>
                        <p id="tier-modal-title" class="mt-2 text-lg font-semibold text-slate-100">Create a new tier</p>
                        <p id="tier-modal-description" class="mt-1 text-xs text-slate-400">New tiers start as inactive until they are activated.</p>
                    </div>
                    <button type="button" class="rounded-full border border-slate-700 px-2.5 py-1 text-xs text-slate-200" data-modal-close>
                        Close
                    </button>
                </div>
                <form
                    id="create-tier-form"
                    class="px-6 py-6"
                    action="{{ route('tier-rules.store') }}"
                    method="POST"
                    data-store-action="{{ route('tier-rules.store') }}"
                >
                    @csrf
                    <input type="hidden" name="_method" value="POST" data-tier-method>
                    @if ($errors->any())
                        <div class="mb-5 rounded-xl border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-xs text-rose-200" data-error-banner>
                            <p class="font-semibold text-rose-100">Fix the highlighted fields to continue.</p>
                            <p class="mt-1 text-rose-200">{{ $errors->first() }}</p>
                        </div>
                    @endif
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-2 sm:col-span-2">
                            <label class="nl-modal-label uppercase text-slate-400">Title</label>
                            <input class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="text" name="title" value="{{ old('title') }}" placeholder="e.g. Platinum" required>
                            @error('title')
                                <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="nl-modal-label uppercase text-slate-400">Minimum points</label>
                            <input class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="number" name="min_points" min="0" value="{{ old('min_points') }}" placeholder="0" required>
                            @error('min_points')
                                <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="nl-modal-label uppercase text-slate-400">Maximum points</label>
                            <input class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="number" name="max_points" min="0" value="{{ old('max_points') }}" placeholder="9999" required>
                            @error('max_points')
                                <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="nl-modal-label uppercase text-slate-400">Single point value</label>
                            <input class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="number" name="single_point_value" min="0" step="0.01" value="{{ old('single_point_value') }}" placeholder="1.00" required>
                            @error('single_point_value')
                                <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="nl-modal-label uppercase text-slate-400">Tier color</label>
                            <div class="flex items-center gap-3">
                                <input id="tier-color-input" class="nl-color-input w-full rounded-lg border border-slate-700 bg-slate-950/60 text-slate-200" type="color" name="color" value="{{ old('color', '#38bdf8') }}">
                                <span id="tier-color-preview" class="nl-color-chip" style="background-color: {{ old('color', '#38bdf8') }}"></span>
                            </div>
                            @error('color')
                                <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex flex-col gap-2 sm:col-span-2">
                            <label class="nl-modal-label uppercase text-slate-400">Description</label>
                            <textarea class="min-h-[120px] rounded-lg border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-slate-200" name="description" placeholder="Short description about this tier">{{ old('description') }}</textarea>
                            @error('description')
                                <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-slate-800 pt-5 nl-modal-divider">
                        <button type="button" class="rounded-xl border border-slate-700 px-4 py-2 text-xs text-slate-200" data-modal-close>
                            Cancel
                        </button>
                        <button id="tier-modal-submit" type="submit" class="nl-modal-primary rounded-xl px-5 py-2 text-xs">
                            Create
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            (function () {
                const storageKey = 'nl-theme';
                const body = document.body;
                const button = document.getElementById('theme-toggle');
                const modal = document.getElementById('create-tier-modal');
                const openModalButton = document.getElementById('open-create-tier');
                const closeButtons = modal ? modal.querySelectorAll('[data-modal-close]') : [];
                const form = document.getElementById('create-tier-form');
                const methodInput = form ? form.querySelector('[data-tier-method]') : null;
                const colorInput = document.getElementById('tier-color-input');
                const colorPreview = document.getElementById('tier-color-preview');
                const editButtons = document.querySelectorAll('[data-tier-edit]');
                const actionToggles = document.querySelectorAll('[data-action-toggle]');
                const actionMenus = document.querySelectorAll('[data-action-menu]');
                const modalEyebrow = document.getElementById('tier-modal-eyebrow');
                const modalTitle = document.getElementById('tier-modal-title');
                const modalDescription = document.getElementById('tier-modal-description');
                const modalSubmit = document.getElementById('tier-modal-submit');

                const storeAction = form ? form.getAttribute('data-store-action') : '';
                const createTexts = {
                    eyebrow: 'Add tier',
                    title: 'Create a new tier',
                    description: 'New tiers start as inactive until they are activated.',
                    submit: 'Create',
                };
                const editTexts = {
                    eyebrow: 'Edit tier',
                    title: 'Update tier details',
                    description: 'Save changes to update this tier instantly.',
                    submit: 'Save changes',
                };

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

                const resetForm = () => {
                    if (!form) {
                        return;
                    }
                    form.reset();
                    form.setAttribute('action', storeAction);
                    if (methodInput) {
                        methodInput.value = 'POST';
                    }
                    if (modalEyebrow) {
                        modalEyebrow.textContent = createTexts.eyebrow;
                    }
                    if (modalTitle) {
                        modalTitle.textContent = createTexts.title;
                    }
                    if (modalDescription) {
                        modalDescription.textContent = createTexts.description;
                    }
                    if (modalSubmit) {
                        modalSubmit.textContent = createTexts.submit;
                    }
                    if (colorPreview && colorInput) {
                        colorPreview.style.backgroundColor = colorInput.value;
                    }
                };

                const closeAllMenus = () => {
                    actionMenus.forEach((menu) => {
                        menu.classList.remove('is-open');
                    });
                };

                const setModalOpen = (isOpen) => {
                    if (!modal) {
                        return;
                    }
                    modal.classList.toggle('is-open', isOpen);
                    modal.setAttribute('aria-hidden', String(!isOpen));
                    if (!isOpen) {
                        resetForm();
                    }
                };

                const setEditMode = (button) => {
                    if (!form || !button) {
                        return;
                    }
                    form.setAttribute('action', button.dataset.tierAction || storeAction);
                    if (methodInput) {
                        methodInput.value = 'PATCH';
                    }
                    form.querySelector('[name="title"]').value = button.dataset.tierTitle || '';
                    form.querySelector('[name="min_points"]').value = button.dataset.tierMinPoints || '';
                    form.querySelector('[name="max_points"]').value = button.dataset.tierMaxPoints || '';
                    form.querySelector('[name="single_point_value"]').value = button.dataset.tierSingleValue || '';
                    form.querySelector('[name="color"]').value = button.dataset.tierColor || '#38bdf8';
                    form.querySelector('[name="description"]').value = button.dataset.tierDescription || '';
                    if (colorPreview && colorInput) {
                        colorPreview.style.backgroundColor = colorInput.value;
                    }
                    if (modalEyebrow) {
                        modalEyebrow.textContent = editTexts.eyebrow;
                    }
                    if (modalTitle) {
                        modalTitle.textContent = editTexts.title;
                    }
                    if (modalDescription) {
                        modalDescription.textContent = editTexts.description;
                    }
                    if (modalSubmit) {
                        modalSubmit.textContent = editTexts.submit;
                    }
                };

                if (openModalButton) {
                    openModalButton.addEventListener('click', () => {
                        closeAllMenus();
                        resetForm();
                        setModalOpen(true);
                    });
                }

                editButtons.forEach((button) => {
                    button.addEventListener('click', () => {
                        closeAllMenus();
                        setEditMode(button);
                        setModalOpen(true);
                    });
                });

                actionToggles.forEach((button) => {
                    button.addEventListener('click', (event) => {
                        event.stopPropagation();
                        const menu = button.parentElement ? button.parentElement.querySelector('[data-action-menu]') : null;
                        const willOpen = menu && !menu.classList.contains('is-open');
                        closeAllMenus();
                        if (menu && willOpen) {
                            menu.classList.add('is-open');
                        }
                    });
                });

                if (modal) {
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
                    if (event.key === 'Escape') {
                        setModalOpen(false);
                        closeAllMenus();
                    }
                });

                document.addEventListener('click', () => {
                    closeAllMenus();
                });

                if (colorInput && colorPreview) {
                    colorInput.addEventListener('input', () => {
                        colorPreview.style.backgroundColor = colorInput.value;
                    });
                }

                const shouldOpen = {{ $errors->any() ? 'true' : 'false' }};
                if (shouldOpen) {
                    setModalOpen(true);
                }
            })();
        </script>
    </body>
</html>
