<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'NexLoyal') }} - Feature Preview</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        @vite(['resources/css/app.css'])
        <style>
            :root { color-scheme: dark; }
            body { letter-spacing: 0.01em; }
            .nl-theme-light { color-scheme: light; background-color: #f8fafc; color: #0f172a; }
            .nl-theme-light .nl-shell { background: linear-gradient(120deg, rgba(248, 250, 252, 0.95), rgba(226, 232, 240, 0.95)); }
            .nl-theme-light .nl-panel { background-color: rgba(255, 255, 255, 0.85); border-color: rgba(148, 163, 184, 0.4); color: #0f172a; }
            .nl-theme-light .nl-text-muted { color: #475569; }
        </style>
    </head>
    <body class="{{ session('appearance', 'dark') === 'light' ? 'nl-theme-light' : '' }} bg-slate-950 text-slate-100">
        <div class="min-h-screen bg-gradient-to-br from-slate-950 via-slate-950 to-slate-900 nl-shell">
            <div class="mx-auto flex min-h-screen max-w-[1400px]">
                @include('partials.admin-sidebar')

                <main class="flex-1 p-8">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-[0.32em] text-slate-400 nl-text-muted">AI Features</p>
                            <h1 class="mt-2 text-2xl font-semibold">Feature Preview</h1>
                            <p class="mt-2 text-sm text-slate-400 nl-text-muted">Computed customer features before training.</p>
                        </div>
                        <form method="GET" action="{{ route('ai-features') }}" class="flex items-center gap-2">
                            <input type="text" name="search" value="{{ $search }}" placeholder="Search id/email"
                                   class="rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-100">
                            <button type="submit" class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-200 hover:border-slate-500">Search</button>
                        </form>
                    </div>

                    <div class="mt-6 overflow-hidden rounded-2xl border border-slate-800 bg-slate-950/70 nl-panel">
                        <div class="overflow-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-900/70 text-xs uppercase tracking-[0.18em] text-slate-400">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Customer</th>
                                        <th class="px-4 py-3 text-left">Total Spend</th>
                                        <th class="px-4 py-3 text-left">Orders</th>
                                        <th class="px-4 py-3 text-left">Avg Order</th>
                                        <th class="px-4 py-3 text-left">Recency (days)</th>
                                        <th class="px-4 py-3 text-left">Tenure (days)</th>
                                        <th class="px-4 py-3 text-left">Flags</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($features as $feature)
                                        <tr class="border-t border-slate-800/70">
                                            <td class="px-4 py-3">
                                                <div class="text-slate-100">#{{ $feature->customer_id }}</div>
                                                <div class="text-xs text-slate-400 nl-text-muted">{{ $feature->customer?->email ?? $feature->customer?->shopify_id }}</div>
                                            </td>
                                            <td class="px-4 py-3">{{ $feature->total_spent }}</td>
                                            <td class="px-4 py-3">{{ $feature->orders_count }}</td>
                                            <td class="px-4 py-3">{{ $feature->avg_order_value }}</td>
                                            <td class="px-4 py-3">{{ $feature->days_since_last_order ?? '—' }}</td>
                                            <td class="px-4 py-3">{{ $feature->tenure_days ?? '—' }}</td>
                                            <td class="px-4 py-3">
                                                <div class="text-xs text-slate-300">
                                                    @if($feature->is_new_customer)
                                                        <span class="mr-2 rounded-full bg-slate-800 px-2 py-1">new_customer</span>
                                                    @endif
                                                    @if($feature->is_excluded)
                                                        <span class="rounded-full bg-rose-500/20 px-2 py-1 text-rose-100">{{ $feature->excluded_reason ?? 'excluded' }}</span>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="px-4 py-6 text-center text-slate-400">No features found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="border-t border-slate-800/70 px-4 py-4">
                            {{ $features->links() }}
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </body>
</html>
