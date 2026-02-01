{{-- This view provides the form to create a new mystery box and assign tiers and coupons. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        {{-- The title uses the app name configuration with a fallback for local/dev environments. --}}
        <title>{{ config('app.name', 'NexLoyal') }} - Create Mystery Box</title>
        {{-- Preconnect and load the UI font used across the admin experience. --}}
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        {{-- Vite builds and injects the compiled CSS for this page. --}}
        @vite(['resources/css/app.css'])
        <style>
            {{-- These styles define light-mode overrides for the form panels. --}}
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
            .nl-theme-light .nl-panel-muted {
                background-color: rgba(226, 232, 240, 0.6);
                border-color: rgba(148, 163, 184, 0.4);
                color: #0f172a;
            }
            .nl-theme-light .nl-text-muted { color: #475569; }
        </style>
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
        <div class="min-h-screen bg-[radial-gradient(900px_circle_at_top,rgba(56,189,248,0.18),transparent_60%)]">
            <div class="min-h-screen bg-[linear-gradient(120deg,rgba(15,23,42,0.9),rgba(2,6,23,0.95))] nl-shell">
                <div class="flex min-h-screen flex-col lg:flex-row">
                    {{-- The admin sidebar is shared across the dashboard and provides navigation. --}}
                    @include('partials.admin-sidebar')

                    <main class="flex-1 px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
                        {{-- The header anchors the create flow and provides a return link. --}}
                        <x-page-header eyebrow="" title="Create Mystery Box" breadcrumb="Coupons / Mystery Box">
                            <x-slot name="actions">
                                <a href="{{ route('mystery-boxes') }}" class="rounded-xl border border-slate-800 px-4 py-2 text-xs text-slate-200 nl-panel-muted">
                                    Back to list
                                </a>
                            </x-slot>
                        </x-page-header>

                        {{-- This form creates the mystery box configuration in the backend. --}}
                        <form method="POST" action="{{ route('mystery-boxes.store') }}" class="mt-6 space-y-6">
                            @csrf
                            <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 nl-panel">
                                <h2 class="text-sm font-semibold text-slate-100">Overview</h2>
                                <div class="mt-4 grid gap-4 md:grid-cols-2">
                                    <div class="flex flex-col gap-2">
                                        {{-- Name identifies the box for admins and reporting. --}}
                                        <label class="text-xs uppercase tracking-[0.2em] text-slate-400">Name</label>
                                        <input class="rounded-lg border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-slate-200" type="text" name="name" value="{{ old('name') }}" required>
                                        @error('name')
                                            <p class="text-xs text-rose-300">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        {{-- Claim rules control how often a customer can redeem. --}}
                                        <label class="text-xs uppercase tracking-[0.2em] text-slate-400">Claim rule</label>
                                        <select class="rounded-lg border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-slate-200" name="claim_rule" required>
                                            <option value="ONCE_PER_DAY" @selected(old('claim_rule', 'ONCE_PER_DAY') === 'ONCE_PER_DAY')>Once per day</option>
                                            <option value="ONCE_PER_WEEK" @selected(old('claim_rule') === 'ONCE_PER_WEEK')>Once per week</option>
                                            <option value="ONCE_EVER" @selected(old('claim_rule') === 'ONCE_EVER')>Once ever</option>
                                        </select>
                                        @error('claim_rule')
                                            <p class="text-xs text-rose-300">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        {{-- Date limits are optional and constrain eligibility windows. --}}
                                        <label class="text-xs uppercase tracking-[0.2em] text-slate-400">Start date</label>
                                        <input class="rounded-lg border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-slate-200" type="date" name="starts_at" value="{{ old('starts_at') }}">
                                        @error('starts_at')
                                            <p class="text-xs text-rose-300">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        {{-- End date closes the claim window when set. --}}
                                        <label class="text-xs uppercase tracking-[0.2em] text-slate-400">End date</label>
                                        <input class="rounded-lg border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-slate-200" type="date" name="ends_at" value="{{ old('ends_at') }}">
                                        @error('ends_at')
                                            <p class="text-xs text-rose-300">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </section>

                            <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 nl-panel">
                                <h2 class="text-sm font-semibold text-slate-100">Visible tiers</h2>
                                <div class="mt-4 grid gap-3 md:grid-cols-2">
                                    {{-- Selecting tiers limits visibility to those customer levels. --}}
                                    @foreach ($tiers as $tier)
                                        <label class="flex items-center gap-3 rounded-lg border border-slate-800 px-3 py-2 text-sm text-slate-200">
                                            <input type="checkbox" name="tiers[]" value="{{ $tier->id }}" @checked(in_array($tier->id, old('tiers', []), true))>
                                            <span>{{ $tier->title }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('tiers')
                                    <p class="mt-2 text-xs text-rose-300">{{ $message }}</p>
                                @enderror
                            </section>

                            <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 nl-panel">
                                <h2 class="text-sm font-semibold text-slate-100">Mystery Box coupons</h2>
                                {{-- Only coupons flagged for mystery boxes can be assigned. --}}
                                <p class="mt-1 text-xs text-slate-400">Only coupons flagged as Mystery Box are available.</p>
                                <div class="mt-4 grid gap-3 md:grid-cols-2">
                                    {{-- These coupons become the possible rewards for this box. --}}
                                    @forelse ($coupons as $coupon)
                                        <label class="flex items-center gap-3 rounded-lg border border-slate-800 px-3 py-2 text-sm text-slate-200">
                                            <input type="checkbox" name="coupons[]" value="{{ $coupon->id }}" @checked(in_array($coupon->id, old('coupons', []), true))>
                                            <span>{{ $coupon->title }}</span>
                                        </label>
                                    @empty
                                        {{-- Empty state when there are no eligible coupons. --}}
                                        <p class="text-sm text-slate-400">No Mystery Box coupons found.</p>
                                    @endforelse
                                </div>
                                @error('coupons')
                                    <p class="mt-2 text-xs text-rose-300">{{ $message }}</p>
                                @enderror
                            </section>

                            <div class="flex flex-wrap justify-end gap-3">
                                {{-- Cancel returns to the list without saving. --}}
                                <a href="{{ route('mystery-boxes') }}" class="rounded-xl border border-slate-700 px-4 py-2 text-xs text-slate-200">Cancel</a>
                                {{-- Submit saves the mystery box configuration. --}}
                                <button type="submit" class="rounded-xl bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-900">
                                    Save mystery box
                                </button>
                            </div>
                        </form>
                    </main>
                </div>
            </div>
        </div>
    </body>
</html>
