<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'NexLoyal') }} - Coupons</title>
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
            .nl-badge {
                border-radius: 999px;
                padding: 4px 10px;
                font-size: 11px;
                font-weight: 600;
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
                max-width: 720px;
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
                                <a href="{{ route('customers') }}" class="flex items-center justify-between rounded-xl border border-transparent px-4 py-3 text-slate-300 hover:border-slate-800 hover:bg-slate-900/60 nl-sidebar-link">
                                    <span>Customers</span>
                                    <span class="text-xs text-slate-500 nl-text-muted">Segments</span>
                                </a>
                                <a href="{{ route('coupons') }}" class="flex items-center justify-between rounded-xl border border-slate-800 bg-slate-900/80 px-4 py-3 text-slate-100 nl-sidebar-link nl-sidebar-link-active">
                                    <span>Coupons</span>
                                    <span class="text-xs text-slate-400 nl-text-muted">Rewards</span>
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
                            <x-page-header eyebrow="" title="Coupons" breadcrumb="Rewards / Coupons">
                                <x-slot name="actions">
                                    <button id="theme-toggle" class="rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-2 text-xs text-slate-200 nl-panel-muted" type="button">
                                        Switch theme
                                    </button>
                                </x-slot>
                            </x-page-header>

                            <section class="mt-6 overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel">
                                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800/70 px-6 py-4">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-100">Coupons list</p>
                                        <p class="mt-1 text-xs text-slate-400">Track discount availability and tier eligibility.</p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <button id="open-create-coupon" class="rounded-xl bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-900" type="button">
                                            Add new coupon
                                        </button>
                                    </div>
                                </div>

                                <div class="px-6 py-5">
                                    <form method="GET" class="space-y-5">
                                        <div class="flex items-center gap-2 text-xs font-semibold text-slate-300">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                <path d="M3 5h18l-7 8v5l-4 2v-7L3 5z" />
                                            </svg>
                                            Filter
                                        </div>

                                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                            <div class="flex flex-col gap-2">
                                                <label class="nl-filter-label uppercase tracking-[0.2em] text-slate-400 nl-text-muted">Type</label>
                                                <select name="type" class="nl-filter-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200 nl-chip">
                                                    <option value="all" @selected(request('type', 'all') === 'all')>All</option>
                                                    <option value="amount-order" @selected(request('type') === 'amount-order')>Amount off order</option>
                                                    <option value="amount-product" @selected(request('type') === 'amount-product')>Amount off product</option>
                                                    <option value="buy-x-get-y" @selected(request('type') === 'buy-x-get-y')>Buy X get Y</option>
                                                    <option value="free-shipping" @selected(request('type') === 'free-shipping')>Free shipping</option>
                                                </select>
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <label class="nl-filter-label uppercase tracking-[0.2em] text-slate-400 nl-text-muted">Status</label>
                                                <select name="status" class="nl-filter-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200 nl-chip">
                                                    <option value="all" @selected(request('status', 'all') === 'all')>All</option>
                                                    <option value="active" @selected(request('status') === 'active')>Active</option>
                                                    <option value="scheduled" @selected(request('status') === 'scheduled')>Scheduled</option>
                                                    <option value="paused" @selected(request('status') === 'paused')>Paused</option>
                                                    <option value="expired" @selected(request('status') === 'expired')>Expired</option>
                                                </select>
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <label class="nl-filter-label uppercase tracking-[0.2em] text-slate-400 nl-text-muted">Start period</label>
                                                <input name="start_period" class="nl-filter-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200 nl-chip" type="date" value="{{ request('start_period') }}">
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <label class="nl-filter-label uppercase tracking-[0.2em] text-slate-400 nl-text-muted">End period</label>
                                                <input name="end_period" class="nl-filter-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200 nl-chip" type="date" value="{{ request('end_period') }}">
                                            </div>
                                        </div>

                                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                            <div class="flex flex-col gap-2">
                                                <label class="nl-filter-label uppercase tracking-[0.2em] text-slate-400 nl-text-muted">Tier</label>
                                                <select name="tier" class="nl-filter-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200 nl-chip">
                                                    <option value="all" @selected(request('tier', 'all') === 'all')>All tiers</option>
                                                    @foreach ($tiers as $tier)
                                                        <option value="{{ $tier->id }}" @selected((string) request('tier') === (string) $tier->id)>{{ $tier->title }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <label class="nl-filter-label uppercase tracking-[0.2em] text-slate-400 nl-text-muted">Value type</label>
                                                <select name="value_type" class="nl-filter-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200 nl-chip">
                                                    <option value="all" @selected(request('value_type', 'all') === 'all')>All</option>
                                                    <option value="percentage" @selected(request('value_type') === 'percentage')>Percentage</option>
                                                    <option value="fixed" @selected(request('value_type') === 'fixed')>Fixed amount</option>
                                                    <option value="none" @selected(request('value_type') === 'none')>No value</option>
                                                </select>
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <label class="nl-filter-label uppercase tracking-[0.2em] text-slate-400 nl-text-muted">Points</label>
                                                <select name="points" class="nl-filter-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200 nl-chip">
                                                    <option value="all" @selected(request('points', 'all') === 'all')>All</option>
                                                    <option value="under-200" @selected(request('points') === 'under-200')>Under 200</option>
                                                    <option value="200-500" @selected(request('points') === '200-500')>200-500</option>
                                                    <option value="500-1000" @selected(request('points') === '500-1000')>500-1000</option>
                                                    <option value="1000-plus" @selected(request('points') === '1000-plus')>1000+</option>
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
                                                <input name="search" class="h-7 w-56 rounded-lg border border-slate-700 bg-slate-950/60 px-2 text-xs text-slate-200" type="text" value="{{ request('search') }}" placeholder="Search by coupon name">
                                                <button type="submit" class="rounded-lg bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-900">Apply</button>
                                                <a class="rounded-lg border border-slate-700 px-3 py-1 text-xs text-slate-200" href="{{ route('coupons') }}">Reset</a>
                                            </div>
                                        </div>
                                    </form>

                                    @php
                                        $typeLabels = [
                                            'amount-order' => 'Amount off order',
                                            'amount-product' => 'Amount off product',
                                            'buy-x-get-y' => 'Buy X get Y',
                                            'free-shipping' => 'Free shipping',
                                        ];
                                        $statusLabels = [
                                            'active' => 'Active',
                                            'scheduled' => 'Scheduled',
                                            'paused' => 'Paused',
                                            'expired' => 'Expired',
                                        ];
                                        $statusClasses = [
                                            'active' => 'bg-emerald-500/20 text-emerald-200',
                                            'scheduled' => 'bg-sky-500/20 text-sky-200',
                                            'paused' => 'bg-amber-500/20 text-amber-200',
                                            'expired' => 'bg-rose-500/20 text-rose-200',
                                        ];
                                    @endphp

                                    <div class="mt-4 overflow-x-auto">
                                        <table class="w-full text-left text-xs">
                                            <thead class="nl-table-head text-slate-300">
                                                <tr>
                                                    <th class="px-4 py-3 font-semibold">Coupon</th>
                                                    <th class="px-4 py-3 font-semibold">Type</th>
                                                    <th class="px-4 py-3 font-semibold">Value</th>
                                                    <th class="px-4 py-3 font-semibold">Points</th>
                                                    <th class="px-4 py-3 font-semibold">Tier</th>
                                                    <th class="px-4 py-3 font-semibold">Start</th>
                                                    <th class="px-4 py-3 font-semibold">End</th>
                                                    <th class="px-4 py-3 font-semibold">Status</th>
                                                </tr>
                                            </thead>
                                                                                        <tbody class="divide-y divide-slate-800/80 text-slate-200">
                                                @forelse ($coupons as $coupon)
                                                    @php
                                                        $valueLabel = 'No value';
                                                        if ($coupon->value_type === 'percentage') {
                                                            $valueLabel = rtrim(rtrim(number_format((float) $coupon->value, 2, '.', ''), '0'), '.').'%';
                                                        } elseif ($coupon->value_type === 'fixed') {
                                                            $valueLabel = '$'.number_format((float) $coupon->value, 2);
                                                        }
                                                    @endphp
                                                    <tr class="nl-table-row">
                                                        <td class="px-4 py-4">
                                                            <div class="font-semibold text-slate-100">{{ $coupon->title }}</div>
                                                        </td>
                                                        <td class="px-4 py-4 text-slate-300">{{ $typeLabels[$coupon->type] ?? $coupon->type }}</td>
                                                        <td class="px-4 py-4 text-slate-300">{{ $valueLabel }}</td>
                                                        <td class="px-4 py-4 text-slate-300">{{ $coupon->points_value }}</td>
                                                        <td class="px-4 py-4 text-slate-300">{{ $coupon->tier?->title ?? 'All tiers' }}</td>
                                                        <td class="px-4 py-4 text-slate-300">{{ optional($coupon->start_date)->format('Y-m-d') }}</td>
                                                        <td class="px-4 py-4 text-slate-300">{{ optional($coupon->end_date)->format('Y-m-d') }}</td>
                                                        <td class="px-4 py-4">
                                                            @php
                                                                $statusLabel = $statusLabels[$coupon->status] ?? $coupon->status;
                                                                $statusClass = $statusClasses[$coupon->status] ?? 'bg-slate-500/20 text-slate-200';
                                                            @endphp
                                                            <span class="nl-badge {{ $statusClass }}">{{ $statusLabel }}</span>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="8" class="px-4 py-10 text-center text-slate-400">No coupons yet. Create your first reward to start.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>

                                                                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-400">
                                        <div>
                                            Showing {{ $coupons->firstItem() ?? 0 }} to {{ $coupons->lastItem() ?? 0 }} of {{ $coupons->total() }} entries
                                        </div>
                                        <div class="flex items-center gap-2">
                                            @php
                                                $current = $coupons->currentPage();
                                                $last = $coupons->lastPage();
                                                $start = max($current - 1, 1);
                                                $end = min($current + 1, $last);
                                            @endphp
                                            <a class="rounded-lg border border-slate-700 px-3 py-1 {{ $coupons->onFirstPage() ? 'pointer-events-none text-slate-600' : 'text-slate-200' }}" href="{{ $coupons->previousPageUrl() ?? '#' }}">Prev</a>
                                            @for ($page = $start; $page <= $end; $page++)
                                                <a class="rounded-lg border border-slate-700 px-3 py-1 {{ $page === $current ? 'bg-slate-800 text-slate-100' : 'text-slate-300' }}" href="{{ $coupons->url($page) }}">{{ $page }}</a>
                                            @endfor
                                            <a class="rounded-lg border border-slate-700 px-3 py-1 {{ $current === $last ? 'pointer-events-none text-slate-600' : 'text-slate-200' }}" href="{{ $coupons->nextPageUrl() ?? '#' }}">Next</a>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </main>
                    </div>
                </div>
            </div>
        </div>
        <div id="create-coupon-modal" class="nl-modal-backdrop" aria-hidden="true">
    <div class="nl-modal-panel">
        <div class="flex items-start justify-between border-b border-slate-800 px-6 py-5 nl-modal-divider">
            <div>
                <p class="text-xs uppercase tracking-[0.35em] text-slate-400">Add coupon</p>
                <p class="mt-2 text-lg font-semibold text-slate-100">Create a new coupon reward</p>
                <p class="mt-1 text-xs text-slate-400">Set the discount value, tier eligibility, and redemption points.</p>
            </div>
            <button type="button" class="rounded-full border border-slate-700 px-2.5 py-1 text-xs text-slate-200" data-modal-close>
                Close
            </button>
        </div>
                <form id="create-coupon-form" class="px-6 py-6" method="POST" action="{{ route('coupons.store') }}">
            @csrf
            @if ($errors->any())
                <div class="mb-5 rounded-xl border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-xs text-rose-200" data-error-banner>
                    <p class="font-semibold text-rose-100">Fix the highlighted fields to continue.</p>
                    <p class="mt-1 text-rose-200">{{ $errors->first() }}</p>
                </div>
            @endif
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-2 sm:col-span-2">
                    <label class="nl-modal-label uppercase text-slate-400">Title</label>
                    <input class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="text" name="title" value="{{ old('title') }}" placeholder="e.g. Welcome 10" required>
                    @error('title')
                        <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-col gap-2">
                    <label class="nl-modal-label uppercase text-slate-400">Type</label>
                    <select class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" name="type" required>
                        <option value="" disabled @selected(!old('type'))>Select</option>
                        <option value="amount-order" @selected(old('type') === 'amount-order')>Amount off order</option>
                        <option value="amount-product" @selected(old('type') === 'amount-product')>Amount off product</option>
                        <option value="buy-x-get-y" @selected(old('type') === 'buy-x-get-y')>Buy X get Y</option>
                        <option value="free-shipping" @selected(old('type') === 'free-shipping')>Free shipping</option>
                    </select>
                    @error('type')
                        <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-col gap-2">
                    <label class="nl-modal-label uppercase text-slate-400">Value type</label>
                    <select class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" name="value_type" required>
                        <option value="" disabled @selected(!old('value_type'))>Select</option>
                        <option value="percentage" @selected(old('value_type') === 'percentage')>Percentage</option>
                        <option value="fixed" @selected(old('value_type') === 'fixed')>Fixed amount</option>
                        <option value="none" @selected(old('value_type') === 'none')>No value</option>
                    </select>
                    @error('value_type')
                        <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-col gap-2">
                    <label class="nl-modal-label uppercase text-slate-400">Value</label>
                    <input class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="text" name="value" value="{{ old('value') }}" placeholder="e.g. 10% or 25.00">
                    @error('value')
                        <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-col gap-2">
                    <label class="nl-modal-label uppercase text-slate-400">Points value</label>
                    <input class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="number" name="points_value" min="0" value="{{ old('points_value') }}" placeholder="e.g. 150" required>
                    @error('points_value')
                        <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-col gap-2">
                    <label class="nl-modal-label uppercase text-slate-400">Tier</label>
                    <select class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" name="tier_id">
                        <option value="" @selected(!old('tier_id'))>All tiers</option>
                        @foreach ($tiers as $tier)
                            <option value="{{ $tier->id }}" @selected((string) old('tier_id') === (string) $tier->id)>{{ $tier->title }}</option>
                        @endforeach
                    </select>
                    @error('tier_id')
                        <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-col gap-2">
                    <label class="nl-modal-label uppercase text-slate-400">Start date</label>
                    <input class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="date" name="start_date" value="{{ old('start_date') }}" required>
                    @error('start_date')
                        <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-col gap-2">
                    <label class="nl-modal-label uppercase text-slate-400">End date</label>
                    <input class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="date" name="end_date" value="{{ old('end_date') }}" required>
                    @error('end_date')
                        <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-col gap-2 sm:col-span-2">
                    <label class="nl-modal-label uppercase text-slate-400">Description</label>
                    <textarea class="min-h-[100px] rounded-lg border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-slate-200" name="description" placeholder="Add coupon details for the team...">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                    @enderror
                </div>
            </div>
            <div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-slate-800 pt-5 nl-modal-divider">
                <button type="button" class="rounded-xl border border-slate-700 px-4 py-2 text-xs text-slate-200" data-modal-close>
                    Cancel
                </button>
                <button type="submit" class="nl-modal-primary rounded-xl px-5 py-2 text-xs">
                    Create coupon
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
                const modal = document.getElementById('create-coupon-modal');
                const openModalButton = document.getElementById('open-create-coupon');
                const closeButtons = modal ? modal.querySelectorAll('[data-modal-close]') : [];

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
                const shouldOpenSettings = window.location.pathname.startsWith('/settings');
                if (settingsMenu) {
                    settingsMenu.classList.toggle('hidden', !shouldOpenSettings);
                }
                if (settingsToggle && settingsMenu) {
                    settingsToggle.addEventListener('click', () => {
                        settingsMenu.classList.toggle('hidden');
                    });
                }

                const setModalOpen = (isOpen) => {
                    if (!modal) {
                        return;
                    }
                    modal.classList.toggle('is-open', isOpen);
                    modal.setAttribute('aria-hidden', String(!isOpen));
                };

                if (openModalButton) {
                    openModalButton.addEventListener('click', () => setModalOpen(true));
                }

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
                    }
                });

                const shouldOpen = {{ $errors->any() ? 'true' : 'false' }};
                if (shouldOpen) {
                    setModalOpen(true);
                }

            })();
        </script>
    </body>
</html>






