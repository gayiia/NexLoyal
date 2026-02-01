{{-- This view manages reward coupons, including filtering, creation, and per-coupon actions. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        {{-- The title uses the app name configuration with a fallback for local/dev environments. --}}
        <title>{{ config('app.name', 'NexLoyal') }} - Coupons</title>
        {{-- Preconnect and load the UI font used across the admin experience. --}}
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        {{-- Vite builds and injects the compiled CSS for this page. --}}
        @vite(['resources/css/app.css'])
        <style>
            {{-- These styles define light-mode overrides plus coupon-specific UI elements. --}}
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
                display: block;
            }
            .nl-action-item:hover {
                background: rgba(30, 41, 59, 0.6);
            }
            .nl-action-item-danger {
                color: #fda4af;
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
                display: flex;
                flex-direction: column;
                max-height: calc(100vh - 48px);
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
            .nl-modal-form {
                display: flex;
                flex-direction: column;
                flex: 1 1 auto;
                min-height: 0;
            }
            .nl-modal-body {
                flex: 1 1 auto;
                min-height: 0;
                overflow: auto;
                padding: 24px;
            }
            .nl-modal-primary {
                background: linear-gradient(135deg, #38bdf8, #2563eb);
                color: #020617;
                font-weight: 600;
                box-shadow: 0 12px 24px rgba(37, 99, 235, 0.4);
            }
            .nl-product-shell {
                border: 1px solid rgba(148, 163, 184, 0.35);
                border-radius: 14px;
                background: rgba(2, 6, 23, 0.5);
                padding: 12px;
            }
            .nl-product-scroll {
                max-height: 220px;
                overflow: auto;
            }
            .nl-product-grid {
                display: grid;
                gap: 10px;
            }
            @media (min-width: 640px) {
                .nl-product-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }
            @media (min-width: 1024px) {
                .nl-product-grid {
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                }
            }
            .nl-product-card {
                border: 1px solid rgba(148, 163, 184, 0.25);
                background: rgba(15, 23, 42, 0.55);
                border-radius: 12px;
                padding: 10px 12px;
                display: flex;
                gap: 10px;
                align-items: flex-start;
            }
            .nl-product-card input {
                margin-top: 3px;
            }
            .nl-product-title {
                font-size: 12px;
                line-height: 1.4;
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
            .nl-theme-light .nl-product-shell {
                background: rgba(241, 245, 249, 0.7);
                border-color: rgba(148, 163, 184, 0.4);
            }
            .nl-theme-light .nl-product-card {
                background: rgba(255, 255, 255, 0.8);
                border-color: rgba(148, 163, 184, 0.4);
            }
            .nl-theme-light .nl-product-title {
                color: #0f172a;
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
        </style>
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
        <div class="min-h-screen bg-[radial-gradient(900px_circle_at_top,rgba(56,189,248,0.18),transparent_60%)]">
            <div class="min-h-screen bg-[radial-gradient(700px_circle_at_bottom,rgba(30,64,175,0.22),transparent_60%)]">
                <div class="min-h-screen bg-[linear-gradient(120deg,rgba(15,23,42,0.9),rgba(2,6,23,0.95))] nl-shell">
                    <div class="flex min-h-screen flex-col lg:flex-row">
                        {{-- The admin sidebar is shared across the dashboard and provides navigation. --}}
                        @include('partials.admin-sidebar')

                        <main class="flex-1 px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
                            {{-- The header anchors coupon management actions. --}}
                            <x-page-header eyebrow="" title="Coupons" breadcrumb="Rewards / Coupons">
                                <x-slot name="actions">
                                    <button id="theme-toggle" class="rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-2 text-xs text-slate-200 nl-panel-muted" type="button">
                                        Switch theme
                                    </button>
                                </x-slot>
                            </x-page-header>

                            <section class="mt-6 overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel">
                                {{-- Shopify or coupon errors are surfaced near the top of the list. --}}
                                @if ($errors->has('shopify') || $errors->has('coupon'))
                                    <div class="border-b border-slate-800/70 px-6 py-4 text-xs text-rose-200">
                                        <p class="font-semibold text-rose-100">Action failed.</p>
                                        <p class="mt-1 text-rose-200">{{ $errors->first('shopify') ?: $errors->first('coupon') }}</p>
                                    </div>
                                @endif
                                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800/70 px-6 py-4">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-100">Coupons list</p>
                                        <p class="mt-1 text-xs text-slate-400">Track discount availability and tier eligibility.</p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        {{-- Export preserves current filters in the query string. --}}
                                        <a class="rounded-xl border border-slate-700 px-4 py-2 text-xs text-slate-200" href="{{ route('coupons.export.list', request()->query()) }}">
                                            Export CSV
                                        </a>
                                        {{-- This opens the modal for creating a new coupon. --}}
                                        <button id="open-create-coupon" class="rounded-xl bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-900" type="button">
                                            Add new coupon
                                        </button>
                                    </div>
                                </div>

                                <div class="px-6 py-5">
                                    {{-- Filters and search are submitted as GET query parameters. --}}
                                    {{-- Filters control which coupons are listed in the table below. --}}
                                    <form method="GET" class="space-y-5">
                                        <div class="flex items-center gap-2 text-xs font-semibold text-slate-300">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                <path d="M3 5h18l-7 8v5l-4 2v-7L3 5z" />
                                            </svg>
                                            Filter
                                        </div>

                                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                            {{-- These select inputs filter by coupon type, status, and date range. --}}
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
                                                    <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                                                    <option value="active" @selected(request('status') === 'active')>Active</option>
                                                    <option value="paused" @selected(request('status') === 'paused')>Paused</option>
                                                    <option value="scheduled" @selected(request('status') === 'scheduled')>Scheduled</option>
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
                                            {{-- These inputs filter by tier, value type, and point cost. --}}
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
                                            'draft' => 'Draft',
                                            'active' => 'Active',
                                            'scheduled' => 'Scheduled',
                                            'paused' => 'Paused',
                                            'expired' => 'Expired',
                                        ];
                                        $statusClasses = [
                                            'draft' => 'bg-slate-500/20 text-slate-200',
                                            'active' => 'bg-emerald-500/20 text-emerald-200',
                                            'scheduled' => 'bg-sky-500/20 text-sky-200',
                                            'paused' => 'bg-amber-500/20 text-amber-200',
                                            'expired' => 'bg-rose-500/20 text-rose-200',
                                        ];
                                    @endphp

                                    <div class="mt-4">
                                        {{-- Status classes map coupon lifecycle state to badge styles. --}}
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
                                                    <th class="px-4 py-3 font-semibold">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-800/80 text-slate-200">
                                                {{-- Each row represents a coupon with derived display values. --}}
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
                                                        {{-- The title and code identify the reward and its redeemable code. --}}
                                                        <div class="font-semibold text-slate-100">{{ $coupon->title }}</div>
                                                        @if ($coupon->code)
                                                            <div class="text-slate-400">{{ $coupon->code }}</div>
                                                        @endif
                                                        </td>
                                                        <td class="px-4 py-4 text-slate-300">{{ $typeLabels[$coupon->type] ?? $coupon->type }}</td>
                                                        <td class="px-4 py-4 text-slate-300">{{ $valueLabel }}</td>
                                                        <td class="px-4 py-4 text-slate-300">{{ $coupon->points_value }}</td>
                                                        <td class="px-4 py-4 text-slate-300">{{ $coupon->tier?->title ?? 'All tiers' }}</td>
                                                        <td class="px-4 py-4 text-slate-300">{{ optional($coupon->start_date)->format('Y-m-d') }}</td>
                                                        <td class="px-4 py-4 text-slate-300">{{ optional($coupon->end_date)->format('Y-m-d') }}</td>
                                                          <td class="px-4 py-4">
                                                          @php
                                                              if ($coupon->is_mystery_box_coupon) {
                                                                  $statusLabel = 'Mystery Box';
                                                                  $statusClass = 'bg-cyan-500/20 text-cyan-200';
                                                              } elseif ($coupon->is_ai_cluster_coupon) {
                                                                  $statusLabel = 'AI Cluster';
                                                                  $statusClass = 'bg-indigo-500/20 text-indigo-200';
                                                              } else {
                                                                  $statusLabel = $statusLabels[$coupon->status] ?? $coupon->status;
                                                                  $statusClass = $statusClasses[$coupon->status] ?? 'bg-slate-500/20 text-slate-200';
                                                              }
                                                          @endphp
                                                              {{-- Special coupon flags override the usual status label. --}}
                                                              <span class="nl-badge {{ $statusClass }}">{{ $statusLabel }}</span>
                                                          </td>
                                                        <td class="px-4 py-4">
                                                            <div class="relative inline-flex">
                                                                <button class="nl-action-trigger" type="button" data-action-toggle>
                                                                    Actions
                                                                </button>
                                                                <div class="nl-action-menu" data-action-menu>
                                                                    {{-- Edit is only allowed while the coupon is still a draft. --}}
                                                                    @if ($coupon->status === 'draft')
                                                                        <a class="nl-action-item" href="{{ route('coupons.edit', $coupon) }}">Edit</a>
                                                                    @else
                                                                        <span class="nl-action-item text-slate-500 cursor-not-allowed">Edit (locked)</span>
                                                                    @endif
                                                                    {{-- View is only available for active coupons. --}}
                                                                    @if ($coupon->status === 'active')
                                                                        <a class="nl-action-item" href="{{ route('coupons.view', $coupon) }}">View</a>
                                                                    @endif
                                                                    {{-- Status transitions are limited to valid lifecycle states. --}}
                                                                    @if (in_array($coupon->status, ['draft', 'paused'], true))
                                                                        <form method="POST" action="{{ route('coupons.activate', $coupon) }}">
                                                                            @csrf
                                                                            @method('PATCH')
                                                                            <button class="nl-action-item" type="submit">Activate</button>
                                                                        </form>
                                                                    @elseif ($coupon->status === 'active')
                                                                        <form method="POST" action="{{ route('coupons.deactivate', $coupon) }}">
                                                                            @csrf
                                                                            @method('PATCH')
                                                                            <button class="nl-action-item" type="submit">Deactivate</button>
                                                                        </form>
                                                                    @endif
                                                                    {{-- Delete removes the coupon record and its Shopify linkage. --}}
                                                                    <form method="POST" action="{{ route('coupons.destroy', $coupon) }}">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button class="nl-action-item nl-action-item-danger" type="submit">Delete</button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    {{-- Empty state when no coupons match the filters. --}}
                                                    <tr>
                                                        <td colspan="9" class="px-4 py-10 text-center text-slate-400">No coupons yet. Create your first reward to start.</td>
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
                                            {{-- Pagination uses a small window around the current page. --}}
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
        {{-- The create-coupon modal collects all fields needed for Shopify discounts. --}}
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
        {{-- This form posts a new coupon and syncs it with Shopify via the backend. --}}
        <form id="create-coupon-form" class="nl-modal-form" method="POST" action="{{ route('coupons.store') }}">
            @csrf
            <div class="nl-modal-body">
                {{-- Validation errors are shown inside the modal to keep context. --}}
                @if ($errors->any())
                    <div class="mb-5 rounded-xl border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-xs text-rose-200" data-error-banner>
                        <p class="font-semibold text-rose-100">Fix the highlighted fields to continue.</p>
                        <p class="mt-1 text-rose-200">{{ $errors->first() }}</p>
                    </div>
                @endif
                {{-- If Shopify products cannot be loaded, product-specific inputs may be unusable. --}}
                @if ($productError)
                    <div class="mb-5 rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-xs text-amber-200">
                        <p class="font-semibold text-amber-100">Shopify products unavailable.</p>
                        <p class="mt-1 text-amber-200">{{ $productError }}</p>
                    </div>
                @endif
                <div class="grid gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-2 sm:col-span-2">
                    {{-- Title is the human-readable label for the coupon. --}}
                    <label class="nl-modal-label uppercase text-slate-400">Title</label>
                    <input class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="text" name="title" value="{{ old('title') }}" placeholder="e.g. Welcome 10" required>
                    @error('title')
                        <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-col gap-2">
                    {{-- Type controls which discount fields are shown below. --}}
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
                <div class="flex flex-col gap-2" data-type-section="amount-order,amount-product">
                    {{-- Value type determines whether the discount is percentage or fixed. --}}
                    <label class="nl-modal-label uppercase text-slate-400">Value type</label>
                    <select class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" name="value_type" data-required>
                        <option value="" disabled @selected(!old('value_type'))>Select</option>
                        <option value="percentage" @selected(old('value_type') === 'percentage')>Percentage</option>
                        <option value="fixed" @selected(old('value_type') === 'fixed')>Fixed amount</option>
                        <option value="none" @selected(old('value_type') === 'none')>No value</option>
                    </select>
                    @error('value_type')
                        <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-col gap-2" data-type-section="amount-order,amount-product">
                    {{-- Value is interpreted based on the selected value type. --}}
                    <label class="nl-modal-label uppercase text-slate-400">Value</label>
                    <input class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="text" name="value" value="{{ old('value') }}" placeholder="e.g. 10% or 25.00" data-required>
                    @error('value')
                        <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-col gap-2">
                    {{-- Points value controls the cost to redeem this coupon. --}}
                    <label class="nl-modal-label uppercase text-slate-400">Points value</label>
                    <input class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="number" name="points_value" min="0" value="{{ old('points_value') }}" placeholder="e.g. 150" required>
                    @error('points_value')
                        <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-col gap-2">
                    <label class="nl-modal-label uppercase text-slate-400">Mystery Box coupon</label>
                    {{-- Flags indicate coupons reserved for mystery box rewards. --}}
                    <label class="inline-flex items-center gap-2 text-sm text-slate-200">
                        <input type="checkbox" name="is_mystery_box_coupon" value="1" @checked(old('is_mystery_box_coupon'))>
                        <span>This is a Mystery Box coupon</span>
                    </label>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="nl-modal-label uppercase text-slate-400">AI Insights coupon</label>
                    {{-- Flags indicate coupons issued by AI clustering. --}}
                    <label class="inline-flex items-center gap-2 text-sm text-slate-200">
                        <input type="checkbox" name="is_ai_cluster_coupon" value="1" @checked(old('is_ai_cluster_coupon'))>
                        <span>This is an AI Cluster coupon</span>
                    </label>
                </div>
                <div class="flex flex-col gap-2">
                    {{-- Tier limits coupon visibility to eligible customers. --}}
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
                <div class="flex flex-col gap-2 sm:col-span-2" data-type-section="amount-product">
                    {{-- Product-specific discounts require selecting eligible products. --}}
                    <label class="nl-modal-label uppercase text-slate-400">Eligible products</label>
                    <div class="nl-product-shell">
                        <div class="nl-product-scroll">
                            <div class="nl-product-grid">
                                {{-- Products are sourced from Shopify and selectable for this discount. --}}
                                @forelse ($products as $product)
                                    <label class="nl-product-card">
                                        <input type="checkbox" name="product_ids[]" value="{{ $product['id'] }}" @checked(in_array($product['id'], old('product_ids', []), true))>
                                        <span class="nl-product-title text-slate-200">{{ $product['title'] }}</span>
                                    </label>
                                @empty
                                    <p class="text-xs text-slate-400">No products found.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    @error('product_ids')
                        <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-col gap-2 sm:col-span-2" data-type-section="buy-x-get-y">
                    {{-- Buy X get Y discounts require selecting the buy products. --}}
                    <label class="nl-modal-label uppercase text-slate-400">Buy products</label>
                    <div class="nl-product-shell">
                        <div class="nl-product-scroll">
                            <div class="nl-product-grid">
                                {{-- Products are sourced from Shopify and selectable for this discount. --}}
                                @forelse ($products as $product)
                                    <label class="nl-product-card">
                                        <input type="checkbox" name="buy_product_ids[]" value="{{ $product['id'] }}" @checked(in_array($product['id'], old('buy_product_ids', []), true))>
                                        <span class="nl-product-title text-slate-200">{{ $product['title'] }}</span>
                                    </label>
                                @empty
                                    <p class="text-xs text-slate-400">No products found.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    @error('buy_product_ids')
                        <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-col gap-2" data-type-section="buy-x-get-y">
                    {{-- Quantity required to trigger the discount. --}}
                    <label class="nl-modal-label uppercase text-slate-400">Buy quantity</label>
                    <input class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="number" name="buy_quantity" min="1" value="{{ old('buy_quantity', 1) }}" data-required>
                    @error('buy_quantity')
                        <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-col gap-2" data-type-section="buy-x-get-y">
                    {{-- Quantity granted when the buy threshold is met. --}}
                    <label class="nl-modal-label uppercase text-slate-400">Get quantity</label>
                    <input class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="number" name="get_quantity" min="1" value="{{ old('get_quantity', 1) }}" data-required>
                    @error('get_quantity')
                        <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-col gap-2 sm:col-span-2" data-type-section="buy-x-get-y">
                    {{-- Select which products are granted in the offer. --}}
                    <label class="nl-modal-label uppercase text-slate-400">Get products</label>
                    <div class="nl-product-shell">
                        <div class="nl-product-scroll">
                            <div class="nl-product-grid">
                                {{-- Products are sourced from Shopify and selectable for this discount. --}}
                                @forelse ($products as $product)
                                    <label class="nl-product-card">
                                        <input type="checkbox" name="get_product_ids[]" value="{{ $product['id'] }}" @checked(in_array($product['id'], old('get_product_ids', []), true))>
                                        <span class="nl-product-title text-slate-200">{{ $product['title'] }}</span>
                                    </label>
                                @empty
                                    <p class="text-xs text-slate-400">No products found.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    @error('get_product_ids')
                        <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-col gap-3 sm:col-span-2" data-type-section="buy-x-get-y">
                    {{-- Discount type determines how the "get" items are priced. --}}
                    <label class="nl-modal-label uppercase text-slate-400">At a discounted value</label>
                    <div class="flex flex-wrap gap-4 text-sm text-slate-200">
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" name="buyx_discount_type" value="percentage" @checked(old('buyx_discount_type') === 'percentage') data-buyx-discount>
                            <span>Percentage</span>
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" name="buyx_discount_type" value="amount" @checked(old('buyx_discount_type') === 'amount') data-buyx-discount>
                            <span>Amount off each</span>
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" name="buyx_discount_type" value="free" @checked(old('buyx_discount_type', 'free') === 'free') data-buyx-discount>
                            <span>Free</span>
                        </label>
                    </div>
                    <div>
                        {{-- Discount value is disabled when the selection is free. --}}
                        <input class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="number" step="0.01" min="0" name="buyx_discount_value" value="{{ old('buyx_discount_value') }}" placeholder="Discount value" data-buyx-value>
                        @error('buyx_discount_type')
                            <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                        @enderror
                        @error('buyx_discount_value')
                            <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    {{-- Start and end dates control coupon availability. --}}
                    <label class="nl-modal-label uppercase text-slate-400">Start date</label>
                    <input class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="date" name="start_date" value="{{ old('start_date') }}" required>
                    @error('start_date')
                        <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-col gap-2">
                    {{-- End date is required to align with Shopify price rules. --}}
                    <label class="nl-modal-label uppercase text-slate-400">End date</label>
                    <input class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="date" name="end_date" value="{{ old('end_date') }}" required>
                    @error('end_date')
                        <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-col gap-2 sm:col-span-2">
                    {{-- Description is internal-only for admin context. --}}
                    <label class="nl-modal-label uppercase text-slate-400">Description</label>
                    <textarea class="min-h-[100px] rounded-lg border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-slate-200" name="description" placeholder="Add coupon details for the team...">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="text-xs text-rose-300" data-error-message>{{ $message }}</p>
                    @enderror
                </div>
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-800 px-6 py-5 nl-modal-divider">
                {{-- Cancel closes the modal without creating a coupon. --}}
                <button type="button" class="rounded-xl border border-slate-700 px-4 py-2 text-xs text-slate-200" data-modal-close>
                    Cancel
                </button>
                {{-- Submit creates the coupon and triggers backend sync. --}}
                <button type="submit" class="nl-modal-primary rounded-xl px-5 py-2 text-xs">
                    Create coupon
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
                const modal = document.getElementById('create-coupon-modal');
                const openModalButton = document.getElementById('open-create-coupon');
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

                const typeSelect = modal ? modal.querySelector('[name="type"]') : null;
                const typeSections = modal ? modal.querySelectorAll('[data-type-section]') : [];

                // Show or hide fields based on the selected coupon type.
                const updateTypeSections = () => {
                    const activeType = typeSelect ? typeSelect.value : '';
                    typeSections.forEach((section) => {
                        const types = (section.dataset.typeSection || '').split(',');
                        const isActive = types.includes(activeType);
                        section.classList.toggle('hidden', !isActive);
                        section.querySelectorAll('[data-required]').forEach((input) => {
                            input.required = isActive;
                        });
                    });
                };

                if (typeSelect) {
                    typeSelect.addEventListener('change', updateTypeSections);
                }

                updateTypeSections();

                const discountRadios = modal ? modal.querySelectorAll('[data-buyx-discount]') : [];
                const discountValueInput = modal ? modal.querySelector('[data-buyx-value]') : null;
                // Disable discount value input when the selection is "free".
                const updateBuyXDiscount = () => {
                    if (!discountValueInput) {
                        return;
                    }
                    const selected = modal ? modal.querySelector('[data-buyx-discount]:checked') : null;
                    const isFree = selected && selected.value === 'free';
                    discountValueInput.disabled = Boolean(isFree);
                    discountValueInput.required = !isFree;
                    if (isFree) {
                        discountValueInput.value = '';
                    }
                };

                discountRadios.forEach((radio) => {
                    radio.addEventListener('change', updateBuyXDiscount);
                });
                updateBuyXDiscount();

                const actionMenus = document.querySelectorAll('[data-action-menu]');
                const actionToggles = document.querySelectorAll('[data-action-toggle]');

                // Close all action menus before opening a new one.
                const closeActionMenus = () => {
                    actionMenus.forEach((menu) => menu.classList.remove('is-open'));
                };

                actionToggles.forEach((toggle) => {
                    toggle.addEventListener('click', (event) => {
                        // Prevent click bubbling so only the current menu toggles.
                        event.stopPropagation();
                        const menu = toggle.parentElement ? toggle.parentElement.querySelector('[data-action-menu]') : null;
                        const willOpen = menu && !menu.classList.contains('is-open');
                        closeActionMenus();
                        if (menu && willOpen) {
                            menu.classList.add('is-open');
                        }
                    });
                });

                // Clicking anywhere else closes any open action menu.
                document.addEventListener('click', () => closeActionMenus());

                // Open or close the create-coupon modal.
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
                    // Escape closes both the modal and any open action menu.
                    if (event.key === 'Escape') {
                        closeActionMenus();
                        setModalOpen(false);
                    }
                });

                // Reopen the modal if validation failed on a previous submit.
                const shouldOpen = {{ $errors->any() && old('title') ? 'true' : 'false' }};
                if (shouldOpen) {
                    setModalOpen(true);
                }

            })();
        </script>
    </body>
</html>







