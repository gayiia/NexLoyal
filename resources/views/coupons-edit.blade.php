<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'NexLoyal') }} - Edit Coupon</title>
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
        </style>
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
        <div class="min-h-screen bg-[radial-gradient(900px_circle_at_top,rgba(56,189,248,0.18),transparent_60%)]">
            <div class="min-h-screen bg-[radial-gradient(700px_circle_at_bottom,rgba(30,64,175,0.22),transparent_60%)]">
                <div class="min-h-screen bg-[linear-gradient(120deg,rgba(15,23,42,0.9),rgba(2,6,23,0.95))] nl-shell">
                    <div class="flex min-h-screen flex-col lg:flex-row">
                        <aside class="w-full border-b border-slate-800/70 bg-slate-950/80 px-6 py-6 lg:w-72 lg:border-b-0 lg:border-r lg:py-8 nl-panel">
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

                        <main class="flex-1 px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
                            <x-page-header eyebrow="" title="Edit coupon" breadcrumb="Rewards / Coupons / Edit">
                                <x-slot name="actions">
                                    <a href="{{ route('coupons') }}" class="rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-2 text-xs text-slate-200 nl-panel-muted">
                                        Back to coupons
                                    </a>
                                </x-slot>
                            </x-page-header>

                            <section class="mt-6 overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel">
                                <div class="border-b border-slate-800/70 px-6 py-4">
                                    <p class="text-sm font-semibold text-slate-100">Update coupon</p>
                                    <p class="mt-1 text-xs text-slate-400">Draft coupons can be edited before activation.</p>
                                </div>
                                <div class="px-6 py-6">
                                    <form method="POST" action="{{ route('coupons.update', $coupon) }}" class="space-y-6">
                                        @csrf
                                        @method('PATCH')
                                        @if ($errors->any())
                                            <div class="rounded-xl border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-xs text-rose-200">
                                                <p class="font-semibold text-rose-100">Fix the highlighted fields to continue.</p>
                                                <p class="mt-1 text-rose-200">{{ $errors->first() }}</p>
                                            </div>
                                        @endif
                                        @if ($productError)
                                            <div class="rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-xs text-amber-200">
                                                <p class="font-semibold text-amber-100">Shopify products unavailable.</p>
                                                <p class="mt-1 text-amber-200">{{ $productError }}</p>
                                            </div>
                                        @endif
                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <div class="flex flex-col gap-2 sm:col-span-2">
                                                <label class="nl-modal-label uppercase text-slate-400">Title</label>
                                                <input class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="text" name="title" value="{{ old('title', $coupon->title) }}" required>
                                                @error('title')
                                                    <p class="text-xs text-rose-300">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <label class="nl-modal-label uppercase text-slate-400">Type</label>
                                                <select class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" name="type" required>
                                                    <option value="amount-order" @selected(old('type', $coupon->type) === 'amount-order')>Amount off order</option>
                                                    <option value="amount-product" @selected(old('type', $coupon->type) === 'amount-product')>Amount off product</option>
                                                    <option value="buy-x-get-y" @selected(old('type', $coupon->type) === 'buy-x-get-y')>Buy X get Y</option>
                                                    <option value="free-shipping" @selected(old('type', $coupon->type) === 'free-shipping')>Free shipping</option>
                                                </select>
                                                @error('type')
                                                    <p class="text-xs text-rose-300">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            <div class="flex flex-col gap-2" data-type-section="amount-order,amount-product">
                                                <label class="nl-modal-label uppercase text-slate-400">Value type</label>
                                                <select class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" name="value_type" data-required>
                                                    <option value="percentage" @selected(old('value_type', $coupon->value_type) === 'percentage')>Percentage</option>
                                                    <option value="fixed" @selected(old('value_type', $coupon->value_type) === 'fixed')>Fixed amount</option>
                                                    <option value="none" @selected(old('value_type', $coupon->value_type) === 'none')>No value</option>
                                                </select>
                                                @error('value_type')
                                                    <p class="text-xs text-rose-300">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            <div class="flex flex-col gap-2" data-type-section="amount-order,amount-product">
                                                <label class="nl-modal-label uppercase text-slate-400">Value</label>
                                                <input class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="text" name="value" value="{{ old('value', $coupon->value) }}" data-required>
                                                @error('value')
                                                    <p class="text-xs text-rose-300">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <label class="nl-modal-label uppercase text-slate-400">Points value</label>
                                                <input class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="number" name="points_value" min="0" value="{{ old('points_value', $coupon->points_value) }}" required>
                                                @error('points_value')
                                                    <p class="text-xs text-rose-300">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <label class="nl-modal-label uppercase text-slate-400">Tier</label>
                                                <select class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" name="tier_id">
                                                    <option value="" @selected(!old('tier_id', $coupon->tier_id))>All tiers</option>
                                                    @foreach ($tiers as $tier)
                                                        <option value="{{ $tier->id }}" @selected((string) old('tier_id', $coupon->tier_id) === (string) $tier->id)>{{ $tier->title }}</option>
                                                    @endforeach
                                                </select>
                                                @error('tier_id')
                                                    <p class="text-xs text-rose-300">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            <div class="flex flex-col gap-2 sm:col-span-2" data-type-section="amount-product">
                                                <label class="nl-modal-label uppercase text-slate-400">Eligible products</label>
                                                <div class="nl-product-shell">
                                                    <div class="nl-product-scroll">
                                                        <div class="nl-product-grid">
                                                            @forelse ($products as $product)
                                                                <label class="nl-product-card">
                                                                    <input type="checkbox" name="product_ids[]" value="{{ $product['id'] }}" @checked(in_array($product['id'], old('product_ids', $coupon->product_ids ?? []), true))>
                                                                    <span class="nl-product-title text-slate-200">{{ $product['title'] }}</span>
                                                                </label>
                                                            @empty
                                                                <p class="text-xs text-slate-400">No products found.</p>
                                                            @endforelse
                                                        </div>
                                                    </div>
                                                </div>
                                                @error('product_ids')
                                                    <p class="text-xs text-rose-300">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            <div class="flex flex-col gap-2 sm:col-span-2" data-type-section="buy-x-get-y">
                                                <label class="nl-modal-label uppercase text-slate-400">Buy products</label>
                                                <div class="nl-product-shell">
                                                    <div class="nl-product-scroll">
                                                        <div class="nl-product-grid">
                                                            @forelse ($products as $product)
                                                                <label class="nl-product-card">
                                                                    <input type="checkbox" name="buy_product_ids[]" value="{{ $product['id'] }}" @checked(in_array($product['id'], old('buy_product_ids', $coupon->buy_product_ids ?? []), true))>
                                                                    <span class="nl-product-title text-slate-200">{{ $product['title'] }}</span>
                                                                </label>
                                                            @empty
                                                                <p class="text-xs text-slate-400">No products found.</p>
                                                            @endforelse
                                                        </div>
                                                    </div>
                                                </div>
                                                @error('buy_product_ids')
                                                    <p class="text-xs text-rose-300">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            <div class="flex flex-col gap-2" data-type-section="buy-x-get-y">
                                                <label class="nl-modal-label uppercase text-slate-400">Buy quantity</label>
                                                <input class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="number" name="buy_quantity" min="1" value="{{ old('buy_quantity', $coupon->buy_quantity ?? 1) }}">
                                                @error('buy_quantity')
                                                    <p class="text-xs text-rose-300">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            <div class="flex flex-col gap-2" data-type-section="buy-x-get-y">
                                                <label class="nl-modal-label uppercase text-slate-400">Get quantity</label>
                                                <input class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="number" name="get_quantity" min="1" value="{{ old('get_quantity', $coupon->get_quantity ?? 1) }}">
                                                @error('get_quantity')
                                                    <p class="text-xs text-rose-300">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            <div class="flex flex-col gap-2 sm:col-span-2" data-type-section="buy-x-get-y">
                                                <label class="nl-modal-label uppercase text-slate-400">Get products</label>
                                                <div class="nl-product-shell">
                                                    <div class="nl-product-scroll">
                                                        <div class="nl-product-grid">
                                                            @forelse ($products as $product)
                                                                <label class="nl-product-card">
                                                                    <input type="checkbox" name="get_product_ids[]" value="{{ $product['id'] }}" @checked(in_array($product['id'], old('get_product_ids', $coupon->get_product_ids ?? []), true))>
                                                                    <span class="nl-product-title text-slate-200">{{ $product['title'] }}</span>
                                                                </label>
                                                            @empty
                                                                <p class="text-xs text-slate-400">No products found.</p>
                                                            @endforelse
                                                        </div>
                                                    </div>
                                                </div>
                                                @error('get_product_ids')
                                                    <p class="text-xs text-rose-300">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            <div class="flex flex-col gap-3 sm:col-span-2" data-type-section="buy-x-get-y">
                                                <label class="nl-modal-label uppercase text-slate-400">At a discounted value</label>
                                                <div class="flex flex-wrap gap-4 text-sm text-slate-200">
                                                    <label class="inline-flex items-center gap-2">
                                                        <input type="radio" name="buyx_discount_type" value="percentage" @checked(old('buyx_discount_type', $coupon->buyx_discount_type) === 'percentage') data-buyx-discount>
                                                        <span>Percentage</span>
                                                    </label>
                                                    <label class="inline-flex items-center gap-2">
                                                        <input type="radio" name="buyx_discount_type" value="amount" @checked(old('buyx_discount_type', $coupon->buyx_discount_type) === 'amount') data-buyx-discount>
                                                        <span>Amount off each</span>
                                                    </label>
                                                    <label class="inline-flex items-center gap-2">
                                                        <input type="radio" name="buyx_discount_type" value="free" @checked(old('buyx_discount_type', $coupon->buyx_discount_type ?? 'free') === 'free') data-buyx-discount>
                                                        <span>Free</span>
                                                    </label>
                                                </div>
                                                <div>
                                                    <input class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="number" step="0.01" min="0" name="buyx_discount_value" value="{{ old('buyx_discount_value', $coupon->buyx_discount_value) }}" placeholder="Discount value" data-buyx-value>
                                                    @error('buyx_discount_type')
                                                        <p class="text-xs text-rose-300">{{ $message }}</p>
                                                    @enderror
                                                    @error('buyx_discount_value')
                                                        <p class="text-xs text-rose-300">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <label class="nl-modal-label uppercase text-slate-400">Start date</label>
                                                <input class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="date" name="start_date" value="{{ old('start_date', optional($coupon->start_date)->format('Y-m-d')) }}" required>
                                                @error('start_date')
                                                    <p class="text-xs text-rose-300">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <label class="nl-modal-label uppercase text-slate-400">End date</label>
                                                <input class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="date" name="end_date" value="{{ old('end_date', optional($coupon->end_date)->format('Y-m-d')) }}" required>
                                                @error('end_date')
                                                    <p class="text-xs text-rose-300">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            <div class="flex flex-col gap-2 sm:col-span-2">
                                                <label class="nl-modal-label uppercase text-slate-400">Description</label>
                                                <textarea class="min-h-[100px] rounded-lg border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-slate-200" name="description">{{ old('description', $coupon->description) }}</textarea>
                                                @error('description')
                                                    <p class="text-xs text-rose-300">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-800 pt-5">
                                            <a href="{{ route('coupons') }}" class="rounded-xl border border-slate-700 px-4 py-2 text-xs text-slate-200">Cancel</a>
                                            <button type="submit" class="nl-modal-primary rounded-xl px-5 py-2 text-xs">
                                                Save changes
                                            </button>
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
                const storageKey = 'nl-theme';
                const body = document.body;
                const settingsToggle = document.getElementById('settings-toggle');
                const settingsMenu = document.getElementById('settings-menu');

                const applyTheme = (theme) => {
                    if (theme === 'light') {
                        body.classList.add('nl-theme-light');
                    } else {
                        body.classList.remove('nl-theme-light');
                    }
                };

                const stored = localStorage.getItem(storageKey);
                applyTheme(stored || 'dark');

                const shouldOpenSettings = window.location.pathname.startsWith('/settings');
                if (settingsMenu) {
                    settingsMenu.classList.toggle('hidden', !shouldOpenSettings);
                }
                if (settingsToggle && settingsMenu) {
                    settingsToggle.addEventListener('click', () => {
                        settingsMenu.classList.toggle('hidden');
                    });
                }

                const typeSelect = document.querySelector('[name="type"]');
                const typeSections = document.querySelectorAll('[data-type-section]');
                const updateTypeSections = () => {
                    const activeType = typeSelect ? typeSelect.value : '';
                    typeSections.forEach((section) => {
                        const types = (section.dataset.typeSection || '').split(',');
                        section.classList.toggle('hidden', !types.includes(activeType));
                    });
                };

                if (typeSelect) {
                    typeSelect.addEventListener('change', updateTypeSections);
                }
                updateTypeSections();

                const discountRadios = document.querySelectorAll('[data-buyx-discount]');
                const discountValueInput = document.querySelector('[data-buyx-value]');
                const updateBuyXDiscount = () => {
                    if (!discountValueInput) {
                        return;
                    }
                    const selected = document.querySelector('[data-buyx-discount]:checked');
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
            })();
        </script>
    </body>
</html>
