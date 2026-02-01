{{-- This view shows redemption activity and summary details for a single coupon. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        {{-- The title uses the app name configuration with a fallback for local/dev environments. --}}
        <title>{{ config('app.name', 'NexLoyal') }} - Coupon View</title>
        {{-- Preconnect and load the UI font used across the admin experience. --}}
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        {{-- Vite builds and injects the compiled CSS for this page. --}}
        @vite(['resources/css/app.css'])
        <style>
            {{-- These styles define light-mode overrides and table appearance. --}}
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
            .nl-filter-input {
                height: 34px;
                font-size: 12px;
            }
            .nl-filter-label {
                font-size: 11px;
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
                            {{-- The header anchors coupon redemption reporting. --}}
                            <x-page-header eyebrow="" title="Coupon redemptions" breadcrumb="Rewards / Coupons / View">
                                <x-slot name="actions">
                                    {{-- Back navigates to the full coupon list. --}}
                                    <a href="{{ route('coupons') }}" class="rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-2 text-xs text-slate-200 nl-panel-muted">
                                        Back to coupons
                                    </a>
                                    {{-- Export uses the current filters for the CSV output. --}}
                                    <a href="{{ route('coupons.export', ['coupon' => $coupon, 'status' => request('status', 'all'), 'search' => request('search')]) }}" class="rounded-xl bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-900">
                                        Export CSV
                                    </a>
                                </x-slot>
                            </x-page-header>

                            {{-- These labels normalize coupon values for display. --}}
                            @php
                                $valueLabel = 'No value';
                                if ($coupon->type === 'free-shipping') {
                                    $valueLabel = 'Free shipping';
                                } elseif ($coupon->type === 'buy-x-get-y') {
                                    $valueLabel = 'Buy '.$coupon->buy_quantity.' get '.$coupon->get_quantity.' '.($coupon->buyx_discount_type === 'free' ? 'free' : 'discount');
                                } else {
                                    if ($coupon->value_type === 'percentage') {
                                        $valueLabel = rtrim(rtrim(number_format((float) $coupon->value, 2, '.', ''), '0'), '.').'%';
                                    } elseif ($coupon->value_type === 'fixed') {
                                        $valueLabel = '$'.number_format((float) $coupon->value, 2);
                                    }
                                    $valueLabel .= $coupon->type === 'amount-product' ? ' off product' : ' off order';
                                }
                                $typeLabel = [
                                    'amount-order' => 'Amount off order',
                                    'amount-product' => 'Amount off product',
                                    'buy-x-get-y' => 'Buy X get Y',
                                    'free-shipping' => 'Free shipping',
                                ][$coupon->type] ?? $coupon->type;
                            @endphp

                            {{-- Summary cards show core coupon metadata. --}}
                            <section class="mt-6 grid gap-4 lg:grid-cols-3">
                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 nl-panel">
                                    <p class="text-xs uppercase tracking-[0.35em] text-slate-400">Summary</p>
                                    <div class="mt-4 space-y-2 text-sm text-slate-200">
                                        <div><span class="text-slate-400">Name:</span> {{ $coupon->title }}</div>
                                        <div><span class="text-slate-400">Points:</span> {{ $coupon->points_value }}</div>
                                        <div><span class="text-slate-400">Value:</span> {{ $valueLabel }}</div>
                                        <div><span class="text-slate-400">Type:</span> {{ $typeLabel }}</div>
                                    </div>
                                </div>
                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 nl-panel">
                                    <p class="text-xs uppercase tracking-[0.35em] text-slate-400">Eligibility</p>
                                    <div class="mt-4 space-y-2 text-sm text-slate-200">
                                        <div><span class="text-slate-400">Tier:</span> {{ $coupon->tier?->title ?? 'All tiers' }}</div>
                                        <div><span class="text-slate-400">Start:</span> {{ optional($coupon->start_date)->format('Y-m-d') ?? '—' }}</div>
                                        <div><span class="text-slate-400">End:</span> {{ optional($coupon->end_date)->format('Y-m-d') ?? '—' }}</div>
                                    </div>
                                </div>
                                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 nl-panel">
                                    <p class="text-xs uppercase tracking-[0.35em] text-slate-400">Redemptions</p>
                                    <div class="mt-4 grid grid-cols-2 gap-3 text-sm text-slate-200">
                                        <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-3">
                                            <p class="text-xs text-slate-400">Purchased</p>
                                            <p class="text-lg font-semibold text-slate-100">{{ $summary['purchased'] }}</p>
                                        </div>
                                        <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-3">
                                            <p class="text-xs text-slate-400">Used</p>
                                            <p class="text-lg font-semibold text-slate-100">{{ $summary['used'] }}</p>
                                        </div>
                                        <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-3">
                                            <p class="text-xs text-slate-400">Unused</p>
                                            <p class="text-lg font-semibold text-slate-100">{{ $summary['unused'] }}</p>
                                        </div>
                                        <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-3">
                                            <p class="text-xs text-slate-400">Expired</p>
                                            <p class="text-lg font-semibold text-slate-100">{{ $summary['expired'] }}</p>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            {{-- The table below lists individual customer redemptions. --}}
                            <section class="mt-6 rounded-2xl border border-slate-800 bg-slate-900/70 p-5 nl-panel">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-100">Customer redemptions</p>
                                        <p class="text-xs text-slate-400">Search, filter, and review coupon usage.</p>
                                    </div>
                                    {{-- Filters are sent as query parameters. --}}
                                    <form class="flex flex-wrap items-end gap-3" method="GET" action="{{ route('coupons.view', $coupon) }}">
                                        <label class="nl-filter-label text-slate-400">
                                            Search
                                            <input name="search" class="nl-filter-input mt-1 w-56 rounded-lg border border-slate-700 bg-slate-950/60 px-2 text-xs text-slate-200" type="text" value="{{ request('search') }}" placeholder="Name, email, or code">
                                        </label>
                                        <label class="nl-filter-label text-slate-400">
                                            Status
                                            <select name="status" class="nl-filter-input mt-1 rounded-lg border border-slate-700 bg-slate-950/60 px-2 text-xs text-slate-200 nl-chip">
                                                <option value="all" @selected(request('status', 'all') === 'all')>All</option>
                                                <option value="used" @selected(request('status') === 'used')>Used</option>
                                                <option value="unused" @selected(request('status') === 'unused')>Unused</option>
                                                <option value="in_progress" @selected(request('status') === 'in_progress')>In progress</option>
                                                <option value="expired" @selected(request('status') === 'expired')>Expired</option>
                                            </select>
                                        </label>
                                        <button class="mt-4 rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-900" type="submit">
                                            Apply
                                        </button>
                                    </form>
                                </div>

                                <div class="mt-5 overflow-x-auto">
                                    <table class="w-full text-left text-xs">
                                        <thead class="nl-table-head text-slate-300">
                                            <tr>
                                                <th class="px-4 py-3">#</th>
                                                <th class="px-4 py-3">Customer</th>
                                                <th class="px-4 py-3">Coupon code</th>
                                                <th class="px-4 py-3">Status</th>
                                                <th class="px-4 py-3">Validity</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-800/70">
                                            {{-- Use the current page to calculate row numbering. --}}
                                            @php $rowStart = ($redemptions->currentPage() - 1) * $redemptions->perPage(); @endphp
                                            {{-- Each row represents a customer's coupon redemption. --}}
                                            @forelse ($redemptions as $index => $redemption)
                                                @php
                                                    $expiresAt = $redemption->expires_at ?? $coupon->end_date;
                                                    $status = 'Unused';
                                                    if ($redemption->status === 'used' || $redemption->used_at) {
                                                        $status = 'Used';
                                                    } elseif ($redemption->status === 'expired' || ($expiresAt && $expiresAt->isPast())) {
                                                        $status = 'Expired';
                                                    } elseif ($redemption->status === 'in_progress') {
                                                        $status = 'In progress';
                                                    }
                                                    $statusClass = $status === 'Used' ? 'bg-emerald-500/20 text-emerald-200' : ($status === 'Expired' ? 'bg-rose-500/20 text-rose-200' : 'bg-slate-500/20 text-slate-200');
                                                    $nameParts = array_filter([$redemption->customer?->first_name, $redemption->customer?->last_name]);
                                                    $name = $nameParts ? implode(' ', $nameParts) : ($redemption->customer?->email ?? 'Customer');
                                                    $validityLabel = $expiresAt ? $expiresAt->format('Y-m-d') : 'No expiry';
                                                @endphp
                                                <tr class="nl-table-row">
                                                    <td class="px-4 py-4 text-slate-300">{{ $rowStart + $index + 1 }}</td>
                                                    <td class="px-4 py-4">
                                                        <div class="text-slate-100">{{ $name }}</div>
                                                        <div class="text-slate-400">{{ $redemption->customer?->email ?? '—' }}</div>
                                                    </td>
                                                    <td class="px-4 py-4 text-slate-300">{{ $redemption->code ?? '—' }}</td>
                                                    <td class="px-4 py-4">
                                                        <span class="nl-badge {{ $statusClass }}">{{ $status }}</span>
                                                    </td>
                                                    <td class="px-4 py-4 text-slate-300">{{ $validityLabel }}</td>
                                                </tr>
                                            @empty
                                                {{-- Empty state when no redemptions match the filters. --}}
                                                <tr>
                                                    <td colspan="5" class="px-4 py-10 text-center text-slate-400">No redemptions match your filters.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>

                                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-400">
                                    <div>
                                        Showing {{ $redemptions->firstItem() ?? 0 }} to {{ $redemptions->lastItem() ?? 0 }} of {{ $redemptions->total() }} entries
                                    </div>
                                    <div class="flex items-center gap-2">
                                        {{-- Pagination uses a small window around the current page. --}}
                                        @php
                                            $current = $redemptions->currentPage();
                                            $last = $redemptions->lastPage();
                                            $start = max($current - 1, 1);
                                            $end = min($current + 1, $last);
                                        @endphp
                                        <a class="rounded-lg border border-slate-700 px-3 py-1 {{ $redemptions->onFirstPage() ? 'pointer-events-none text-slate-600' : 'text-slate-200' }}" href="{{ $redemptions->previousPageUrl() ?? '#' }}">Prev</a>
                                        @for ($page = $start; $page <= $end; $page++)
                                            <a class="rounded-lg border border-slate-700 px-3 py-1 {{ $page === $current ? 'bg-slate-800 text-slate-100' : 'text-slate-300' }}" href="{{ $redemptions->url($page) }}">{{ $page }}</a>
                                        @endfor
                                        <a class="rounded-lg border border-slate-700 px-3 py-1 {{ $current === $last ? 'pointer-events-none text-slate-600' : 'text-slate-200' }}" href="{{ $redemptions->nextPageUrl() ?? '#' }}">Next</a>
                                    </div>
                                </div>
                            </section>
                        </main>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
