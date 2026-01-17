<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'NexLoyal') }} - Mystery Box View</title>
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
            .nl-table-head { background: rgba(15, 23, 42, 0.6); }
            .nl-table-row:hover { background: rgba(30, 41, 59, 0.45); }
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
                        <x-page-header eyebrow="" title="{{ $mysteryBox->name }}" breadcrumb="Coupons / Mystery Box">
                            <x-slot name="actions">
                                <a href="{{ route('mystery-boxes') }}" class="rounded-xl border border-slate-800 px-4 py-2 text-xs text-slate-200">
                                    Back to list
                                </a>
                            </x-slot>
                        </x-page-header>

                        <section class="mt-6 grid gap-4 lg:grid-cols-4">
                            <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 nl-panel">
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Status</p>
                                <p class="mt-2 text-lg font-semibold text-slate-100">
                                    {{ $mysteryBox->is_active ? 'Active' : 'Inactive' }}
                                </p>
                            </div>
                            <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 nl-panel">
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Tiers</p>
                                <p class="mt-2 text-lg font-semibold text-slate-100">
                                    {{ $tierNames ? implode(', ', $tierNames) : 'All tiers' }}
                                </p>
                            </div>
                            <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 nl-panel">
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Claim rule</p>
                                <p class="mt-2 text-lg font-semibold text-slate-100">
                                    {{ str_replace('_', ' ', $mysteryBox->claim_rule) }}
                                </p>
                            </div>
                            <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 nl-panel">
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Claims</p>
                                <p class="mt-2 text-lg font-semibold text-slate-100">
                                    {{ $summary['total'] }} total
                                </p>
                                <p class="text-xs text-slate-400">Active {{ $summary['active'] }} • Used {{ $summary['used'] }}</p>
                            </div>
                        </section>

                        <section class="mt-6 rounded-2xl border border-slate-800 bg-slate-900/70 p-6 nl-panel">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-100">Claimed rewards</p>
                                    <p class="mt-1 text-xs text-slate-400">Track customer wins and redemptions.</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('mystery-boxes.export', $mysteryBox) . '?' . http_build_query(request()->only(['status', 'search'])) }}"
                                       class="rounded-xl border border-slate-700 px-4 py-2 text-xs text-slate-200">
                                        Export CSV
                                    </a>
                                </div>
                            </div>

                            <form method="GET" class="mt-4 flex flex-wrap gap-3 text-xs">
                                <input type="text" name="search" placeholder="Search customer or coupon" value="{{ request('search') }}"
                                       class="rounded-lg border border-slate-700 bg-slate-950/60 px-3 py-2 text-slate-200">
                                <select name="status" class="rounded-lg border border-slate-700 bg-slate-950/60 px-3 py-2 text-slate-200">
                                    <option value="all" @selected(request('status', 'all') === 'all')>All</option>
                                    <option value="unused" @selected(request('status') === 'unused')>Unused</option>
                                    <option value="used" @selected(request('status') === 'used')>Used</option>
                                    <option value="expired" @selected(request('status') === 'expired')>Expired</option>
                                </select>
                                <button type="submit" class="rounded-lg border border-slate-700 px-3 py-2 text-slate-200">Filter</button>
                            </form>

                            <div class="mt-5 overflow-x-auto">
                                <table class="min-w-full text-left text-sm">
                                    <thead class="text-xs uppercase tracking-[0.2em] text-slate-400">
                                        <tr class="nl-table-head">
                                            <th class="px-4 py-3">#</th>
                                            <th class="px-4 py-3">Customer</th>
                                            <th class="px-4 py-3">Coupon</th>
                                            <th class="px-4 py-3">Code</th>
                                            <th class="px-4 py-3">Status</th>
                                            <th class="px-4 py-3">Validity</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-800/70 text-slate-200">
                                        @forelse ($claims as $index => $claim)
                                            @php
                                                $customer = $claim->customer;
                                                $coupon = $claim->coupon;
                                                $nameParts = array_filter([$customer?->first_name, $customer?->last_name]);
                                                $name = $nameParts ? implode(' ', $nameParts) : ($customer?->email ?? 'Customer');
                                                $expiresAt = $claim->expires_at ?? $coupon?->end_date;
                                            @endphp
                                            <tr class="nl-table-row">
                                                <td class="px-4 py-4 text-xs text-slate-400">{{ $claims->firstItem() + $index }}</td>
                                                <td class="px-4 py-4">
                                                    <div class="text-sm text-slate-100">{{ $name }}</div>
                                                    <div class="text-xs text-slate-400">{{ $customer?->email }}</div>
                                                </td>
                                                <td class="px-4 py-4 text-sm text-slate-100">{{ $coupon?->title ?? 'Coupon' }}</td>
                                                <td class="px-4 py-4 text-xs text-slate-200">{{ $claim->code }}</td>
                                                <td class="px-4 py-4">
                                                    @php
                                                        $statusLabel = strtoupper($claim->status ?? 'active');
                                                        if ($claim->used_at) {
                                                            $statusLabel = 'USED';
                                                        } elseif ($claim->status === 'expired') {
                                                            $statusLabel = 'EXPIRED';
                                                        }
                                                    @endphp
                                                    @if ($statusLabel === 'USED')
                                                        <span class="nl-badge border border-rose-500/40 bg-rose-500/10 text-rose-200">Used</span>
                                                    @elseif ($statusLabel === 'EXPIRED')
                                                        <span class="nl-badge border border-amber-500/40 bg-amber-500/10 text-amber-200">Expired</span>
                                                    @else
                                                        <span class="nl-badge border border-emerald-500/40 bg-emerald-500/10 text-emerald-200">Unused</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-4 text-xs text-slate-400">
                                                    <div>Claimed: {{ optional($claim->redeemed_at)->format('Y-m-d') }}</div>
                                                    <div>Expires: {{ optional($expiresAt)->format('Y-m-d') ?? '—' }}</div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="px-4 py-6 text-center text-slate-400">No claims yet.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-4">
                                {{ $claims->links() }}
                            </div>
                        </section>
                    </main>
                </div>
            </div>
        </div>
    </body>
</html>
